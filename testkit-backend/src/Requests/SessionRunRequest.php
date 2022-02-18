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

use Symfony\Component\Uid\Uuid;

final class SessionRunRequest
{
    private Uuid $sessionId;
    private string $cypher;
    /** @var iterable<string, array{name: string, data: array{value: iterable|scalar|null}}> */
    private iterable $params;
    /** @var iterable<string, mixed>|null */
    private ?iterable $txMeta;
    private ?int $timeout;

    /**
     * @param iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>|null $params
     * @param iterable<string, mixed>|null                                  $txMeta
     */
    public function __construct(Uuid $sessionId, string $cypher, ?iterable $params, ?iterable $txMeta, ?int $timeout)
    {
        $this->sessionId = $sessionId;
        $this->cypher = $cypher;
        $this->params = $params ?? [];
        $this->txMeta = $txMeta;
        $this->timeout = $timeout;
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
        return $this->params;
    }

    /**
     * @return iterable<string, mixed>|null
     */
    public function getTxMeta(): ?iterable
    {
        return $this->txMeta;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }
}
