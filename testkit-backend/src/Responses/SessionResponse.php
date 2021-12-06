<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Responses;


use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Represents a session instance on the backend.
 */
final class SessionResponse implements TestkitResponseInterface
{
    private Uuid $id;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'Session',
            'data' => [
                'id' => $this->id->toRfc4122(),
            ],
        ];
    }
}
