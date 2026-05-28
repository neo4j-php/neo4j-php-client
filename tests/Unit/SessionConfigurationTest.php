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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\SessionConfiguration;
use PHPUnit\Framework\TestCase;

final class SessionConfigurationTest extends TestCase
{
    public function testMergePrefersArgumentDatabase(): void
    {
        $fromUri = SessionConfiguration::fromUri(Uri::create('neo4j://localhost?database=from-uri'), null);
        $caller = SessionConfiguration::create('caller-db');

        self::assertSame('caller-db', $fromUri->merge($caller)->getDatabase());
    }

    public function testInferAuraFreeDatabaseFromUriWhenPrincipalMatchesInstanceId(): void
    {
        $uri = Uri::create('neo4j+s://42d35d33:secret@42d35d33.databases.neo4j.io');

        self::assertSame('42d35d33', SessionConfiguration::inferAuraFreeDatabaseFromUri($uri));
    }

    public function testInferAuraFreeDatabaseFromUriSkipsNeo4jPrincipal(): void
    {
        $uri = Uri::create('neo4j+s://neo4j:secret@42d35d33.databases.neo4j.io');

        self::assertNull(SessionConfiguration::inferAuraFreeDatabaseFromUri($uri));
    }

    public function testInferAuraFreeDatabaseFromUriSkipsNonAuraHost(): void
    {
        $uri = Uri::create('neo4j://neo4j:secret@localhost:7687');

        self::assertNull(SessionConfiguration::inferAuraFreeDatabaseFromUri($uri));
    }
}
