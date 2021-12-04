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

use function explode;
use function getenv;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    private function getBoltUri(): string
    {
        foreach (explode(',', (string) getenv('NEO4J_CONNECTIONS')) as $uri) {
            $psrUri = Uri::create($uri);
            if ($psrUri->getScheme() === 'bolt') {
                return $psrUri->__toString();
            }
        }

        return 'bolt://neo4j:test@neo4j:7687';
    }

    public function testBoltSetupWithScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', $this->getBoltUri())->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWithoutPort(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', $this->getBoltUri())->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', $this->getBoltUri())->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }
}
