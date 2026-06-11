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
use Laudis\Neo4j\Bolt\BoltUnmanagedTransaction;
use Laudis\Neo4j\Bolt\Session as BoltSession;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<RetryableNegativeRequest>
 */
final class RetryableNegative implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    /**
     * @param RetryableNegativeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $sessionId = $request->getSessionId();
        $transactionId = $this->repository->tryGetTsxIdFromSession($sessionId);
        if ($transactionId === null) {
            return new BackendErrorResponse('Transaction not found for session '.$sessionId->toRfc4122());
        }

        $tsx = $this->repository->getTransaction($transactionId);
        if (!$tsx instanceof UnmanagedTransactionInterface) {
            return new BackendErrorResponse('Transaction not found '.$transactionId->toRfc4122());
        }

        $exception = $this->resolveDriverException($request->getErrorId());
        if ($exception instanceof Neo4jException) {
            return $this->handleDriverException($exception, $sessionId, $transactionId, $tsx);
        }

        return $this->handleClientRollback($tsx, $transactionId);
    }

    private function handleDriverException(
        Neo4jException $exception,
        Uuid $sessionId,
        Uuid $transactionId,
        UnmanagedTransactionInterface $tsx,
    ): TestkitResponseInterface {
        if ($this->isConnectionError($exception)) {
            return $this->retryWithNewConnection($sessionId, $transactionId);
        }

        if ($exception->getClassification() === 'TransientError' && $tsx instanceof BoltUnmanagedTransaction) {
            $tsx->prepareForRetry();

            return new RetryableTryResponse($transactionId);
        }

        return new DriverErrorResponse($transactionId, $exception);
    }

    private function handleClientRollback(
        UnmanagedTransactionInterface $tsx,
        Uuid $transactionId,
    ): TestkitResponseInterface {
        if (!$tsx->isFinished()) {
            try {
                $tsx->rollback();
            } catch (Neo4jException $e) {
                return new DriverErrorResponse($transactionId, $e);
            }
        }

        return new FrontendErrorResponse('Client code caused transaction to be rolled back');
    }

    private function resolveDriverException(mixed $errorId): ?Neo4jException
    {
        if ($errorId === '' || $errorId === null) {
            return null;
        }

        if (!$errorId instanceof Uuid) {
            if (!Uuid::isValid((string) $errorId)) {
                return null;
            }
            $errorId = Uuid::fromString($errorId);
        }

        $errorResponse = $this->repository->tryGetRecords($errorId);
        if (!$errorResponse instanceof DriverErrorResponse) {
            return null;
        }

        $exception = $errorResponse->getException();

        return $exception instanceof Neo4jException ? $exception : null;
    }

    private function retryWithNewConnection(Uuid $sessionId, Uuid $transactionId): TestkitResponseInterface
    {
        $session = $this->repository->getSession($sessionId);
        if (!$session instanceof BoltSession) {
            return new BackendErrorResponse('Session does not support connection retry');
        }

        $config = $this->repository->getSessionRetryConfig($sessionId) ?? TransactionConfiguration::default();
        $session->resetConnectionPool();

        try {
            $newTransaction = $this->repository->isSessionRetryWrite($sessionId)
                ? $session->beginWriteTransaction($config)
                : $session->beginReadTransaction($config);
        } catch (Neo4jException $e) {
            return new DriverErrorResponse($transactionId, $e);
        }

        $this->repository->replaceTransaction($transactionId, $newTransaction);

        return new RetryableTryResponse($transactionId);
    }

    private function isConnectionError(Neo4jException $exception): bool
    {
        if ($exception->getNeo4jCode() === 'Neo.ClientError.General.ConnectionError') {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof BoltConnectException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'interrupted system call')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection timeout')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'i/o error');
    }
}
