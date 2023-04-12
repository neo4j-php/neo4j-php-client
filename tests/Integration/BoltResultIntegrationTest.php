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

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\Bolt\SystemWideConnectionFactory;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;

final class BoltResultIntegrationTest extends EnvironmentAwareIntegrationTest
{
    public function testIterationLong(): void
    {
        if ($this->getUri()->getScheme() !== 'bolt') {
            self::markTestSkipped('No bolt uri provided');
        }

        $i = 0;
        $factory = new BoltFactory(
            SystemWideConnectionFactory::getInstance(),
            new ProtocolFactory(),
            new SslConfigurationFactory()
        );
        $connection = $factory->createConnection(
            new ConnectionRequestData($this->getUri(), Authenticate::fromUrl($this->getUri()), 'a/b', new SslConfiguration(SslMode::FROM_URL(), false)),
            SessionConfiguration::default()
        );

        $connection->getImplementation()[0]->run('UNWIND range(1, 100000) AS i RETURN i')
            ->getResponse();
        $result = new BoltResult($connection, 1000, -1);
        foreach ($result as $i => $x) {
            self::assertEquals($i + 1, $x[0] ?? 0);
        }

        self::assertEquals(100000, $i + 1);
        self::assertIsArray($result->consume());
    }
}
