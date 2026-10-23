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

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitCallbackResultInterface;
use RuntimeException;

/**
 * Tracks in-flight TestKit callback round-trips between backend and frontend.
 */
final class CallbackRegistry
{
    /** @var array<string, true> */
    private array $pending = [];

    /** @var array<string, TestkitCallbackResultInterface> */
    private array $completed = [];

    public function registerPending(string $callbackId): void
    {
        $this->pending[$callbackId] = true;
    }

    public function complete(string $callbackId, TestkitCallbackResultInterface $result): void
    {
        if (!array_key_exists($callbackId, $this->pending)) {
            throw new RuntimeException('No pending callback for id: '.$callbackId);
        }

        unset($this->pending[$callbackId]);
        $this->completed[$callbackId] = $result;
    }

    public function hasCompleted(string $callbackId): bool
    {
        return array_key_exists($callbackId, $this->completed);
    }

    public function takeCompleted(string $callbackId): TestkitCallbackResultInterface
    {
        if (!array_key_exists($callbackId, $this->completed)) {
            throw new RuntimeException('Callback not completed for id: '.$callbackId);
        }

        $result = $this->completed[$callbackId];
        unset($this->completed[$callbackId]);

        return $result;
    }
}
