<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;

/**
 * @implements RequestHandlerInterface<RetryableNegativeRequest>
 */
final class RetryableNegative implements RequestHandlerInterface
{
    /**
     * @param RetryableNegativeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        return new BackendErrorResponse('Retryable negative not implemented yet'); // TODO
    }
}
