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

namespace Laudis\Neo4j\TestkitBackend\Requests;

use Symfony\Component\Uid\Uuid;

final class SessionRunRequest
{
    /**
     * @param iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>|null $params
     * @param iterable<string, scalar|iterable|null>|null                                          $txMeta
     */
    public function __construct(
        private Uuid $sessionId,
        private string $cypher,
        private ?iterable $params = null,
        private ?iterable $txMeta = null,
        private ?float $timeout = null,
    ) {
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    public function getCypher(): string
    {
        return $this->cypher;
    }

    /**
     * @return iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>
     */
    public function getParams(): iterable
    {
        return $this->params ?? [];
    }

    /**
     * @return iterable<string, scalar|iterable|null>|null
     */
    public function getTxMeta(): ?iterable
    {
        return $this->txMeta;
    }

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
}
