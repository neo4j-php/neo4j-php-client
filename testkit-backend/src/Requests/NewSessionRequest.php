<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Requests;

use Laudis\Neo4j\Databags\Bookmark;
use Symfony\Component\Uid\Uuid;

final class NewSessionRequest
{
    /**
     * @param iterable<Bookmark>|null $bookmarks
     */
    public function __construct(private Uuid $driverId, private string $accessMode, private ?iterable $bookmarks, private ?string $database, private ?int $fetchSize)
    {
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getAccessMode(): string
    {
        return $this->accessMode;
    }

    /**
     * @return iterable<Bookmark>|null
     */
    public function getBookmarks(): ?iterable
    {
        return $this->bookmarks;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function getFetchSize(): ?int
    {
        return $this->fetchSize;
    }
}
