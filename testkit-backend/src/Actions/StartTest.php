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

namespace Laudis\Neo4j\TestkitBackend\Actions;

use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;

final class StartTest implements ActionInterface
{
    private array $acceptedTests;

    public function __construct(array $acceptedTests)
    {
        $this->acceptedTests = $acceptedTests;
    }

    public function handle(array $data): array
    {
        $section = $this->acceptedTests;
        foreach (explode('.', $data['testName'] ?? '') as $key) {
            if (!isset($section[$key])) {
                return ['name' => 'SkipTest', 'data' => ['reason' => 'Test not registered in backend']];
            }
            if ($section[$key] === false) {
                return ['name' => 'SkipTest', 'data' => ['reason' => 'Test disabled in backend']];
            }
            $section = $section[$key];
        }

        return ['name' => 'RunTest'];
    }
}
