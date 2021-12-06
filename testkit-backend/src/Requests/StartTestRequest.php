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

namespace Laudis\Neo4j\TestkitBackend\Requests;

final class StartTestRequest
{
    private string $testName;

    public function __construct(string $testName)
    {
        $this->testName = $testName;
    }

    public function getTestName(): string
    {
        return $this->testName;
    }
}
