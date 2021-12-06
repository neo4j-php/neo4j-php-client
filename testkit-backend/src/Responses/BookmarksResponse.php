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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents an array of bookmarks.
 */
final class BookmarksResponse implements TestkitResponseInterface
{
    /**
     * @var iterable<string>
     */
    private iterable $bookmarks;

    /**
     * @param iterable<string> $bookmarks
     */
    public function __construct(iterable $bookmarks)
    {
        $this->bookmarks = $bookmarks;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'Bookmarks',
            'data' => [
                'bookmarks' => $this->bookmarks,
            ],
        ];
    }
}
