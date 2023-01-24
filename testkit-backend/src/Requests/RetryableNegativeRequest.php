<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class RetryableNegativeRequest
{
    /**
     * @param Uuid|string $errorId
     */
    public function __construct(private Uuid $sessionId, private $errorId)
    {
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    public function getErrorId(): \Symfony\Component\Uid\Uuid|string
    {
        return $this->errorId;
    }
}
