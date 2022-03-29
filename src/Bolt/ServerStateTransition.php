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

namespace Laudis\Neo4j\Bolt;

/**
 * @psalm-immutable
 */
final class ServerStateTransition
{
    public function __construct(
        private string $originalState,
        private string $message,
        private ?string $triggersSignal,
        private ?string $serverResponse,
        private ?string $newState
    ) {
    }

    public function getOriginalState(): string
    {
        return $this->originalState;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTriggersSignal(): ?string
    {
        return $this->triggersSignal;
    }

    public function getServerResponse(): ?string
    {
        return $this->serverResponse;
    }

    public function getNewState(): ?string
    {
        return $this->newState;
    }

    /**
     * @param array{0: string, 1: string, 2: string|null, 3: string|null, 4: string|null} $params
     *
     * @pure
     */
    public static function fromArray(array $params): self
    {
        return new self($params[0], $params[1], $params[2], $params[3], $params[4]);
    }
}
