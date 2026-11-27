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

namespace Laudis\Neo4j\Tests\Relate;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Compatibility checks against Relate (https://relatedb.ai/) over Bolt.
 *
 * Requires a running Relate Bolt server and CONNECTION pointing at it, e.g.:
 * CONNECTION='bolt://relate:testtest@127.0.0.1:7687'
 *
 * Only covers behaviour Relate actually supports. Do not run the Neo4j
 * Integration suite against Relate.
 */
final class RelateBoltCompatibilityTest extends EnvironmentAwareIntegrationTest
{
    public function testBoltConnectionAndProtocolNegotiation(): void
    {
        $this->requireBoltScheme();

        $serverInfo = $this->getDriver()->getServerInfo();
        $protocol = $serverInfo->getProtocol();

        self::assertContains(
            $protocol,
            [ConnectionProtocol::BOLT_V44(), ConnectionProtocol::BOLT_V5_4()],
            sprintf(
                'Relate speaks Bolt 4.4 and 5.4 only; negotiated %s',
                $protocol->getValue()
            )
        );

        $agent = $serverInfo->getAgent();
        self::assertNotSame('', $agent, 'Server agent must be non-empty after HELLO');
        self::assertTrue(
            str_starts_with($agent, 'Relate/') || str_starts_with($agent, 'Neo4j/'),
            sprintf('Unexpected server agent "%s"', $agent)
        );
    }

    public function testBasicCypherReturn(): void
    {
        $this->requireBoltScheme();

        $result = $this->getSession()->run('RETURN 1 AS one');

        self::assertSame(1, $result->count());
        self::assertSame(1, $result->first()->get('one'));
    }

    public function testCreateAndRetrieveNode(): void
    {
        $this->requireBoltScheme();

        $marker = 'relate-php-node-'.bin2hex(random_bytes(8));

        $created = $this->getSession()->run(
            'CREATE (n:RelateCompatPerson {name: $name}) RETURN n',
            ['name' => $marker]
        );

        self::assertSame(1, $created->count());
        $node = $created->first()->getAsNode('n');
        self::assertInstanceOf(Node::class, $node);
        self::assertContains('RelateCompatPerson', $node->getLabels()->toArray());
        self::assertSame($marker, $node->getProperty('name'));

        $fetched = $this->getSession()->run(
            'MATCH (n:RelateCompatPerson {name: $name}) RETURN n.name AS name',
            ['name' => $marker]
        );

        self::assertSame(1, $fetched->count());
        self::assertSame($marker, $fetched->first()->get('name'));
    }

    public function testCreateAndRetrieveRelationship(): void
    {
        $this->requireBoltScheme();

        $marker = 'relate-php-rel-'.bin2hex(random_bytes(8));

        $result = $this->getSession()->run(
            <<<'CYPHER'
CREATE (a:RelateCompatPerson {name: $from})
CREATE (b:RelateCompatPerson {name: $to})
CREATE (a)-[r:RELATE_COMPAT_KNOWS {since: $since}]->(b)
RETURN a, r, b
CYPHER,
            ['from' => $marker.'-a', 'to' => $marker.'-b', 'since' => 2026]
        );

        self::assertSame(1, $result->count());
        $row = $result->first();

        self::assertSame($marker.'-a', $row->getAsNode('a')->getProperty('name'));
        self::assertSame($marker.'-b', $row->getAsNode('b')->getProperty('name'));

        $relationship = $row->getAsRelationship('r');
        self::assertInstanceOf(Relationship::class, $relationship);
        self::assertSame('RELATE_COMPAT_KNOWS', $relationship->getType());
        self::assertSame(2026, $relationship->getProperty('since'));

        $matched = $this->getSession()->run(
            <<<'CYPHER'
MATCH (a:RelateCompatPerson {name: $from})-[r:RELATE_COMPAT_KNOWS]->(b:RelateCompatPerson {name: $to})
RETURN r.since AS since, type(r) AS type
CYPHER,
            ['from' => $marker.'-a', 'to' => $marker.'-b']
        );

        self::assertSame(1, $matched->count());
        self::assertSame(2026, $matched->first()->get('since'));
        self::assertSame('RELATE_COMPAT_KNOWS', $matched->first()->get('type'));
    }

    public function testQueryResultRecords(): void
    {
        $this->requireBoltScheme();

        $result = $this->getSession()->run(
            'UNWIND [1, 2, 3] AS n RETURN n, n * 2 AS doubled ORDER BY n'
        );

        self::assertSame(3, $result->count());
        self::assertSame(['n', 'doubled'], $result->keys());
        self::assertSame(1, $result->get(0)->get('n'));
        self::assertSame(2, $result->get(0)->get('doubled'));
        self::assertSame(3, $result->get(2)->get('n'));
        self::assertSame(6, $result->get(2)->get('doubled'));
    }

    public function testTransactionCommit(): void
    {
        $this->requireBoltScheme();

        $marker = 'relate-php-tx-commit-'.bin2hex(random_bytes(8));
        $tsx = $this->getSession()->beginTransaction();

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isCommitted());
        self::assertFalse($tsx->isRolledBack());

        $tsx->run(
            'CREATE (n:RelateCompatPerson {name: $name}) RETURN n.name AS name',
            ['name' => $marker]
        );
        $tsx->commit();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isCommitted());
        self::assertFalse($tsx->isRolledBack());

        $found = $this->getSession()->run(
            'MATCH (n:RelateCompatPerson {name: $name}) RETURN count(n) AS c',
            ['name' => $marker]
        );
        self::assertSame(1, $found->first()->get('c'));
    }

    public function testTransactionRollback(): void
    {
        $this->requireBoltScheme();

        $marker = 'relate-php-tx-rollback-'.bin2hex(random_bytes(8));
        $tsx = $this->getSession()->beginTransaction();

        $tsx->run(
            'CREATE (n:RelateCompatPerson {name: $name}) RETURN n.name AS name',
            ['name' => $marker]
        );
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $found = $this->getSession()->run(
            'MATCH (n:RelateCompatPerson {name: $name}) RETURN count(n) AS c',
            ['name' => $marker]
        );
        self::assertSame(0, $found->first()->get('c'));
    }

    public function testAuthenticationRejectsInvalidCredentials(): void
    {
        $this->requireBoltScheme();

        $uri = $this->getUri()->withUserInfo('');
        $conf = DriverConfiguration::default()
            ->withLogger(LogLevel::DEBUG, $this->createMock(LoggerInterface::class));

        $driver = Driver::create($uri, $conf, Authenticate::basic('relate', 'definitely-wrong-password'));

        $this->expectException(Neo4jException::class);
        $driver->createSession()->run('RETURN 1 AS one');
    }

    public function testSessionLifecycleAndConnectionCleanup(): void
    {
        $this->requireBoltScheme();

        $session = $this->getDriver()->createSession();
        $result = $session->run('RETURN 1 AS one');
        self::assertSame(1, $result->first()->get('one'));

        $this->getDriver()->closeConnections();

        $sessionAfterClose = $this->getDriver()->createSession();
        $again = $sessionAfterClose->run('RETURN 2 AS two');
        self::assertSame(2, $again->first()->get('two'));
    }

    private function requireBoltScheme(): void
    {
        if (!str_contains($this->getUri()->getScheme(), 'bolt')) {
            $this->markTestSkipped('Relate compatibility tests require a bolt:// CONNECTION URI.');
        }
    }
}
