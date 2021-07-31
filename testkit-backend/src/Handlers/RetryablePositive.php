<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\RetryablePositiveRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;

/**
 * @implements RequestHandlerInterface<RetryablePositiveRequest>
 */
final class RetryablePositive implements RequestHandlerInterface
{
    /**
     * @param RetryablePositiveRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        return new BackendErrorResponse('Retryable positive not implemented yet'); // TODO
    }
}
