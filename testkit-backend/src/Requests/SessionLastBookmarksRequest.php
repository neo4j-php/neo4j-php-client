<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class SessionLastBookmarksRequest
{
    private Uuid $sessionId;

    public function __construct(Uuid $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId(): Uuid
    {
        return $this->sessionId;
    }
}
