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

use Bolt\enum\ServerState;
use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Exception;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;
use WeakReference;

/**
 * @implements ConnectionInterface<array{0: V4_4|V5|V5_1|V5_2|V5_3|V5_4|null, 1: Connection}>
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

    /**
     * @return array{0: V4_4|V5|V5_1|V5_2|V5_3|V5_4|null, 1: Connection}
     */
    public function getImplementation(): array
    {
        return [$this->boltProtocol, $this->connection];
    }

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private V4_4|V5|V5_1|V5_2|V5_3|V5_4|null $boltProtocol,
        private readonly Connection $connection,
        private readonly AuthenticateInterface $auth,
        private readonly string $userAgent,
        /** @psalm-readonly */
        private readonly ConnectionConfiguration $config,
        private readonly ?Neo4jLogger $logger,
    ) {}

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

    public function isOpen(): bool
    {
        if (!isset($this->boltProtocol)) {
            return false;
        }

        return !in_array(
            $this->protocol()->serverState,
            [ServerState::DISCONNECTED, ServerState::DEFUNCT],
            true
        );
    }

    public function isStreaming(): bool
    {
        return in_array(
            $this->protocol()->serverState,
            [ServerState::STREAMING, ServerState::TX_STREAMING],
            true
        );
    }

    public function setTimeout(float $timeout): void
    {
        $this->connection->setTimeout($timeout);
    }

    public function consumeResults(): void
    {
        $this->logger?->log(LogLevel::DEBUG, 'Consuming results');
        if (!$this->isStreaming()) {
            $this->subscribedResults = [];

            return;
        }

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
        $this->logger?->log(LogLevel::DEBUG, 'RESET');
        $response = $this->protocol()
            ->reset()
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

        $extra = $this->buildRunExtra($database, $timeout, $holder, AccessMode::WRITE());
        $this->logger?->log(LogLevel::DEBUG, 'BEGIN', $extra);
        $response = $this->protocol()
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
        $this->logger?->log(LogLevel::DEBUG, 'DISCARD', $extra);
        $response = $this->protocol()
            ->discard($extra)
            ->getResponse();
        $this->assertNoFailure($response);
    }

    /**
     * Runs a query/statement.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     *
     * @return BoltMeta
     */
    public function run(
        string $text,
        array $parameters,
        ?string $database,
        ?float $timeout,
        BookmarkHolder $holder,
        ?AccessMode $mode
    ): array {
        $extra = $this->buildRunExtra($database, $timeout, $holder, $mode);
        $this->logger?->log(LogLevel::DEBUG, 'RUN', $extra);
        $response = $this->protocol()
            ->run($text, $parameters, $extra)
            ->getResponse();
        $this->assertNoFailure($response);
        /** @var BoltMeta */
        return $response->content;
    }

    /**
     * Commits a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function commit(): void
    {
        $this->logger?->log(LogLevel::DEBUG, 'COMMIT');
        $this->consumeResults();

        $response = $this->protocol()
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
        $this->logger?->log(LogLevel::DEBUG, 'ROLLBACK');
        $this->consumeResults();

        $response = $this->protocol()
            ->rollback()
            ->getResponse();
        $this->assertNoFailure($response);
    }

    public function protocol(): V4_4|V5|V5_1|V5_2|V5_3|V5_4
    {
        if (!isset($this->boltProtocol)) {
            throw new Exception('Connection is closed');
        }

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
        $this->logger?->log(LogLevel::DEBUG, 'PULL', $extra);

        $tbr = [];
        /** @var Response $response */
        foreach ($this->protocol()->pull($extra)->getResponses() as $response) {
            $this->assertNoFailure($response);
            $tbr[] = $response->content;
        }

        /** @var non-empty-list<list> */
        return $tbr;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        try {
            if ($this->isOpen()) {
                if ($this->isStreaming()) {
                    $this->consumeResults();
                }

                $this->logger?->log(LogLevel::DEBUG, 'GOODBYE');
                $this->protocol()->goodbye();

                unset($this->boltProtocol); // has to be set to null as the sockets don't recover nicely contrary to what the underlying code might lead you to believe;
            }
        } catch (\Throwable) {
        }
    }

    private function buildRunExtra(?string $database, ?float $timeout, BookmarkHolder $holder, ?AccessMode $mode): array
    {
        $extra = [];
        if ($database !== null) {
            $extra['db'] = $database;
        }
        if ($timeout !== null) {
            $extra['tx_timeout'] = (int) ($timeout * 1000);
        }

        if (!$holder->getBookmark()->isEmpty()) {
            $extra['bookmarks'] = $holder->getBookmark()->values();
        }

        if ($mode) {
            $extra['mode'] = AccessMode::WRITE() === $mode ? 'w' : 'r';
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
        if (!isset($this->boltProtocol)) {
            return ServerState::DISCONNECTED->name;
        }

        return $this->protocol()->serverState->name;
    }

    public function subscribeResult(CypherList $result): void
    {
        $this->subscribedResults[] = WeakReference::create($result);
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    private function assertNoFailure(Response $response): void
    {
        if ($response->signature === Signature::FAILURE) {
            $this->logger?->log(LogLevel::ERROR, 'FAILURE');
            $resetResponse = $this->protocol()->reset()->getResponse();
            $this->subscribedResults = [];
            if ($resetResponse->signature === Signature::FAILURE) {
                throw new Neo4jException([Neo4jError::fromBoltResponse($resetResponse), Neo4jError::fromBoltResponse($response)]);
            }
            throw Neo4jException::fromBoltResponse($response);
        }
    }
}
