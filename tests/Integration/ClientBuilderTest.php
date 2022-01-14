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

use Dotenv\Dotenv;
use function explode;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    private function getBoltUri(): ?string
    {
        /** @var string|mixed $connections */
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            Dotenv::createImmutable(__DIR__.'/../../')->load();
            /** @var string|mixed $connections */
            $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
            if (!is_string($connections)) {
                $connections = '';
            }
        }
        foreach (explode(',', $connections) as $uri) {
            $psrUri = Uri::create($uri);
            if ($psrUri->getScheme() === 'bolt') {
                return $psrUri->__toString();
            }
        }

        return null;
    }

    public function testBoltSetupWithScheme(): void
    {
        $uri = $this->getBoltUri();
        if ($uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $client = ClientBuilder::create()->addBoltConnection('bolt', $uri)->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWithoutPort(): void
    {
        $uri = $this->getBoltUri();
        if ($uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $client = ClientBuilder::create()->addBoltConnection('bolt', $uri)->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWrongScheme(): void
    {
        $uri = $this->getBoltUri();
        if ($uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $client = ClientBuilder::create()->addBoltConnection('bolt', $uri)->build();
        $tsx = $client->beginTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }
}
