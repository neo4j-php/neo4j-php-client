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

use Laudis\Neo4j\Bolt\SessionBookmarkTracker;
use Laudis\Neo4j\Contracts\BookmarkManagerInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Neo4jBookmarkManager;
use PHPUnit\Framework\TestCase;

final class SessionBookmarkTrackerTest extends TestCase
{
    public function testPrepareForSendMergesManagerAndSessionBookmarksAndUpdatesLastUsed(): void
    {
        $manager = new Neo4jBookmarkManager([new Bookmark(['mgr:1'])]);
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, [new Bookmark(['sess:1'])]);

        $tracker->prepareForSend(true);

        self::assertEquals(['mgr:1', 'sess:1'], $holder->getBookmark()->values());

        $tracker->handleNewBookmark(new Bookmark(['srv:1']));

        self::assertEquals([new Bookmark(['srv:1'])], $manager->getBookmarks());
    }

    public function testBookmarksForRoutingSyncsWithoutUpdatingLastUsed(): void
    {
        $manager = new Neo4jBookmarkManager([new Bookmark(['mgr:1'])]);
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, [new Bookmark(['sess:1'])]);

        self::assertEquals(['mgr:1', 'sess:1'], $tracker->bookmarksForRouting());

        // Routing sync must not count as "last used", so update keeps mgr:1.
        $tracker->handleNewBookmark(new Bookmark(['srv:1']));

        self::assertEquals(
            [new Bookmark(['mgr:1']), new Bookmark(['srv:1'])],
            $manager->getBookmarks(),
        );
    }

    public function testHandleNewBookmarkIgnoresEmptyBookmark(): void
    {
        $manager = new Neo4jBookmarkManager([new Bookmark(['mgr:1'])]);
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, []);
        $tracker->prepareForSend(true);

        $tracker->handleNewBookmark(new Bookmark());

        self::assertEquals([new Bookmark(['mgr:1'])], $manager->getBookmarks());
        self::assertEquals(['mgr:1'], $holder->getBookmark()->values());
    }

    public function testHandleNewBookmarkReplacesLastReceivedAndUpdatesManager(): void
    {
        $manager = new Neo4jBookmarkManager([new Bookmark(['mgr:1'])]);
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, [new Bookmark(['sess:1'])]);
        $tracker->prepareForSend(true);

        $tracker->handleNewBookmark(new Bookmark(['srv:1']));
        $tracker->prepareForSend(true);

        self::assertEquals(['srv:1'], $holder->getBookmark()->values());

        $tracker->handleNewBookmark(new Bookmark(['srv:2']));

        self::assertEquals([new Bookmark(['srv:2'])], $manager->getBookmarks());
    }

    public function testSupplierBookmarksAreMergedForSendButNotPersistedOnUpdate(): void
    {
        $supplierCalls = 0;
        $manager = new Neo4jBookmarkManager(
            [new Bookmark(['mgr:1'])],
            null,
            static function () use (&$supplierCalls): array {
                ++$supplierCalls;

                // Only enrich the first getBookmarks() (prepareForSend); later gets stay empty
                // so we can assert supplier values were never written into manager state.
                return $supplierCalls === 1 ? [new Bookmark(['extra:1'])] : [];
            },
        );
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, []);

        $tracker->prepareForSend(true);

        // Avoid assertSame(1, $supplierCalls): Psalm would narrow the int to literal 1 and
        // then reject the later assertSame(2, ...) after getBookmarks() increments by ref.
        self::assertGreaterThanOrEqual(1, $supplierCalls);
        self::assertEquals(['extra:1', 'mgr:1'], $holder->getBookmark()->values());

        $tracker->handleNewBookmark(new Bookmark(['srv:1']));

        // Supplier result was in lastUsed for the update call, but never stored in manager state.
        self::assertEquals([new Bookmark(['srv:1'])], $manager->getBookmarks());
        self::assertSame(2, $supplierCalls);
    }

    public function testBookmarksForRoutingThenPrepareForSendCallsSupplierTwice(): void
    {
        $supplierCalls = 0;
        $manager = new Neo4jBookmarkManager(
            [],
            null,
            static function () use (&$supplierCalls): array {
                ++$supplierCalls;

                return [new Bookmark(['extra:'.$supplierCalls])];
            },
        );
        $holder = new BookmarkHolder(new Bookmark());
        $tracker = new SessionBookmarkTracker($holder, $manager, []);

        self::assertEquals(['extra:1'], $tracker->bookmarksForRouting());
        $tracker->prepareForSend(true);

        self::assertSame(2, $supplierCalls);
        self::assertEquals(['extra:2'], $holder->getBookmark()->values());
    }

    public function testDisabledHolderDoesNotReceiveSyncedBookmarks(): void
    {
        $manager = $this->createMock(BookmarkManagerInterface::class);
        $manager->expects(self::exactly(2))->method('getBookmarks')->willReturn([new Bookmark(['mgr:1'])]);
        $manager->expects(self::never())->method('updateBookmarks');

        $holder = new BookmarkHolder(new Bookmark(['ignored']), false);
        $tracker = new SessionBookmarkTracker($holder, $manager, [new Bookmark(['sess:1'])]);

        $tracker->prepareForSend(true);
        self::assertTrue($holder->getBookmark()->isEmpty());

        // Routing still asks the manager, but a disabled holder stays empty.
        self::assertSame([], $tracker->bookmarksForRouting());
    }
}
