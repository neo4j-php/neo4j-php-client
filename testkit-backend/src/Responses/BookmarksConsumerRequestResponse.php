<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitCallbackResponseInterface;

final class BookmarksConsumerRequestResponse implements TestkitCallbackResponseInterface
{
    /**
     * @param list<string> $bookmarks
     */
    public function __construct(
        private readonly string $id,
        private readonly string $bookmarkManagerId,
        private readonly array $bookmarks,
    ) {
    }

    public function getCallbackId(): string
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'BookmarksConsumerRequest',
            'data' => [
                'id' => $this->id,
                'bookmarkManagerId' => $this->bookmarkManagerId,
                'bookmarks' => $this->bookmarks,
            ],
        ];
    }
}
