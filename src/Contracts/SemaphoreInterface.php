<?php

namespace Laudis\Neo4j\Contracts;

use Generator;

interface SemaphoreInterface
{
    /**
     * Returns a generator that can be used to wait for the semaphore to be released.
     *
     * @return Generator<int, void>
     */
    public function wait(): Generator;

    /**
     * Releases the semaphore.
     */
    public function post(): void;
}
