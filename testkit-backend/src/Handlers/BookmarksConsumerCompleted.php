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

use Laudis\Neo4j\TestkitBackend\CallbackRegistry;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksConsumerCompletedRequest;

/**
 * @implements RequestHandlerInterface<BookmarksConsumerCompletedRequest>
 */
final class BookmarksConsumerCompleted implements RequestHandlerInterface
{
    public function __construct(
        private readonly CallbackRegistry $callbackRegistry,
    ) {
    }

    /**
     * @param BookmarksConsumerCompletedRequest $request
     */
    public function handle($request): ?TestkitResponseInterface
    {
        $this->callbackRegistry->complete($request->getRequestId(), $request);

        return null;
    }
}
