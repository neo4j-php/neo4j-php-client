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

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Tests\Base\ClientTest;

final class ClientIntegrationTest extends ClientTest
{
    public function createClient(): ClientInterface
    {
        return ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->build();
    }

    public function connectionAliases(): iterable
    {
        /** @var iterable<string> $tbr */
        $tbr = ['http', 'bolt', null];

        return $tbr;
    }
}
