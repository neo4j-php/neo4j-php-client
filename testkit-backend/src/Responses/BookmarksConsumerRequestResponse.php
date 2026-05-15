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

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Symfony\Component\Uid\Uuid;

final class BookmarksConsumerRequestResponse implements TestkitResponseInterface
{
    /**
     * @param list<string> $bookmarks
     */
    public function __construct(
        private readonly Uuid $requestId,
        private readonly Uuid $bookmarkManagerId,
        private readonly array $bookmarks,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'BookmarksConsumerRequest',
            'data' => [
                'id' => $this->requestId->toRfc4122(),
                'bookmarkManagerId' => $this->bookmarkManagerId->toRfc4122(),
                'bookmarks' => $this->bookmarks,
            ],
        ];
    }
}
