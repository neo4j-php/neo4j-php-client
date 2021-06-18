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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    public function testBoltSetupWithScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j:7687')->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWithoutPort(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'neo4j://neo4j:test@neo4j:7687')->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }
}
