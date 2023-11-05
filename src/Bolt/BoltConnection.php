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

use Bolt\protocol\Response;
use Bolt\protocol\ServerState;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;
use WeakReference;

/**
 * @implements ConnectionInterface<V4_4|V5|V5_1|V5_2|V5_3>
 *
 * @internal
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
     *
     * @var list<WeakReference<CypherList>>
     */
    private array $subscribedResults = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private V4_4|V5|V5_1|V5_2|V5_3 $boltProtocol,
        /** @psalm-readonly */
        private ConnectionConfiguration $config
    ) {}

    public function getEncryptionLevel(): string
    {
        return $this->config->getEncryptionLevel();
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
        return !in_array($this->boltProtocol->serverState->get(), ['DISCONNECTED', 'DEFUNCT'], true);
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
        $response = $this->boltProtocol->reset()
            ->getResponse();

        $this->assertNoFailure($response);

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

        $response = $this->boltProtocol
            ->begin($extra)
            ->getResponse();

        $this->assertNoFailure($response);
    }

    /**
     * Discards a result.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function discard(?int $qid): void
    {
        $extra = $this->buildResultExtra(null, $qid);
        $bolt = $this->boltProtocol;

        $response = $bolt->discard($extra)
            ->getResponse();

        $this->assertNoFailure($response);
    }

    /**
     * Runs a query/statement.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout, BookmarkHolder $holder): array
    {
        $extra = $this->buildRunExtra($database, $timeout, $holder);
        $response = $this->boltProtocol->run($text, $parameters, $extra)
            ->getResponse();

        $this->assertNoFailure($response);

        return $response->getContent();
    }

    /**
     * Commits a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function commit(): void
    {
        $this->consumeResults();
        $response = $this->boltProtocol
            ->commit()
            ->getResponse();

        $this->assertNoFailure($response);
    }

    /**
     * Rolls back a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function rollback(): void
    {
        $this->consumeResults();
        $response = $this->boltProtocol
            ->rollback()
            ->getResponse();

        $this->assertNoFailure($response);
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

        $tbr = [];
        /** @var Response $response */
        foreach ($this->boltProtocol->pull($extra)->getResponses() as $response) {
            $this->assertNoFailure($response);

            $tbr[] = $response->getContent();
        }

        /** @var non-empty-list<list> */
        return $tbr;
    }

    public function __destruct()
    {
        if (!$this->boltProtocol->serverState->is(ServerState::FAILED) && $this->isOpen()) {
            if ($this->boltProtocol->serverState->is(ServerState::STREAMING, ServerState::TX_STREAMING)) {
                $this->consumeResults();
            }

            $this->boltProtocol->goodbye();

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
        /** @var ServerState::* */
        return $this->boltProtocol->serverState->get();
    }

    public function subscribeResult(CypherList $result): void
    {
        $this->subscribedResults[] = WeakReference::create($result);
    }

    private function assertNoFailure(Response $response): void
    {
        if ($response->getSignature() === Response::SIGNATURE_FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }
    }
}
