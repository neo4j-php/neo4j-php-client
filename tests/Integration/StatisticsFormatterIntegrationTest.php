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

use Ds\Vector;
use Exception;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\StatementStatistics;
use Laudis\Neo4j\Formatter\StatisticsFormatter;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
use PHPUnit\Framework\TestCase;

final class StatisticsFormatterIntegrationTest extends TestCase
{
    /** @var ClientInterface<StatementStatistics> */
    private ClientInterface $client;

    public function setUp(): void
    {
        $builder = ClientBuilder::create();
        $aliases = new Vector($this->connectionAliases());
        $aliases = $aliases->slice(0, $aliases->count() - 1);
        foreach ($aliases as $index => $alias) {
            $alias = (new Vector($alias))->first();
            if ($index % 2 === 0) {
                $explosion = explode('-', $alias);
                $version = $explosion[count($explosion) - 1];
                $builder = $builder->addBoltConnection('bolt-'.$version, 'bolt://neo4j:test@neo4j-'.$version);
                $builder = $builder->addBoltConnection('http-'.$version, 'http://neo4j:test@neo4j-'.$version);
            }
        }

        $builder = $builder->addBoltConnection('cluster', 'bolt://neo4j:test@core1', BoltConfig::create()->withAutoRouting(true));

        $this->client = $builder->build()->withFormatter(new StatisticsFormatter());
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
        self::assertEquals(new StatementStatistics(1, 0, 0, 0, 1, 1), $this->client->run('CREATE (x:X {y: $x}) RETURN x', ['x' => random_bytes(128)], $alias));
    }

    /**
     * @return array<array{0: string}>
     */
    public function connectionAliases(): array
    {
        /** @var array<array{0: string}> $tbr */
        $tbr = [];
        foreach (explode(',', (string) getenv('NEO4J_VERSIONS_AVAILABLE')) as $version) {
            $tbr[] = ['bolt-'.$version];
            $tbr[] = ['http-'.$version];
        }

        $tbr[] = ['cluster'];

        return $tbr;
    }
}
