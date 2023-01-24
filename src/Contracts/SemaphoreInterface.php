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

namespace Laudis\Neo4j\Contracts;

use Generator;

interface SemaphoreInterface
{
    /**
     * Returns a generator that can be used to wait for the semaphore to be released.
     *
     * The key of the generator is the amount of times you have already received a value before this one.
     * The value of the generator is the amount of time in seconds that has passed since calling wait.
     * You can stop the wait by sending false to the generator.
     * There is no return value in this generator.
     *
     * @return Generator<int, float, bool>
     */
    public function wait(): Generator;

    /**
     * Releases the semaphore.
     */
    public function post(): void;
}
