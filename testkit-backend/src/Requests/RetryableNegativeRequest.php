<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class RetryableNegativeRequest
{
    private Uuid $sessionId;
    private Uuid $errorId;

    public function __construct(Uuid $sessionId, Uuid $errorId)
    {
        $this->sessionId = $sessionId;
        $this->errorId = $errorId;
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }

    public function getErrorId(): Uuid
    {
        return $this->errorId;
    }
}
