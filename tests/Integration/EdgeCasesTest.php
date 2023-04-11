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

use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Tests\Fixtures\MoviesFixture;
use Laudis\Neo4j\Types\ArrayList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;

final class EdgeCasesTest extends EnvironmentAwareIntegrationTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$session->run(MoviesFixture::CQL);
    }

    public function testCanHandleMapLiterals(): void
    {
        $results = self::$session->run('MATCH (n:Person)-[r:ACTED_IN]->(m) RETURN n, {movie: m, roles: r.roles} AS actInfo LIMIT 5');

        foreach ($results as $result) {
            $actorInfo = $result->get('actInfo');

            $this->assertInstanceOf(CypherMap::class, $actorInfo);
            $this->assertTrue($actorInfo->hasKey('roles'));
            $this->assertTrue($actorInfo->hasKey('movie'));
        }
    }

    public function testComplex(): void
    {
        $results = $this->getSession()->run(<<<'CYPHER'
        MATCH (n:Person)-[r:ACTED_IN]->(m), p = () - [] -> () - [] -> ()
        SET m.point = point({latitude:12, longitude: 56, height: 1000})
        RETURN  n,
                p,
                {movie: m, roles: r.roles} AS actInfo,
                m,
                point({latitude:13, longitude: 56, height: 1000}) as point
        LIMIT 1
        CYPHER);

        foreach ($results as $result) {
            $actorInfo = $result->get('actInfo');

            self::assertInstanceOf(CypherMap::class, $actorInfo);
            self::assertTrue($actorInfo->hasKey('roles'));
            self::assertTrue($actorInfo->hasKey('movie'));

            self::assertInstanceOf(ArrayList::class, $actorInfo->get('roles'));
            self::assertInstanceOf(Node::class, $actorInfo->get('movie'));
            // this can be a cyphermap in HTTP protocol
            $point = $actorInfo->getAsNode('movie')->getProperty('point');
            self::assertTrue($point instanceof PointInterface || $point instanceof CypherMap);
            self::assertIsObject($actorInfo->getAsNode('movie')->getProperty('point'));
            self::assertInstanceOf(Path::class, $result->get('p'));
            self::assertInstanceOf(Node::class, $result->get('m'));
            self::assertInstanceOf(PointInterface::class, $result->get('point'));
        }
    }

    public function testRunALotOfStatements(): void
    {
        $persons = $this->getSession()->run('MATCH (p:Person) RETURN p');
        $movies = $this->getSession()->run('MATCH (m:Movie) RETURN m');

        $personIds = [];
        foreach ($persons->toArray() as $record) {
            foreach ($record->keys() as $key) {
                /** @var Node $node */
                $node = $record->get($key);
                $personIds[] = $node->getId();
            }
        }

        $movieIds = [];
        foreach ($movies->toArray() as $record) {
            foreach ($record->keys() as $key) {
                /** @var Node $node */
                $node = $record->get($key);
                $movieIds[] = $node->getId();
            }
        }

        $statements = [];
        foreach ($personIds as $personId) {
            foreach ($movieIds as $movieId) {
                $statements[] = Statement::create(
                    'MATCH (a), (b) WHERE id(a) = $ida AND id(b) = $idb MERGE (a) <-[r:ACTED_IN]- (b) RETURN id(r)',
                    ['ida' => $personId, 'idb' => $movieId]
                );
            }
        }

        $this->getSession()->runStatements($statements);
        self::assertCount(4978, $statements);
    }

    public function testGettingKeysFromArraylist(): void
    {
        $result = $this->getSession()->run('MATCH (n:Person)-[r:ACTED_IN]->(m)
        RETURN n, {roles: r.roles, movie: m} AS actInfo LIMIT 1');

        $resultKeys = [];
        foreach ($result->toArray() as $record) {
            $keys = $record->keys();
            foreach ($keys as $key) {
                // Calling count inside foreach breaks it
                /** @psalm-suppress UnusedFunctionCall */
                count($keys);
                $resultKeys[] = $key;
            }
        }

        self::assertCount(1, $resultKeys);
    }
}
