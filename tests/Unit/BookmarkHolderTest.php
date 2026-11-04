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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use PHPUnit\Framework\TestCase;

final class BookmarkHolderTest extends TestCase
{
    public function testStoresAndReturnsBookmarkWhenEnabled(): void
    {
        $initial = new Bookmark(['neo4j:bookmark:v1:tx1']);
        $holder = new BookmarkHolder($initial);

        self::assertTrue($holder->areBookmarksEnabled());
        self::assertSame($initial, $holder->getBookmark());

        $updated = new Bookmark(['neo4j:bookmark:v1:tx2']);
        $holder->setBookmark($updated);

        self::assertSame($updated, $holder->getBookmark());
    }

    public function testDisabledIgnoresInitialAndUpdatedBookmarks(): void
    {
        $initial = new Bookmark(['neo4j:bookmark:v1:tx1']);
        $holder = new BookmarkHolder($initial, false);

        self::assertFalse($holder->areBookmarksEnabled());
        self::assertTrue($holder->getBookmark()->isEmpty());

        $holder->setBookmark(new Bookmark(['neo4j:bookmark:v1:tx2']));

        self::assertTrue($holder->getBookmark()->isEmpty());
    }

    public function testSetBookmarkFromServerStoresBookmarkWithoutListener(): void
    {
        $holder = new BookmarkHolder(new Bookmark());
        $fromServer = new Bookmark(['neo4j:bookmark:v1:tx2']);

        $holder->setBookmarkFromServer($fromServer);

        self::assertSame($fromServer, $holder->getBookmark());
    }

    public function testSetBookmarkFromServerInvokesListener(): void
    {
        $holder = new BookmarkHolder(new Bookmark());
        $received = null;
        $holder->onServerBookmark(static function (Bookmark $bookmark) use (&$received): void {
            $received = $bookmark;
        });
        $fromServer = new Bookmark(['neo4j:bookmark:v1:tx3']);

        $holder->setBookmarkFromServer($fromServer);

        self::assertSame($fromServer, $holder->getBookmark());
        self::assertSame($fromServer, $received);
    }

    public function testSetBookmarkFromServerSkipsListenerForEmptyBookmark(): void
    {
        $holder = new BookmarkHolder(new Bookmark(['neo4j:bookmark:v1:tx1']));
        $calls = 0;
        $holder->onServerBookmark(static function () use (&$calls): void {
            ++$calls;
        });

        $holder->setBookmarkFromServer(new Bookmark());

        self::assertTrue($holder->getBookmark()->isEmpty());
        self::assertSame(0, $calls);
    }

    public function testDisabledIgnoresSetBookmarkFromServerAndListener(): void
    {
        $holder = new BookmarkHolder(new Bookmark(['neo4j:bookmark:v1:tx1']), false);
        $calls = 0;
        $holder->onServerBookmark(static function () use (&$calls): void {
            ++$calls;
        });

        $holder->setBookmarkFromServer(new Bookmark(['neo4j:bookmark:v1:tx2']));

        self::assertTrue($holder->getBookmark()->isEmpty());
        self::assertSame(0, $calls);
    }
}
