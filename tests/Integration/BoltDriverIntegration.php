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
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type ParsedUrl from BoltDriver
 */
final class BoltDriverIntegration extends TestCase
{
    /**
     * @throws Exception
     */
    public function testValidHostname(): void
    {
        /** @var ParsedUrl $parsedUrl */
        $parsedUrl = parse_url('bolt://neo4j:test@neo4j-42');
        $driver = new BoltDriver($parsedUrl, BoltInjections::create());
        $session = $driver->aquireSession();
        $results = $session->run([new Statement(<<<'CYPHER'
RETURN 1 AS x
CYPHER, [])]);
        self::assertEquals(1, $results->first()->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testValidUrl(): void
    {
        $ip = gethostbyname('neo4j-42');
        /** @var ParsedUrl $parsedUrl */
        $parsedUrl = parse_url('bolt://neo4j:test@'.$ip);
        $driver = new BoltDriver($parsedUrl, BoltInjections::create());
        $session = $driver->aquireSession();
        $results = $session->run([new Statement(<<<'CYPHER'
RETURN 1 AS x
CYPHER, [])]);
        self::assertEquals(1, $results->first()->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testInvalid(): void
    {
        /** @var ParsedUrl $parsedUrl */
        $parsedUrl = parse_url('bolt://neo4j:test@127.0.0.0');
        $driver = new BoltDriver($parsedUrl, BoltInjections::create());
        $this->expectException(Neo4jException::class);
        $driver->aquireSession();
    }
}
