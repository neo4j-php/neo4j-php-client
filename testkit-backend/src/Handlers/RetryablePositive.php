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
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\RetryablePositiveRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableDoneResponse;

/**
 * @implements RequestHandlerInterface<RetryablePositiveRequest>
 */
final class RetryablePositive implements RequestHandlerInterface
{
    /**
     * @param RetryablePositiveRequest $request
     */

    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }
    public function handle($request): TestkitResponseInterface
    {
        $sessionId = $request->getSessionId();

        try {
            $transactionId = $this->repository->getTsxIdFromSession($sessionId);
        } catch (\Throwable $e) {
            return new BackendErrorResponse('Transaction not found for session ' . $sessionId->toRfc4122());
        }

        $tsx = $this->repository->getTransaction($transactionId);

        if (!$tsx instanceof UnmanagedTransactionInterface) {
            return new BackendErrorResponse('Transaction not found ' . $transactionId->toRfc4122());
        }

        try {
            $tsx->commit();
        } catch (Neo4jException | TransactionException $e) {
            return new DriverErrorResponse($transactionId, $e);
        }

        return new RetryableDoneResponse();
    }
}
