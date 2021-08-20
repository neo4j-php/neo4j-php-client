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

final class SessionWriteTransactionRequest
{
    private Uuid $sessionId;
    /** @var iterable<string, array|scalar|null> */
    private iterable $txMeta;
    private ?int $timeout;

    /**
     * @param iterable<string, array|scalar|null>|null $txMeta
     */
    public function __construct(
        Uuid $sessionId,
        ?iterable $txMeta = null,
        ?int $timeout = null
    ) {
        $this->sessionId = $sessionId;
        $this->txMeta = $txMeta ?? [];
        $this->timeout = $timeout;
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    /**
     * @return iterable<string, array|scalar|null>
     */
    public function getTxMeta(): iterable
    {
        return $this->txMeta;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }
}
