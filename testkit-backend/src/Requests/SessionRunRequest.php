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
    private iterable $params;
    /** @var mixed */
    private $txMeta;
    private ?int $timeout;

    public function __construct(Uuid $sessionId, string $cypher, iterable $params, array $txMeta, ?int $timeout)
    {
        $this->sessionId = $sessionId;
        $this->cypher = $cypher;
        $this->params = $params;
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

    public function getParams(): iterable
    {
        return $this->params;
    }

    public function getTxMeta(): array
    {
        return $this->txMeta;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }
}
