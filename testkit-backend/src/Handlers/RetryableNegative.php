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
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @implements RequestHandlerInterface<RetryableNegativeRequest>
 */
final class RetryableNegative implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
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
            // Attempt to rollback, but proceed with error handling even if rollback fails
        }

        $errorId = $request->getErrorId();
        if ($errorId !== '' && $errorId !== null) {
            try {
                $errorUuid = $errorId instanceof Uuid ? $errorId : Uuid::fromString($errorId);
                $errorResponse = $this->repository->getRecords($errorUuid);

                if ($errorResponse instanceof DriverErrorResponse) {
                    $exception = $errorResponse->getException();
                    if ($exception instanceof Neo4jException && $exception->getClassification() === 'TransientError') {
                        // If the original error was retryable, signal for retry
                        return new RetryableTryResponse($transactionId);
                    }
                    // Otherwise, return the original error to the frontend
                    return new DriverErrorResponse($transactionId, $exception);
                }
            } catch (Throwable $e) {
                // If we can't get the error, fall through to generic error
            }
        }

        // If no specific error was provided or couldn't be retrieved,
        // client code caused the rollback (e.g. ApplicationCodeError) - return FrontendError
        return new FrontendErrorResponse('Client code caused transaction to be rolled back');
    }
}
