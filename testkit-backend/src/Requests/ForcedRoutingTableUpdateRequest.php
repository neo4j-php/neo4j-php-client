<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class ForcedRoutingTableUpdateRequest
{
    /**
     * @param iterable<string> $bookmarks
     */
    public function __construct(private Uuid $driverId, private ?string $database, private ?iterable $bookmarks)
    {
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    /**
     * @return iterable<string>
     */
    public function getBookmarks(): ?iterable
    {
        return $this->bookmarks;
    }
}
