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

use Laudis\Neo4j\Common\MonotonicClock;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\FakeTimeTickRequest;
use Laudis\Neo4j\TestkitBackend\Responses\FakeTimeAckResponse;

/**
 * @implements RequestHandlerInterface<FakeTimeTickRequest>
 */
final class FakeTimeTick implements RequestHandlerInterface
{
    /**
     * @param FakeTimeTickRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        MonotonicClock::tick($request->getIncrementMs());

        return new FakeTimeAckResponse();
    }
}
