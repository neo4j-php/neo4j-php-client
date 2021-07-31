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

namespace Laudis\Neo4j\TestkitBackend\Input;

use Symfony\Component\Uid\Uuid;

final class NewSessionInput
{
    private Uuid $driverId;
    private string $accessMode;
    private ?iterable $bookmarks;
    private ?string $database;
    private ?int $fetchSize;

    public function __construct(
        Uuid $driverId,
        string $accessMode,
        ?iterable $bookmarks,
        ?string $database,
        ?int $fetchSize
    ) {
        $this->driverId = $driverId;
        $this->accessMode = $accessMode;
        $this->bookmarks = $bookmarks;
        $this->database = $database;
        $this->fetchSize = $fetchSize;
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getAccessMode(): string
    {
        return $this->accessMode;
    }

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
