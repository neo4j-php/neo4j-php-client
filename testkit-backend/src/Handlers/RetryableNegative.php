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

use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
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

        $errorId = $request->getErrorId();
        $resolvedException = null;
        if ($errorId !== '' && $errorId !== null) {
            try {
                $errorUuid = $errorId instanceof Uuid ? $errorId : Uuid::fromString($errorId);
                $resolvedException = $this->repository->takePendingDriverError($errorUuid);
                if ($resolvedException === null) {
                    $errorResponse = $this->repository->getRecords($errorUuid);
                    if ($errorResponse instanceof DriverErrorResponse) {
                        $resolvedException = $errorResponse->getException();
                    }
                }
            } catch (Throwable $e) {
                $this->logger->debug('Could not retrieve error for RetryableNegative', ['exception' => $e->getMessage()]);
            }
        }

        $transientRetry = $resolvedException instanceof Neo4jException
            && $resolvedException->getClassification() === 'TransientError';

        // Managed tx retry ({@see Session::executeRead}): same {@see BoltUnmanagedTransaction} is reused. Calling
        // rollback() when the server is already READY (e.g. after PULL FAILURE + RESET) marks the client
        // ROLLED_BACK and the next attempt fails with "Can't run a query on a rolled back transaction."
        // Skip rollback for transient errors so {@see BoltUnmanagedTransaction::ensureBeginSent} can issue BEGIN again.
        if (!$transientRetry) {
            try {
                $tsx->rollback();
            } catch (Throwable $e) {
                $this->logger->debug('Rollback failed during RetryableNegative', ['exception' => $e->getMessage()]);
            }
        }

        if ($resolvedException instanceof Neo4jException) {
            if ($transientRetry) {
                return new RetryableTryResponse($transactionId);
            }

            return new DriverErrorResponse($transactionId, $resolvedException);
        }

        if ($resolvedException !== null) {
            return new DriverErrorResponse($transactionId, $resolvedException);
        }

        // If no specific error was provided or couldn't be retrieved,
        // client code caused the rollback (e.g. ApplicationCodeError) - return FrontendError
        return new FrontendErrorResponse('Client code caused transaction to be rolled back');
    }
}
