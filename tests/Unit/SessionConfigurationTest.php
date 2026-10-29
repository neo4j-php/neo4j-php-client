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

use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SessionConfigurationTest extends TestCase
{
    public function testBookmarksEnabledByDefault(): void
    {
        $config = SessionConfiguration::default();

        self::assertTrue($config->areBookmarksEnabled());
    }

    public function testWithBookmarksEnabledDisabled(): void
    {
        $config = SessionConfiguration::default()->withBookmarksEnabled(false);

        self::assertFalse($config->areBookmarksEnabled());
    }

    public function testWithBookmarksEnabledPreservesOtherSettings(): void
    {
        $bookmarks = [new Bookmark(['neo4j:bookmark:v1:tx1'])];
        $config = SessionConfiguration::default()
            ->withDatabase('app')
            ->withFetchSize(50)
            ->withAccessMode(AccessMode::READ())
            ->withBookmarks($bookmarks)
            ->withBookmarksEnabled(false);

        self::assertSame('app', $config->getDatabase());
        self::assertSame(50, $config->getFetchSize());
        self::assertSame(AccessMode::READ(), $config->getAccessMode());
        self::assertSame($bookmarks, $config->getBookmarks());
        self::assertFalse($config->areBookmarksEnabled());
    }

    public function testWithMethodsPreserveBookmarksEnabled(): void
    {
        $config = SessionConfiguration::default()
            ->withBookmarksEnabled(false)
            ->withDatabase('neo4j')
            ->withFetchSize(100);

        self::assertFalse($config->areBookmarksEnabled());
        self::assertSame('neo4j', $config->getDatabase());
        self::assertSame(100, $config->getFetchSize());
    }

    public function testMergeOverridesBookmarksEnabled(): void
    {
        $base = SessionConfiguration::default()->withBookmarksEnabled(true);
        $override = SessionConfiguration::default()->withBookmarksEnabled(false);

        $merged = $base->merge($override);

        self::assertFalse($merged->areBookmarksEnabled());
    }

    public function testMergeKeepsBookmarksEnabledWhenNotSetOnOverride(): void
    {
        $base = SessionConfiguration::default()->withBookmarksEnabled(false);
        $override = SessionConfiguration::default()->withDatabase('other');

        $merged = $base->merge($override);

        self::assertFalse($merged->areBookmarksEnabled());
        self::assertSame('other', $merged->getDatabase());
    }

    public function testMergePreservesLogger(): void
    {
        $logger = new Neo4jLogger('info', new NullLogger());
        $base = SessionConfiguration::default()->withLogger($logger);
        $override = SessionConfiguration::default()->withDatabase('neo4j');

        $merged = $base->merge($override);

        self::assertSame($logger, $merged->getLogger());
        self::assertSame('neo4j', $merged->getDatabase());
    }

    public function testCreateWithBookmarksEnabled(): void
    {
        $config = SessionConfiguration::create(
            bookmarksEnabled: false,
        );

        self::assertFalse($config->areBookmarksEnabled());
    }
}
