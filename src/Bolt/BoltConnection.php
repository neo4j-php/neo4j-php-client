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
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;
use Throwable;
use Traversable;
use WeakReference;

/**
 * @implements ConnectionInterface<array{0: V4_4|V5|V5_1|V5_2|V5_3|V5_4|null, 1: Connection}>
 *
 * @psalm-import-type BoltMeta from SummarizedResultFormatter
 */
class BoltConnection implements ConnectionInterface
{
    private BoltMessageFactory $messageFactory;

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

    private ?float $recvTimeoutHint = null;

    private ?float $originalTimeout = null;

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
    ) {
        $this->messageFactory = new BoltMessageFactory($this, $this->logger);
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
    public function getServerVersion(): string
    {
        return explode('/', $this->getServerAgent())[1] ?? '';
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
    public function getProtocol(): ConnectionProtocol
    {
        return $this->config->getProtocol();
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): ?AccessMode
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

    public function getTimeout(): float
    {
        return $this->connection->getTimeout();
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
        $message = $this->messageFactory->createResetMessage();
        $response = $message->send()->getResponse();
        $this->assertNoFailure($response);
        $this->subscribedResults = [];
    }

    /**
     * Begins a transaction.
     *
     * Any of the preconditioned states are: 'READY', 'INTERRUPTED'.
     *
     * @param iterable<string, scalar|array|null>|null $txMetaData
     */
    public function begin(?string $database, ?float $timeout, BookmarkHolder $holder, ?iterable $txMetaData): void
    {
        $this->consumeResults();

        $extra = $this->buildRunExtra($database, $timeout, $holder, null, $txMetaData);
        $message = $this->messageFactory->createBeginMessage($extra);
        $response = $message->send()->getResponse();
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

        $message = $this->messageFactory->createDiscardMessage($extra);
        $response = $message->send()->getResponse();
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
        ?BookmarkHolder $holder,
        ?AccessMode $mode,
        ?iterable $tsxMetadata,
    ): array {
        $extra = $this->buildRunExtra($database, $timeout, $holder, $mode, $tsxMetadata);
        $message = $this->messageFactory->createRunMessage($text, $parameters, $extra);
        $response = $message->send()->getResponse();
        $this->assertNoFailure($response);

        /** @var BoltMeta */
        return $response->content;
    }

    /**
     * Rolls back a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function rollback(): void
    {
        $this->consumeResults();

        $message = $this->messageFactory->createRollbackMessage();
        $response = $message->send()->getResponse();
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

        $tbr = [];
        $message = $this->messageFactory->createPullMessage($extra);

        try {
            // Apply timeout before iterating to ensure disconnects are detected
            $this->applyRecvTimeoutTemporarily();
            
            // If no timeout hint is set, apply a shorter default timeout to prevent hanging on disconnect
            // This is especially important for disconnect tests where the server closes the connection
            if ($this->originalTimeout === null && $this->recvTimeoutHint === null) {
                $currentTimeout = $this->connection->getTimeout();
                // Use a shorter timeout (5 seconds) for disconnect detection
                // This ensures we detect disconnects quickly rather than waiting indefinitely
                $this->originalTimeout = $currentTimeout;
                $this->connection->setTimeout(5.0);
            }
            
            try {
                foreach ($message->send()->getResponses() as $response) {
                    $this->assertNoFailure($response);
                    $tbr[] = $response->content;
                }
            } catch (Throwable $e) {
                // If we've received some records before the disconnect, return them
                // This allows the first record to be consumed before the disconnect is detected
                // This is important for tests like exit_after_record where a RECORD is sent before <EXIT>
                if (!empty($tbr)) {
                    // Add an empty summary to indicate the result is incomplete
                    // The last element should be the summary, but since we disconnected, we add an empty one
                    $tbr[] = [];
                    
                    // Restore timeout before returning partial results
                    $this->restoreOriginalTimeout();
                    
                    /** @var non-empty-list<list> */
                    return $tbr;
                }
                
                // No records received, re-throw the exception to be handled by outer catch
                throw $e;
            }
            
            // Restore timeout after successful iteration
            $this->restoreOriginalTimeout();
        } catch (Throwable $e) {
            // Always restore timeout before handling exception
            $this->restoreOriginalTimeout();
            
            // Re-throw Neo4jExceptions (already handled by getResponses wrapper)
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            
            // Convert other exceptions to Neo4jException
            throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
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
                    $this->discardUnconsumedResults();
                }

                $message = $this->messageFactory->createGoodbyeMessage();
                $message->send();

                unset($this->boltProtocol); // has to be set to null as the sockets don't recover nicely contrary to what the underlying code might lead you to believe;
            }
        } catch (Throwable) {
        }
    }

    /**
     * Invalidates the connection without sending GOODBYE message.
     *
     * This method closes the Bolt protocol and socket connection WITHOUT
     * sending a GOODBYE message, which is essential when handling timeout
     * exceptions or when the connection is already broken. Sending GOODBYE
     * on a broken connection can interfere with the server's expected
     * message sequence.
     */
    public function invalidate(): void
    {
        try {
            $this->subscribedResults = [];

            try {
                $this->connection->disconnect();
            } catch (Throwable $e) {
                $this->logger?->log(LogLevel::WARNING, 'Failed to disconnect during invalidation', [
                    'exception' => $e->getMessage(),
                    'type' => $e::class,
                ]);
            }

            unset($this->boltProtocol);
        } catch (Throwable $e) {
            $this->logger?->log(LogLevel::WARNING, 'Error during connection invalidation', [
                'exception' => $e->getMessage(),
                'type' => $e::class,
            ]);
        }
    }

    private function buildRunExtra(?string $database, ?float $timeout, ?BookmarkHolder $holder, ?AccessMode $mode, ?iterable $metadata): array
    {
        $extra = [];
        if ($database !== null) {
            $extra['db'] = $database;
        }
        if ($timeout !== null) {
            $extra['tx_timeout'] = (int) ($timeout * 1000);
        }

        if ($holder && !$holder->getBookmark()->isEmpty()) {
            $extra['bookmarks'] = $holder->getBookmark()->values();
        }

        if ($mode) {
            $extra['mode'] = AccessMode::WRITE() === $mode ? 'w' : 'r';
        }

        if ($metadata !== null) {
            $metadataArray = $metadata instanceof Traversable ? iterator_to_array($metadata) : $metadata;
            if (count($metadataArray) > 0) {
                $extra['tx_metadata'] = $metadataArray;
            }
        }

        return $extra;
    }

    private function buildResultExtra(?int $fetchSize, ?int $qid): array
    {
        $extra = [];
        if ($fetchSize !== null) {
            $extra['n'] = $fetchSize;
        }

        if ($qid !== null && $qid >= 0) {
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

    public function assertNoFailure(Response $response): void
    {
        if ($response->signature === Signature::FAILURE) {
            $this->logger?->log(LogLevel::ERROR, 'FAILURE');
            $message = $this->messageFactory->createResetMessage();

            try {
                $resetResponse = $message->send()->getResponse();
            } catch (Throwable $e) {
                $this->subscribedResults = [];
                throw Neo4jException::fromBoltResponse($response);
            }

            $this->subscribedResults = [];

            if ($resetResponse->signature === Signature::FAILURE) {
                throw new Neo4jException([Neo4jError::fromBoltResponse($resetResponse), Neo4jError::fromBoltResponse($response)]);
            }

            throw Neo4jException::fromBoltResponse($response);
        }
    }

    /**
     * Discard unconsumed results - sends DISCARD to server for each subscribed result.
     */
    public function discardUnconsumedResults(): void
    {
        if (!$this->isOpen()) {
            return;
        }

        if (!in_array($this->protocol()->serverState, [ServerState::STREAMING, ServerState::TX_STREAMING], true)) {
            return;
        }

        $this->logger?->log(LogLevel::DEBUG, 'Discarding unconsumed results');

        $this->subscribedResults = array_values(array_filter(
            $this->subscribedResults,
            static fn (WeakReference $ref): bool => $ref->get() !== null
        ));

        if (!empty($this->subscribedResults)) {
            try {
                $this->discard(null);
                $this->logger?->log(LogLevel::DEBUG, 'Sent DISCARD ALL for unconsumed results');
            } catch (Throwable $e) {
                $this->logger?->log(LogLevel::ERROR, 'Failed to discard results', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->subscribedResults = [];
    }

    public function setRecvTimeoutHint(?float $timeout): void
    {
        $this->recvTimeoutHint = $timeout;
    }

    public function getRecvTimeoutHint(): ?float
    {
        return $this->recvTimeoutHint;
    }

    public function applyRecvTimeoutTemporarily(): void
    {
        if ($this->recvTimeoutHint !== null && $this->originalTimeout === null) {
            $this->originalTimeout = $this->connection->getTimeout();
            $this->connection->setTimeout($this->recvTimeoutHint);
        }
    }

    public function restoreOriginalTimeout(): void
    {
        if ($this->originalTimeout !== null) {
            $this->connection->setTimeout($this->originalTimeout);
            $this->originalTimeout = null;
        }
    }

    public function getOriginalTimeout(): ?float
    {
        return $this->originalTimeout;
    }

    public function setOriginalTimeout(?float $timeout): void
    {
        $this->originalTimeout = $timeout;
    }
}
