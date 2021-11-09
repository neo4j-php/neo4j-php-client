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

namespace Laudis\Neo4j\Databags;

use function bin2hex;
use Exception;
use function random_bytes;
use function substr;

final class Bookmark
{
    /** @var list<string> */
    private array $bookmarks;

    /**
     * @param list<string> $bookmarks
     */
    public function __construct(?array $bookmarks = null)
    {
        $this->bookmarks = $bookmarks ?? [];
    }

    public function isEmpty(): bool
    {
        return count($this->bookmarks) === 0;
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->bookmarks;
    }

    /**
     * @throws Exception
     */
    public function withIncrement(?string $bookmark = null): self
    {
        $copy = $this->bookmarks;
        if ($bookmark === null) {
            $bookmark = $this->generateUuidV4();
        }
        $copy[] = $bookmark;

        return new self($copy);
    }

    /**
     * @throws Exception
     */
    private function generateUuidV4(): string
    {
        $uuid = random_bytes(16);
        $uuid[6] = ((int) $uuid[6]) & 0x0F | 0x40;
        $uuid[8] = ((int) $uuid[8]) & 0x3F | 0x80;
        $uuid = bin2hex($uuid);

        return substr($uuid, 0, 8).'-'
            .substr($uuid, 8, 4).'-'
            .substr($uuid, 12, 4).'-'
            .substr($uuid, 16, 4).'-'
            .substr($uuid, 20, 12);
    }
}
