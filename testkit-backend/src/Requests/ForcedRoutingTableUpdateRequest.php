<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class ForcedRoutingTableUpdateRequest
{
    private Uuid $driverId;
    private ?string $database;
    /** @var iterable<string> */
    private ?iterable $bookmarks;

    /**
     * @param iterable<string> $bookmarks
     */
    public function __construct(Uuid $driverId, ?string $database, ?iterable $bookmarks)
    {
        $this->driverId = $driverId;
        $this->database = $database;
        $this->bookmarks = $bookmarks;
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
