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

use Laudis\Neo4j\Bolt\BoltConfiguration;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 */
final class ClusterIntegrationTest extends TestCase
{
    /** @var ClientInterface<BasicResults> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $boltInjections = BoltConfiguration::create()->withAutoRouting(true);
        $this->client = ClientBuilder::create()
            ->addBoltConnection('cluster', 'bolt://neo4j:test@core1', $boltInjections)
            ->withFormatter(new BasicFormatter())
            ->build();
    }

    /**
     * @dataProvider aliasProvider
     */
    public function testAcceptance(string $connection): void
    {
        self::assertEquals(1, $this->client->run('RETURN 1 as x', [], $connection)->first()->get('x'));
    }

    /**
     * @dataProvider aliasProvider
     */
    public function testWrite(string $connection): void
    {
        self::assertEquals([], $this->client->run('CREATE (x:X) RETURN x', [], $connection)->first()->get('x'));
    }

    /**
     * @return list<array{0: string}>
     */
    public function aliasProvider(): array
    {
        return [
            ['cluster'],
        ];
    }
}
