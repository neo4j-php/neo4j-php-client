<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use BadMethodCallException;
use Bolt\error\IgnoredException;
use Bolt\error\MessageException;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Types\CypherList;
use LogicException;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use function str_starts_with;
use WeakReference;

/**
 * @implements ConnectionInterface<V3>
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class BoltConnection implements ConnectionInterface
{
    private ?V3 $boltProtocol;
    /** @psalm-readonly */
    private ConnectionConfiguration $config;
    /** @psalm-readonly */
    private BoltFactory $factory;

    private string $serverState = 'DISCONNECTED';
    /**
     * @note We are using references to "subscribed results" to maintain backwards compatibility and try and strike
     *       a balance between performance and ease of use.
     *       The connection will discard or pull the results depending on the server state transition. This way end
     *       users don't have to worry about consuming result sets, it will all happen behind te scenes. There are some
     *       edge cases where the result set will be pulled or discarded when it is not strictly necessary, and we
     *       should introduce a "manual" mode later down the road to allow the end users to optimise the result
     *       consumption themselves.
     *       A great moment to do this would be when neo4j 5 is released as it will presumably allow us to do more
     *       stuff with PULL and DISCARD messages.
     *
     * @var list<WeakReference<CypherList>>
     */
    private array $subscribedResults = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(BoltFactory $factory, ?V3 $boltProtocol, ConnectionConfiguration $config)
    {
        $this->factory = $factory;
        $this->boltProtocol = $boltProtocol;
        $this->config = $config;
        if ($boltProtocol) {
            $this->serverState = 'READY';
        }
    }

    /**
     * @psalm-mutation-free
     */
    public function getImplementation(): V3
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Connection is closed');
        }

        return $this->boltProtocol;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAgent(): string
    {
        return $this->config->getServerAgent();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface
    {
        return $this->config->getServerAddress();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerVersion(): string
    {
        return $this->config->getServerVersion();
    }

    /**
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->config->getProtocol();
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode
    {
        return $this->config->getAccessMode();
    }

    /**
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): ?DatabaseInfo
    {
        return $this->config->getDatabaseInfo();
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return $this->serverState !== 'DISCONNECTED' && $this->serverState !== 'DEFUNCT';
    }

    public function open(): void
    {
        if ($this->boltProtocol !== null) {
            throw new BadMethodCallException('Cannot open a connection that is already open');
        }

        $this->boltProtocol = $this->factory->build()[0];
    }

    public function setTimeout(float $timeout): void
    {
        $this->factory->getConnection()->setTimeout($timeout);
    }

    /**
     * Closes the connection.
     *
     * Any of the preconditioned states are: 'READY', 'STREAMING', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     * Sends signal: 'DISCONNECT'
     */
    public function close(): void
    {
        $this->consumeResults();

        $this->protocol()->goodbye();

        $this->serverState = 'DEFUNCT';
        $this->boltProtocol = null; // has to be set to null as the sockets don't recover nicely contrary to what the underlying code might lead you to believe
    }

    private function consumeResults(): void
    {
        foreach ($this->subscribedResults as $result) {
            $result = $result->get();
            if ($result) {
                $result->preload();
            }
        }

        $this->subscribedResults = [];
    }

    /**
     * Resets the connection.
     *
     * Any of the preconditioned states are: 'READY', 'STREAMING', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     * Sends signal: 'INTERRUPT'
     */
    public function reset(): void
    {
        try {
            $this->protocol()->reset();
        } catch (MessageException $e) {
            $this->serverState = 'DEFUNCT';

            throw $e;
        }

        $this->subscribedResults = [];
        $this->serverState = 'READY';
    }

    /**
     * Begins a transaction.
     *
     * Any of the preconditioned states are: 'READY', 'INTERRUPTED'.
     */
    public function begin(?string $database, ?float $timeout): void
    {
        $this->consumeResults();

        $extra = $this->buildRunExtra($database, $timeout);
        try {
            $this->protocol()->begin($extra);
        } catch (IgnoredException $e) {
            $this->serverState = 'INTERRUPTED';

            throw $e;
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        }

        $this->serverState = 'TX_READY';
    }

    public function getFactory(): BoltFactory
    {
        return $this->factory;
    }

    /**
     * Discards a result.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function discard(?int $qid): void
    {
        try {
            $extra = $this->buildResultExtra(null, $qid);
            $bolt = $this->protocol();

            if ($bolt instanceof V4) {
                $result = $bolt->discard($extra);
            } else {
                $result = $bolt->discardAll($extra);
            }

            $this->interpretResult($result);
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }
    }

    /**
     * Runs a query/statement.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     *
     * @return BoltMeta
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout): array
    {
        if (!str_starts_with($this->serverState, 'TX_') || str_starts_with($this->getServerVersion(), '3')) {
            $this->consumeResults();
        }

        try {
            $extra = $this->buildRunExtra($database, $timeout);

            $tbr = $this->protocol()->run($text, $parameters, $extra);

            if (str_starts_with($this->serverState, 'TX_')) {
                $this->serverState = 'TX_STREAMING';
            } else {
                $this->serverState = 'STREAMING';
            }

            /** @var BoltMeta */
            return $tbr;
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }
    }

    /**
     * Commits a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function commit(): void
    {
        $this->consumeResults();

        try {
            $this->protocol()->commit();
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }

        $this->serverState = 'READY';
    }

    /**
     * Rolls back a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function rollback(): void
    {
        $this->consumeResults();

        try {
            $this->protocol()->rollback();
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }

        $this->serverState = 'READY';
    }

    /**
     * Pulls a result set.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     *
     * @return non-empty-list<list>
     */
    public function pull(?int $qid, ?int $fetchSize): array
    {
        $extra = $this->buildResultExtra($fetchSize, $qid);

        $bolt = $this->protocol();
        try {
            if (!$bolt instanceof V4) {
                /** @var non-empty-list<list> */
                $tbr = $bolt->pullAll($extra);
            } else {
                /** @var non-empty-list<list> */
                $tbr = $bolt->pull($extra);
            }
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }

        $this->interpretResult($tbr[count($tbr) - 1]);

        return $tbr;
    }

    /**
     * @psalm-mutation-free
     */
    public function getDriverConfiguration(): DriverConfiguration
    {
        return $this->config->getDriverConfiguration();
    }

    public function __destruct()
    {
        if ($this->serverState !== 'FAILED' && $this->isOpen()) {
            $this->close();
        }
    }

    private function buildRunExtra(?string $database, ?float $timeout): array
    {
        $extra = [];
        if ($database) {
            $extra['db'] = $database;
        }
        if ($timeout) {
            $extra['tx_timeout'] = (int) ($timeout * 1000);
        }

        return $extra;
    }

    private function buildResultExtra(?int $fetchSize, ?int $qid): array
    {
        $extra = [];
        if ($fetchSize !== null) {
            $extra['n'] = $fetchSize;
        }

        if ($qid !== null) {
            $extra['qid'] = $qid;
        }

        return $extra;
    }

    public function getServerState(): string
    {
        return $this->serverState;
    }

    public function subscribeResult(CypherList $result): void
    {
        $this->subscribedResults[] = WeakReference::create($result);
    }

    private function protocol(): V3
    {
        if ($this->boltProtocol === null) {
            throw new LogicException('Cannot use protocol if it is not created');
        }

        return $this->boltProtocol;
    }

    private function interpretResult(array $result): void
    {
        if (str_starts_with($this->serverState, 'TX_')) {
            if ($result['has_more'] ?? ($this->countResults() > 1)) {
                $this->serverState = 'TX_STREAMING';
            } else {
                $this->serverState = 'TX_READY';
            }
        } elseif ($result['has_more'] ?? false) {
            $this->serverState = 'STREAMING';
        } else {
            $this->serverState = 'READY';
        }
    }

    private function countResults(): int
    {
        $ctr = 0;
        foreach ($this->subscribedResults as $result) {
            if ($result->get() !== null) {
                ++$ctr;
            }
        }

        return $ctr;
    }
}
