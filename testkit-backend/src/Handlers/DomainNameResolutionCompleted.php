<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\DomainNameResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;

/**
 * @implements RequestHandlerInterface<DomainNameResolutionCompletedRequest>
 */
final class DomainNameResolutionCompleted implements RequestHandlerInterface
{
    /**
     * @param DomainNameResolutionCompletedRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        return new BackendErrorResponse('Domain name resolution not implemented yet'); // TODO
    }
}
