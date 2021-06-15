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

use Exception;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\StatementStatistics;
use Laudis\Neo4j\Formatter\StatisticsFormatter;
use PHPUnit\Framework\TestCase;

final class StatisticsFormatterIntegrationTest extends TestCase
{
    /** @var ClientInterface<StatementStatistics> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->withDriver('bolt', 'bolt://neo4j:test@neo4j')
            ->withDriver('http', 'http://neo4j:test@neo4j')
            ->withDriver('cluster', 'neo4j://neo4j:test@core1')
            ->withFormatter(new StatisticsFormatter())
            ->build();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAcceptanceRead(string $alias): void
    {
        self::assertEquals(new StatementStatistics(), $this->client->run('RETURN 1', [], $alias));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testAcceptanceWrite(string $alias): void
    {
        self::assertEquals(new StatementStatistics(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $this->client->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))], $alias));
    }

    /**
     * @return array<array{0: string}>
     */
    public function connectionAliases(): array
    {
        return [['bolt'], ['http'], ['cluster']];
    }
}
