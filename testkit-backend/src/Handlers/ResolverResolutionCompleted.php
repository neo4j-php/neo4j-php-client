<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\ResolverResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;

/**
 * @implements RequestHandlerInterface<ResolverResolutionCompletedRequest>
 */
final class ResolverResolutionCompleted implements RequestHandlerInterface
{
    /**
     * @param ResolverResolutionCompletedRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        return new BackendErrorResponse('Resolver resolution not implemented yet'); // TODO
    }
}
