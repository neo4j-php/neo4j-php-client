<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class RetryablePositiveRequest
{
    public function __construct(private Uuid $sessionId)
    {
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }
}
