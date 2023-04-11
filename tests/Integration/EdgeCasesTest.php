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

use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Tests\Fixtures\MoviesFixture;
use Laudis\Neo4j\Types\Node;

final class EdgeCasesTest extends EnvironmentAwareIntegrationTest
{
    public function testRunALotOfStatements(): void
    {
        $this->getSession()->run('MATCH (n) DETACH DELETE n');
        $this->getSession()->run(MoviesFixture::CQL);

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
        $this->getSession()->run('MATCH (n) DETACH DELETE n');
        $this->getSession()->run(MoviesFixture::CQL);

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
