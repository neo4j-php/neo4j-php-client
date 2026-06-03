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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Bolt\error\ConnectException as BoltConnectException;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Bolt\BoltUnmanagedTransaction;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\Bolt\Session as BoltSession;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\BoltTelemetryApi;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use ReflectionProperty;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * TestKit retryable transactions reuse the same transaction id; replace the underlying managed tx on retry.
 */
final class RetryableManagedTransactionSupport
{
    private const MAX_RETRIES = 3;

    /** @var array<string, int> */
    private array $retryAttempts = [];

    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    public function clearRetryAttempts(Uuid $sessionId): void
    {
        unset($this->retryAttempts[$sessionId->toRfc4122()]);
    }

    public function isRetryableException(Throwable $exception): bool
    {
        if ($exception instanceof BoltConnectException) {
            return true;
        }

        if (!$exception instanceof Neo4jException) {
            return false;
        }

        if ($exception->getClassification() === 'TransientError') {
            return true;
        }

        $code = $exception->getNeo4jCode();
        if ($code === 'Neo.ClientError.General.ConnectionError') {
            return true;
        }

        $message = strtolower($exception->getNeo4jMessage() ?? $exception->getMessage());
        if (str_contains($message, 'connection')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection closed')) {
            return true;
        }

        $previous = $exception->getPrevious();

        return $previous !== null && $this->isRetryableException($previous);
    }

    public function retryManagedTransaction(Uuid $sessionId, Uuid $transactionId, ?DriverErrorResponse $lastError = null): TestkitResponseInterface
    {
        $sessionKey = $sessionId->toRfc4122();
        $this->retryAttempts[$sessionKey] = ($this->retryAttempts[$sessionKey] ?? 0) + 1;

        if ($this->retryAttempts[$sessionKey] > self::MAX_RETRIES) {
            unset($this->retryAttempts[$sessionKey]);
            if ($lastError !== null) {
                return new DriverErrorResponse($transactionId, $lastError->getException());
            }

            return new BackendErrorResponse('Maximum managed transaction retries exceeded');
        }

        try {
            $session = $this->repository->getSession($sessionId);
        } catch (Throwable $e) {
            return new BackendErrorResponse('Session not found for retry '.$sessionId->toRfc4122());
        }

        if (!$session instanceof BoltSession) {
            return new BackendErrorResponse('Session does not support managed transaction retry');
        }

        $this->repository->removeRecords($transactionId);
        $exception = $lastError?->getException();
        $oldTransaction = $this->repository->getTransaction($transactionId);

        if ($exception instanceof Neo4jException
            && $exception->getClassification() === 'TransientError'
            && $oldTransaction instanceof BoltUnmanagedTransaction) {
            return $this->retryTransientOnSameConnection($session, $transactionId, $oldTransaction);
        }

        $this->invalidateSessionConnections($session);

        try {
            $transaction = $session->openManagedTransaction(TransactionConfiguration::default());
            $this->repository->addTransaction($transactionId, $transaction);
        } catch (Neo4jException $e) {
            return new DriverErrorResponse($transactionId, $e);
        }

        return new RetryableTryResponse($transactionId);
    }

    private function retryTransientOnSameConnection(
        BoltSession $session,
        Uuid $transactionId,
        BoltUnmanagedTransaction $oldTransaction,
    ): TestkitResponseInterface {
        $connection = $this->getTransactionConnection($oldTransaction);

        try {
            $connection->reset();
        } catch (Throwable) {
        }

        try {
            $transaction = $this->createManagedTransactionOnConnection($session, $connection);
            $this->repository->addTransaction($transactionId, $transaction);
        } catch (Neo4jException $e) {
            return new DriverErrorResponse($transactionId, $e);
        }

        return new RetryableTryResponse($transactionId);
    }

    private function createManagedTransactionOnConnection(BoltSession $session, BoltConnection $connection): BoltUnmanagedTransaction
    {
        $sessionConfig = $this->getSessionConfiguration($session);
        $formatter = $this->getSessionFormatter($session);
        $pool = $this->getSessionPool($session);
        $bookmarkHolder = $this->getSessionBookmarkHolder($session);

        return new BoltUnmanagedTransaction(
            $sessionConfig->getDatabase(),
            $formatter,
            $connection,
            $sessionConfig,
            TransactionConfiguration::default(),
            $bookmarkHolder,
            new BoltMessageFactory($connection, $pool->getLogger()),
            false,
            $pool,
            false,
            BoltTelemetryApi::MANAGED_TRANSACTION,
        );
    }

    private function getTransactionConnection(BoltUnmanagedTransaction $transaction): BoltConnection
    {
        $property = new ReflectionProperty(BoltUnmanagedTransaction::class, 'connection');
        $property->setAccessible(true);

        /** @var BoltConnection */
        return $property->getValue($transaction);
    }

    private function invalidateSessionConnections(BoltSession $session): void
    {
        $pool = $this->getSessionPool($session);
        $sessionConfig = $this->getSessionConfiguration($session);

        if ($pool instanceof Neo4jConnectionPool) {
            $pool->clearRoutingTable($sessionConfig);
        }

        $pool->close();
    }

    private function getSessionPool(BoltSession $session): ConnectionPool|Neo4jConnectionPool
    {
        $property = new ReflectionProperty(BoltSession::class, 'pool');
        $property->setAccessible(true);

        /** @var ConnectionPool|Neo4jConnectionPool */
        return $property->getValue($session);
    }

    private function getSessionConfiguration(BoltSession $session): SessionConfiguration
    {
        $property = new ReflectionProperty(BoltSession::class, 'config');
        $property->setAccessible(true);

        /** @var SessionConfiguration */
        return $property->getValue($session);
    }

    private function getSessionFormatter(BoltSession $session): SummarizedResultFormatter
    {
        $property = new ReflectionProperty(BoltSession::class, 'formatter');
        $property->setAccessible(true);

        /** @var SummarizedResultFormatter */
        return $property->getValue($session);
    }

    private function getSessionBookmarkHolder(BoltSession $session): BookmarkHolder
    {
        $property = new ReflectionProperty(BoltSession::class, 'bookmarkHolder');
        $property->setAccessible(true);

        /** @var BookmarkHolder */
        return $property->getValue($session);
    }
}
