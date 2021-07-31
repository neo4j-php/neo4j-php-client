<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Input;


use Symfony\Component\Uid\Uuid;

final class SessionCloseInput
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
