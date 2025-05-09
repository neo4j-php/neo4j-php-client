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

final class TransactionRunRequest
{
    /**
     * @param iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>|null $params
     */
    public function __construct(
        private Uuid $txId,
        private string $cypher,
        private ?iterable $params = null,
        private ?float $timeout = null,
    ) {
    }

    public function getTxId(): Uuid
    {
        return $this->txId;
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

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
}
