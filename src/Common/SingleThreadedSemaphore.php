<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Common;

use Generator;
use Laudis\Neo4j\Contracts\SemaphoreInterface;

use function microtime;

class SingleThreadedSemaphore implements SemaphoreInterface
{
    private int $max;
    private int $amount = 0;
    private static array $instances = [];

    private function __construct(int $max)
    {
        $this->max = $max;
    }

    public static function create(string $key, int $max): self
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($max);
        }

        return self::$instances[$key];
    }

    public function wait(): Generator
    {
        $start = microtime(true);
        while ($this->amount >= $this->max) {
            $continue = yield $start - microtime(true);
            if (!$continue) {
                return;
            }
        }
        ++$this->amount;
    }

    public function post(): void
    {
        if ($this->amount <= 0) {
            throw new \RuntimeException('Semaphore underflow');
        }
        --$this->amount;
    }
}
