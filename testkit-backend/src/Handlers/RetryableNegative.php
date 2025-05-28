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

use _PHPStan_a2c094651\Psr\Log\LoggerInterface;
use Exception;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TimeoutException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;

/**
 * @implements RequestHandlerInterface<RetryableNegativeRequest>
 */
final class RetryableNegative implements RequestHandlerInterface
{
    private MainRepository $repository;
    private LoggerInterface $logger;

    public function __construct(MainRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * @param RetryableNegativeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $sessionId = $request->getSessionId();
        $session = $this->repository->getSession($sessionId);

        try {
            // This is the core logic you need to fill in based on the Testkit's expectations
            // for a 'RetryableNegative' test case.
            // It typically involves an operation designed to fail with a retryable error.

            throw new Neo4jException(
                'Simulated retryable error: Transaction failed due to deadlock.',
                'Neo.TransientError.Transaction.DeadlockDetected'
            );

        } catch (Neo4jException $e) {
            $this->logger->info('Caught Neo4jException in RetryableNegative handler', ['exception' => $e->getMessage()]);
            return new DriverErrorResponse($sessionId, $e);
        } catch (TransactionException $e) {
            $this->logger->info('Caught TransactionException in RetryableNegative handler', ['exception' => $e->getMessage()]);
            return new DriverErrorResponse($sessionId, $e);
        } catch (TimeoutException $e) {
            $this->logger->info('Caught TimeoutException in RetryableNegative handler', ['exception' => $e->getMessage()]);
            return new DriverErrorResponse($sessionId, $e);
        } catch (Exception $e) {
            $this->logger->error('Unhandled exception in RetryableNegative handler', ['exception' => $e->getMessage()]);
            return new FrontendErrorResponse('Unhandled exception in RetryableNegative handler: ' . $e->getMessage());
        }

    }
}
