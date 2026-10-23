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

use Laudis\Neo4j\BookmarkManagers;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkManagerConfig;
use Laudis\Neo4j\Neo4jBookmarkManager;
use Laudis\Neo4j\NoOpBookmarkManager;
use PHPUnit\Framework\TestCase;

final class Neo4jBookmarkManagerTest extends TestCase
{
    public function testShouldAddInitialBookmarks(): void
    {
        $initialBookmarks = [new Bookmark(['SY:000001'])];
        $manager = new Neo4jBookmarkManager($initialBookmarks);

        self::assertEquals($initialBookmarks, $manager->getBookmarks());
    }

    public function testShouldNotifyUpdateListener(): void
    {
        $notified = null;
        $manager = new Neo4jBookmarkManager([], static function (array $bookmarks) use (&$notified): void {
            $notified = $bookmarks;
        });
        $bookmark = new Bookmark(['SY:000001']);

        $manager->updateBookmarks([], [$bookmark]);

        self::assertEquals([$bookmark], $notified);
    }

    public function testShouldUpdateBookmarks(): void
    {
        $initialBookmarks = [
            new Bookmark(['SY:000001']),
            new Bookmark(['SY:000002']),
            new Bookmark(['SY:000003']),
            new Bookmark(['SY:000004']),
            new Bookmark(['SY:000005']),
        ];
        $manager = new Neo4jBookmarkManager($initialBookmarks);
        $newBookmark = new Bookmark(['SY:000007']);

        $manager->updateBookmarks(
            [new Bookmark(['SY:000003']), new Bookmark(['SY:000004'])],
            [$newBookmark],
        );

        self::assertEquals(
            [
                new Bookmark(['SY:000001']),
                new Bookmark(['SY:000002']),
                new Bookmark(['SY:000005']),
                $newBookmark,
            ],
            $manager->getBookmarks(),
        );
    }

    public function testShouldGetBookmarksFromBookmarkSupplier(): void
    {
        $initialBookmark = new Bookmark(['SY:000001']);
        $supplierBookmark = new Bookmark(['SY:000002']);
        $supplierCalls = 0;
        $manager = new Neo4jBookmarkManager(
            [$initialBookmark],
            null,
            static function () use (&$supplierCalls, $supplierBookmark): array {
                ++$supplierCalls;

                return [$supplierBookmark];
            },
        );

        self::assertEquals(
            [$initialBookmark, $supplierBookmark],
            $manager->getBookmarks(),
        );
        self::assertSame(1, $supplierCalls);
    }

    public function testDefaultManagerUsesConfig(): void
    {
        $manager = BookmarkManagers::defaultManager(
            BookmarkManagerConfig::default()->withInitialBookmarks([new Bookmark(['bm1'])]),
        );

        self::assertEquals([new Bookmark(['bm1'])], $manager->getBookmarks());
    }

    public function testNoOpBookmarkManagerReturnsEmptyBookmarks(): void
    {
        $manager = NoOpBookmarkManager::instance();

        $manager->updateBookmarks([new Bookmark(['bm1'])], [new Bookmark(['bm2'])]);

        self::assertSame([], $manager->getBookmarks());
    }
}
