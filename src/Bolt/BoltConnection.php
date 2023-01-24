<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use Bolt\protocol\V3;
use Bolt\protocol\V4;

use function in_array;

use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;

use function str_starts_with;

use WeakReference;

/**
 * @implements ConnectionInterface<array{0: V3, 1: Connection}>
 *
 * @psalm-import-type BoltMeta from FormatterInterface
 */
class BoltConnection implements ConnectionInterface
{
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

    public function getImplementation()
    {
        return [$this->boltProtocol, $this->connection];
    }

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private V3 $boltProtocol,
        private Connection $connection,
        private AuthenticateInterface $auth,
        private string $userAgent,
    /** @psalm-readonly */
    private ConnectionConfiguration $config
    ) {
        $this->serverState = 'READY';
        $this->config = $config;
    }

    public function getEncryptionLevel(): string
    {
        return $this->connection->getEncryptionLevel();
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

    public function getAuthentication(): AuthenticateInterface
    {
        return $this->auth;
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return !$this->protocol()->serverState->is('DISCONNECTED', 'DEFUNCT');
    }

    public function setTimeout(float $timeout): void
    {
        $this->connection->setTimeout($timeout);
    }

    public function consumeResults(): void
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
        $this->protocol()->reset();
        $this->subscribedResults = [];
    }

    /**
     * Begins a transaction.
     *
     * Any of the preconditioned states are: 'READY', 'INTERRUPTED'.
     */
    public function begin(?string $database, ?float $timeout, BookmarkHolder $holder): void
    {
        $this->consumeResults();
        $extra = $this->buildRunExtra($database, $timeout, $holder);
        $this->protocol()->begin($extra);
    }

    /**
     * Discards a result.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function discard(?int $qid): void
    {
        $extra = $this->buildResultExtra(null, $qid);
        $bolt = $this->protocol();

        if ($bolt instanceof V4) {
            $bolt->discard($extra);
        } else {
            $bolt->discardAll($extra);
        }
    }

    /**
     * Runs a query/statement.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     *
     * @return BoltMeta
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout, BookmarkHolder $holder): array
    {
        if (!str_starts_with($this->protocol()->serverState->get(), 'TX_') || str_starts_with($this->getServerVersion(), '3')) {
            $this->consumeResults();
        }

        $extra = $this->buildRunExtra($database, $timeout, $holder);
        $tbr = $this->protocol()->run($text, $parameters, $extra);

        /** @var BoltMeta */
        return $tbr;
    }

    /**
     * Commits a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function commit(): void
    {
        $this->consumeResults();
        $this->protocol()->commit();
    }

    /**
     * Rolls back a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function rollback(): void
    {
        $this->consumeResults();
        $this->protocol()->rollback();
    }

    public function protocol(): V3
    {
        return $this->boltProtocol;
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
        if (!$bolt instanceof V4) {
            /** @var non-empty-list<list> */
            $tbr = $bolt->pullAll($extra);
        } else {
            /** @var non-empty-list<list> */
            $tbr = $bolt->pull($extra);
        }

        $this->interpretResult($tbr[count($tbr) - 1]);

        return $tbr;
    }

    public function __destruct()
    {
        if ($this->serverState !== 'FAILED' && $this->isOpen()) {
            if (in_array($this->serverState, ['STREAMING', 'TX_STREAMING'])) {
                $this->consumeResults();
            }

            $this->protocol()->goodbye();

            $this->serverState = 'DEFUNCT';
            unset($this->boltProtocol); // has to be set to null as the sockets don't recover nicely contrary to what the underlying code might lead you to believe;
        }
    }

    private function buildRunExtra(?string $database, ?float $timeout, BookmarkHolder $holder): array
    {
        $extra = [];
        if ($database) {
            $extra['db'] = $database;
        }
        if ($timeout) {
            $extra['tx_timeout'] = (int) ($timeout * 1000);
        }

        if (!$holder->getBookmark()->isEmpty()) {
            $extra['bookmarks'] = $holder->getBookmark()->values();
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
        return $this->protocol()->serverState->get();
    }

    public function subscribeResult(CypherList $result): void
    {
        $this->subscribedResults[] = WeakReference::create($result);
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

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
}
