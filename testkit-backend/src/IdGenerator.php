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

namespace Laudis\Neo4j\TestkitBackend;

/**
 * Generates monotonically increasing string identifiers for TestKit objects and callbacks.
 *
 * Matches the Java TestkitState#newId() behaviour.
 */
final class IdGenerator
{
    private int $next = 0;

    public function newId(): string
    {
        return (string) $this->next++;
    }
}
