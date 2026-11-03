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

namespace Laudis\Neo4j\Tests\Integration;

use function bin2hex;

use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Basic\Session;
use Laudis\Neo4j\Client;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;

use function random_bytes;

/**
 * Proves bookmark enable/disable behavior against a real Neo4j server.
 *
 * An invalid bookmark is rejected with Neo.ClientError.Transaction.InvalidBookmark
 * only when the driver actually sends it. A successful query with that bookmark
 * configured, together with an empty last bookmark, shows the client neither sent
 * nor retained bookmark state.
 */
final class BookmarkConfigurationIntegrationTest extends EnvironmentAwareIntegrationTest
{
    private const INVALID_BOOKMARK = 'hi, this is an invalid bookmark';

    public function testDefaultConfigurationPropagatesBookmarks(): void
    {
        $id = $this->newId();
        $writer = $this->openSession();

        try {
            self::assertTrue($writer->getLastBookmark()->isEmpty());

            $writer->run(
                'CREATE (n:BookmarkConfigTest {id: $id}) RETURN n.id AS id',
                ['id' => $id]
            )->preload();

            $bookmark = $writer->getLastBookmark();
            self::assertFalse($bookmark->isEmpty());

            $reader = $this->openSession(SessionConfiguration::default()->withBookmarks([$bookmark]));
            $seen = $reader->run(
                'MATCH (n:BookmarkConfigTest {id: $id}) RETURN n.id AS id',
                ['id' => $id]
            );
            $seen->preload();

            self::assertSame($id, $seen->first()->get('id'));
            self::assertFalse($reader->getLastBookmark()->isEmpty());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testExplicitlyEnabledConfigurationPropagatesBookmarks(): void
    {
        $id = $this->newId();
        $writer = $this->openSession(SessionConfiguration::default()->withBookmarksEnabled(true));

        try {
            $writer->writeTransaction(static function (TransactionInterface $tx) use ($id): void {
                $tx->run('CREATE (n:BookmarkConfigTest {id: $id})', ['id' => $id]);
            });

            $bookmark = $writer->getLastBookmark();
            self::assertFalse($bookmark->isEmpty());

            $reader = $this->openSession(
                SessionConfiguration::default()
                    ->withBookmarks([$bookmark])
                    ->withBookmarksEnabled(true)
            );
            $count = $reader->run(
                'MATCH (n:BookmarkConfigTest {id: $id}) RETURN count(n) AS c',
                ['id' => $id]
            )->first()->get('c');

            self::assertEquals(1, $count);
            self::assertFalse($reader->getLastBookmark()->isEmpty());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testDisabledSessionDoesNotSendOrRetainBookmarks(): void
    {
        $id = $this->newId();
        $writer = $this->openSession();

        try {
            $writer->run(
                'CREATE (n:BookmarkConfigTest {id: $id}) RETURN n.id AS id',
                ['id' => $id]
            )->preload();
            $produced = $writer->getLastBookmark();
            self::assertFalse($produced->isEmpty());

            $disabled = $this->openSession(
                SessionConfiguration::default()
                    ->withBookmarks([$produced, new Bookmark([self::INVALID_BOOKMARK])])
                    ->withBookmarksEnabled(false)
            );

            self::assertTrue($disabled->getLastBookmark()->isEmpty());

            $disabled->run('RETURN 1 AS one', [])->preload();
            $disabled->run(
                'MATCH (n:BookmarkConfigTest {id: $id}) SET n.touched = true RETURN n.id AS id',
                ['id' => $id]
            )->preload();

            self::assertTrue($disabled->getLastBookmark()->isEmpty());

            $followUp = $this->openSession(
                SessionConfiguration::default()->withBookmarks([$disabled->getLastBookmark()])
            );
            $followUp->run('RETURN 1 AS one')->preload();

            self::assertFalse($followUp->getLastBookmark()->isEmpty());
            self::assertNotEquals($produced->values(), $followUp->getLastBookmark()->values());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testDisabledExplicitTransactionDoesNotSendOrRetainBookmarks(): void
    {
        $id = $this->newId();
        $session = $this->openSession(
            SessionConfiguration::default()
                ->withBookmarks([new Bookmark([self::INVALID_BOOKMARK])])
                ->withBookmarksEnabled(false)
        );

        try {
            self::assertTrue($session->getLastBookmark()->isEmpty());

            $tsx = $session->beginTransaction();
            $tsx->run('CREATE (n:BookmarkConfigTest {id: $id})', ['id' => $id]);
            $created = $tsx->run(
                'MATCH (n:BookmarkConfigTest {id: $id}) RETURN n.id AS id',
                ['id' => $id]
            );
            self::assertSame($id, $created->first()->get('id'));
            $tsx->commit();

            self::assertTrue($session->getLastBookmark()->isEmpty());

            $session->run('RETURN 1 AS one')->preload();
            self::assertTrue($session->getLastBookmark()->isEmpty());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testDisabledBookmarksAllowCommittedWriteAndLaterQuery(): void
    {
        $id = $this->newId();
        $session = $this->openSession(SessionConfiguration::default()->withBookmarksEnabled(false));

        try {
            $session->writeTransaction(static function (TransactionInterface $tx) use ($id): void {
                $tx->run('CREATE (n:BookmarkConfigTest {id: $id})', ['id' => $id]);
                $seen = $tx->run(
                    'MATCH (n:BookmarkConfigTest {id: $id}) RETURN n.id AS id',
                    ['id' => $id]
                );
                self::assertSame($id, $seen->first()->get('id'));
            });

            self::assertTrue($session->getLastBookmark()->isEmpty());

            $later = $session->run('RETURN 1 AS one');
            $later->preload();
            self::assertEquals(1, $later->first()->get('one'));
            self::assertTrue($session->getLastBookmark()->isEmpty());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testClientDefaultConfigurationDisablesBookmarks(): void
    {
        $id = $this->newId();
        $built = ClientBuilder::create()
            ->withDriver('default', $this->connectionString())
            ->withDefaultSessionConfiguration(
                SessionConfiguration::default()
                    ->withBookmarks([new Bookmark([self::INVALID_BOOKMARK])])
                    ->withBookmarksEnabled(false)
            )
            ->build();
        self::assertInstanceOf(Client::class, $built);
        $client = $built;

        try {
            self::assertFalse($client->getDefaultSessionConfiguration()->areBookmarksEnabled());
            self::assertEquals(1, $client->run('RETURN 1 AS one')->first()->get('one'));

            $client->writeTransaction(static function (TransactionInterface $tx) use ($id): void {
                $tx->run('CREATE (n:BookmarkConfigTest {id: $id})', ['id' => $id]);
            });

            self::assertEquals(1, $client->run('RETURN 1 AS one')->first()->get('one'));

            $session = $client->getDriver('default')->createSession($client->getDefaultSessionConfiguration());
            $session->run(
                'MATCH (n:BookmarkConfigTest {id: $id}) RETURN count(n) AS c',
                ['id' => $id]
            )->preload();

            self::assertTrue($session->getLastBookmark()->isEmpty());
        } finally {
            $this->deleteTestNodes($id);
        }
    }

    public function testEnabledSessionRejectsInvalidBookmark(): void
    {
        $autoCommit = $this->openSession($this->enabledWithInvalidBookmark());

        try {
            $autoCommit->run('RETURN 1');
            self::fail('An enabled session must send the configured bookmark');
        } catch (Neo4jException $e) {
            self::assertSame('Neo.ClientError.Transaction.InvalidBookmark', $e->getNeo4jCode());
        }

        $explicit = $this->openSession($this->enabledWithInvalidBookmark());

        try {
            $explicit->beginTransaction()->run('RETURN 1');
            self::fail('An enabled explicit transaction must send the configured bookmark');
        } catch (Neo4jException $e) {
            self::assertSame('Neo.ClientError.Transaction.InvalidBookmark', $e->getNeo4jCode());
        }
    }

    public function testEnabledClientRejectsInvalidBookmark(): void
    {
        $built = ClientBuilder::create()
            ->withDriver('default', $this->connectionString())
            ->withDefaultSessionConfiguration($this->enabledWithInvalidBookmark())
            ->build();
        self::assertInstanceOf(Client::class, $built);
        $client = $built;

        try {
            $client->run('RETURN 1');
            self::fail('An enabled client default must send the configured bookmark');
        } catch (Neo4jException $e) {
            self::assertSame('Neo.ClientError.Transaction.InvalidBookmark', $e->getNeo4jCode());
        }
    }

    private function enabledWithInvalidBookmark(): SessionConfiguration
    {
        return SessionConfiguration::default()
            ->withBookmarks([new Bookmark([self::INVALID_BOOKMARK])])
            ->withBookmarksEnabled(true);
    }

    private function openSession(?SessionConfiguration $config = null): Session
    {
        return Driver::create($this->connectionString())->createSession($config);
    }

    private function connectionString(): string
    {
        return $this->getUri(['bolt', 'neo4j'])->__toString();
    }

    private function newId(): string
    {
        return 'bm-'.bin2hex(random_bytes(8));
    }

    private function deleteTestNodes(string $id): void
    {
        Driver::create($this->connectionString())
            ->createSession()
            ->run('MATCH (n:BookmarkConfigTest {id: $id}) DETACH DELETE n', ['id' => $id])
            ->preload();
    }
}
