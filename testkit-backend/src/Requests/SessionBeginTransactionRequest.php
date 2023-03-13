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

use Laudis\Neo4j\Databags\TransactionConfiguration;
use Symfony\Component\Uid\Uuid;

final class SessionBeginTransactionRequest
{
    /**
     * @param iterable<string, array|scalar|null>|null $txMeta
     */
    public function __construct(
        private Uuid $sessionId,
        private ?iterable $txMeta = null,
        private ?int $timeout = null
    ) {}

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    /**
     * @return iterable<string, array|scalar|null>
     */
    public function getTxMeta(): iterable
    {
        return $this->txMeta ?? [];
    }

    public function getTimeout(): int
    {
        return (int) ($this->timeout ?? TransactionConfiguration::DEFAULT_TIMEOUT);
    }
}
