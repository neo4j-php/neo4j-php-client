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
use function count;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
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

    private int $ownerCount = 0;
    private string $serverState = 'DISCONNECTED';
    /** @var list<WeakReference<SummarizedResult>> */
    private array $subscribedResults = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(BoltFactory $factory, ?V3 $boltProtocol, ConnectionConfiguration $config)
    {
        $this->factory = $factory;
        $this->boltProtocol = $boltProtocol;
        $this->config = $config;
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
        return $this->boltProtocol !== null;
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

    public function close(): void
    {
        $this->possibleStates(['READY', 'STREAMING', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'], 'GOODBYE');

        $this->signal('DISCONNECT');

        $this->consumeResults();

        $this->protocol()->goodbye();

        $this->boltProtocol = null;
        $this->serverState = 'DEFUNCT';
        $this->subscribedResults = [];
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

    public function reset(): void
    {
        $this->possibleStates(['READY', 'STREAMING', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'], 'RESET');

        $this->signal('INTERRUPT');

        $this->consumeResults();

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
     * @param string|null $database the database to connect to
     * @param float|null  $timeout  timeout in seconds
     */
    public function begin(?string $database, ?float $timeout): void
    {
        $this->possibleStates(['READY', 'INTERRUPTED'], 'BEGIN');

        $this->consumeResults();

        $extra = $this->buildExtra($database, $timeout);
        try {
            $this->protocol()->begin($extra);
        } catch (IgnoredException $e) {
            $this->serverState = 'INTERRUPTED';

            throw $e;
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        }
    }

    /**
     * @param string|null $database the database to connect to
     * @param float|null  $timeout  timeout in seconds
     */
    public function discard(?int $qid): void
    {
        $this->possibleStates(['STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'], 'DISCARD');

        try {
            $extra = $this->buildExtraParam(null, $qid);
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
     * @return BoltMeta
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout): array
    {
        $this->possibleStates(['READY', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'], 'RUN');

        if (!str_starts_with($this->serverState, 'TX_')) {
            $this->consumeResults();
        }

        try {
            $extra = $this->buildExtra($database, $timeout);

            $tbr = $this->protocol()->run($text, $parameters, $extra);

            if (str_starts_with($this->serverState, 'TX_')) {
                $this->serverState = 'TX_STREAMING';
            } else {
                $this->serverState = 'STREAMING';
            }

            return $tbr;
        } catch (MessageException $e) {
            $this->serverState = 'FAILED';

            throw $e;
        } catch (IgnoredException $e) {
            $this->serverState = 'IGNORED';

            throw $e;
        }
    }

    public function commit(): void
    {
        $this->possibleStates(['TX_READY', 'INTERRUPTED'], 'COMMIT');

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

    public function rollback(): void
    {
        $this->possibleStates(['TX_READY', 'INTERRUPTED'], 'ROLLBACK');

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
     * @return non-empty-list<list>
     */
    public function pull(?int $qid, ?int $fetchSize): array
    {
        $this->possibleStates(['STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'], 'ROLLBACK');
        $extra = $this->buildExtraParam($fetchSize, $qid);

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

        $this->interpretResult($tbr);

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
        if ($this->serverState !== 'DISCONNECTED' && $this->serverState !== 'DEFUNCT' && $this->boltProtocol !== null) {
            $this->close();
        }
    }

    private function buildExtra(?string $database, ?float $timeout): array
    {
        $extra = [];
        if ($database) {
            $extra['db'] = $database;
        }
        if ($timeout) {
            $extra['tx_timeout'] = $timeout * 1000;
        }

        return $extra;
    }

    public function buildExtraParam(?int $fetchSize, ?int $qid): array
    {
        $extra = [];
        if ($fetchSize) {
            $extra['n'] = $fetchSize;
        }

        if ($qid) {
            $extra['qid'] = $qid;
        }

        return $extra;
    }

    /**
     * @return string
     */
    public function getServerState(): string
    {
        return $this->serverState;
    }

    /**
     * @param callable(ServerStateTransition): void $listener
     */
    private function bindAfterTransitionEventListener($listener): void
    {
        $this->subscribedResults[] = $listener;
    }

    private function possibleStates(array $array, string $string)
    {
    }

    private function signal(string $string)
    {
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
            if ($has_more ?? count($this->subscribedResults) === 1) {
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
}