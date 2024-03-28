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

namespace Laudis\Neo4j\Common;

use Generator;
use Laudis\Neo4j\Contracts\SemaphoreInterface;

use function microtime;

use RuntimeException;

class SingleThreadedSemaphore implements SemaphoreInterface
{
    private int $amount = 0;
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        private readonly int $max
    ) {}

    public static function create(string $key, int $max): self
    {
        if (!array_key_exists($key, self::$instances)) {
            self::$instances[$key] = new self($max);
        }

        return self::$instances[$key];
    }

    public function wait(): Generator
    {
        $start = microtime(true);
        while ($this->amount >= $this->max) {
            /** @var bool $continue */
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
            throw new RuntimeException('Semaphore underflow');
        }
        --$this->amount;
    }
}
