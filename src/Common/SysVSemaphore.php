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

use function hash;

use Laudis\Neo4j\Contracts\SemaphoreInterface;

use function microtime;

use RuntimeException;

use function sem_acquire;
use function sem_get;
use function sem_release;

class SysVSemaphore implements SemaphoreInterface
{
    private function __construct(
        private readonly \SysvSemaphore $semaphore
    ) {}

    public static function create(string $key, int $max): self
    {
        $key = hash('sha512', $key, true);
        $key = substr($key, 0, 8);

        if (!function_exists('sem_get')) {
            throw new RuntimeException('Can only create a semaphore if the sysv extension is installed');
        }

        $semaphore = sem_get(hexdec($key), $max);
        if ($semaphore === false) {
            throw new RuntimeException('Could not create semaphore');
        }

        return new self($semaphore);
    }

    public function wait(): Generator
    {
        $start = microtime(true);
        while (!sem_acquire($this->semaphore, true)) {
            /** @var bool $continue */
            $continue = yield $start - microtime(true);
            if (!$continue) {
                return;
            }
        }
    }

    public function post(): void
    {
        if (!sem_release($this->semaphore)) {
            throw new RuntimeException('Cannot release semaphore');
        }
    }
}
