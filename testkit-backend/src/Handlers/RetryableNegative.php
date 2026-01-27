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

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;

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
        // RetryableNegative tests negative scenarios with retryable errors
        // This is used to test that the driver properly handles retryable exceptions
        // For now, we return a frontend error to indicate this test scenario is not supported
        // In practice, this would need to throw a retryable Neo4j error
        return new FrontendErrorResponse('Retryable negative test scenarios not yet implemented');
    }
}
