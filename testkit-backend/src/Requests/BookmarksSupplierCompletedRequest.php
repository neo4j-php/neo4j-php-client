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

namespace Laudis\Neo4j\TestkitBackend\Requests;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitCallbackResultInterface;

final class BookmarksSupplierCompletedRequest implements TestkitCallbackResultInterface
{
    /**
     * @param list<string> $bookmarks
     */
    public function __construct(
        private readonly string $requestId,
        private readonly array $bookmarks,
    ) {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * @return list<string>
     */
    public function getBookmarks(): array
    {
        return $this->bookmarks;
    }
}
