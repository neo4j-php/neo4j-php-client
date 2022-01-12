<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Tests\Fixtures\MoviesFixture;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @psalm-suppress all
 */
final class DriverParityTest extends SelectableDriverIntegrationTestCase
{
    private const TESTABLE_SCHEMES = ['bolt', 'http'];

    protected function setUp(): void
    {
        parent::setUp();
        $client = $this->getClient();

        $client->run('MATCH (n) DETACH DELETE n');
        $client->run(MoviesFixture::CQL);
    }

    protected static function formatter(): FormatterInterface
    {
        return SummarizedResultFormatter::create();
    }

    public function testCanHandleMapLiterals(): void
    {
        $this->runParityTest(function (ClientInterface $client) {
            $results = $client->run('MATCH (n:Person)-[r:ACTED_IN]->(m) RETURN n, {movie: m, roles: r.roles} AS actInfo LIMIT 5');

            foreach ($results as $result) {
                $actorInfo = $result->get('actInfo');

                $this->assertInstanceOf(CypherMap::class, $actorInfo);
                $this->assertTrue($actorInfo->hasKey('roles'));
                $this->assertTrue($actorInfo->hasKey('movie'));
            }
        });
    }

    private function runParityTest(callable $test): void
    {
        foreach (self::TESTABLE_SCHEMES as $scheme) {
            $client = $this->getClientForScheme($scheme);
            echo 'Testing '.$scheme.' for '.$this->getName().PHP_EOL;
            $test($client);
            echo $scheme.' passed'.PHP_EOL;
        }
    }
}
