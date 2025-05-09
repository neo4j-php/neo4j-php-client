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

use function is_string;

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\StartTestRequest;
use Laudis\Neo4j\TestkitBackend\Responses\RunTestResponse;
use Laudis\Neo4j\TestkitBackend\Responses\SkipTestResponse;

/**
 * @implements RequestHandlerInterface<StartTestRequest>
 */
final class StartTest implements RequestHandlerInterface
{
    /** @var array<string, array|string|bool> */
    private array $acceptedTests;

    /**
     * @param array<string, array|string|bool> $acceptedTests
     */
    public function __construct(array $acceptedTests)
    {
        $this->acceptedTests = $acceptedTests;
    }

    /**
     * @param StartTestRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $section = $this->acceptedTests;
        foreach (explode('.', $request->getTestName()) as $key) {
            if (array_key_exists($key, $section)) {
                if ($section[$key] === false) {
                    return new SkipTestResponse('Test disabled in backend');
                }
                if (is_string($section[$key])) {
                    return new SkipTestResponse($section[$key]);
                }
                /** @var array<string, array|string|bool> $section */
                $section = $section[$key];
            } else {
                break;
            }
        }

        return new RunTestResponse();
    }
}
