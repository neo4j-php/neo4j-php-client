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

use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\TelemetryAPI;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\DriverExceptionHelper;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @implements RequestHandlerInterface<RetryableNegativeRequest>
 */
final class RetryableNegative implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param RetryableNegativeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $sessionId = $request->getSessionId();

        try {
            $transactionId = $this->repository->getTsxIdFromSession($sessionId);
        } catch (Throwable $e) {
            return new BackendErrorResponse('Transaction not found for session '.$sessionId->toRfc4122());
        }

        $tsx = $this->repository->getTransaction($transactionId);

        if (!$tsx instanceof UnmanagedTransactionInterface) {
            return new BackendErrorResponse('Transaction not found '.$transactionId->toRfc4122());
        }

        try {
            $tsx->rollback();
        } catch (Throwable $e) {
            // Best-effort rollback: connection may already be broken. Proceed with error response.
            $this->logger->debug('Rollback failed during RetryableNegative', ['exception' => $e->getMessage()]);
        }

        $errorId = $request->getErrorId();
        if ($errorId !== '' && $errorId !== null) {
            try {
                $errorUuid = $errorId instanceof Uuid ? $errorId : Uuid::fromString($errorId);
                $errorResponse = $this->getDriverErrorResponse($errorUuid, $transactionId);

                if ($errorResponse instanceof DriverErrorResponse) {
                    $exception = $errorResponse->getException();
                    if ($exception instanceof Neo4jException && DriverExceptionHelper::shouldRetryManagedTransaction($exception)) {
                        if (DriverExceptionHelper::isConnectionNeo4jException($exception)) {
                            $this->dropLastUsedBoltConnection($sessionId);
                        } else {
                            $this->resetSessionBoltConnection($sessionId);
                        }

                        return $this->retryTransaction($sessionId);
                    }

                    // Otherwise, return the original error to the frontend
                    return new DriverErrorResponse($transactionId, $exception);
                }
            } catch (Throwable $e) {
                // Invalid errorId or record not found - fall through to generic FrontendError
                $this->logger->debug('Could not retrieve error for RetryableNegative', ['exception' => $e->getMessage()]);
            }
        }

        // If no specific error was provided or couldn't be retrieved,
        // client code caused the rollback (e.g. ApplicationCodeError) - return FrontendError
        return new FrontendErrorResponse('Client code caused transaction to be rolled back');
    }

    private function getDriverErrorResponse(Uuid $errorId, Uuid $transactionId): ?DriverErrorResponse
    {
        $stored = $this->repository->getRecords($errorId);
        if ($stored instanceof DriverErrorResponse) {
            return $stored;
        }

        if (!$errorId->equals($transactionId)) {
            $fallback = $this->repository->getRecords($transactionId);
            if ($fallback instanceof DriverErrorResponse) {
                return $fallback;
            }
        }

        return null;
    }

    private function dropLastUsedBoltConnection(Uuid $sessionId): void
    {
        $session = $this->repository->getSession($sessionId);
        if ($session instanceof Session) {
            $session->dropLastUsedBoltConnection();
        }
    }

    private function resetSessionBoltConnection(Uuid $sessionId): void
    {
        $session = $this->repository->getSession($sessionId);
        if ($session instanceof Session) {
            $session->resetLastUsedBoltConnection();
        }
    }

    private function retryTransaction(Uuid $sessionId): RetryableTryResponse
    {
        $session = $this->repository->getSession($sessionId);
        $config = TransactionConfiguration::default();

        $newId = Uuid::v4();
        $transaction = $session instanceof Session
            ? $session->beginTransaction(null, $config, TelemetryAPI::TX_FUNC)
            : $session->beginTransaction(null, $config);

        $this->repository->addTransaction($newId, $transaction);
        $this->repository->bindTransactionToSession($sessionId, $newId);

        return new RetryableTryResponse($newId);
    }
}
