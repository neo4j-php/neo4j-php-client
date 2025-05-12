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

final class RetryableNegativeRequest
{
    private Uuid $sessionId;
    /** @var Uuid|string */
    private $errorId;

    /**
     * @param Uuid|string $errorId
     */
    public function __construct(Uuid $sessionId, $errorId)
    {
        $this->sessionId = $sessionId;
        $this->errorId = $errorId;
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    /**
     * @return Uuid|string
     */
    public function getErrorId()
    {
        return $this->errorId;
    }
}
