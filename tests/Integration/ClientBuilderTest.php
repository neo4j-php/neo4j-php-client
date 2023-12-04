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

namespace Integration;

use Laudis\Neo4j\Client;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use PHPUnit\Framework\TestCase;

class ClientBuilderTest extends TestCase
{
    public function testGetClient(): void
    {
        $sslConfig = SslConfiguration::default()->withVerifyPeer(false)->withMode(SslMode::FROM_URL());
        $driverconfig = DriverConfiguration::default()
            ->withSslConfiguration($sslConfig)
            ->withMaxPoolSize(4096)
            ->withAcquireConnectionTimeout(2.5);
        $sessionConfig = SessionConfiguration::default()->withDatabase('neo4j');
        $transactionConfig = TransactionConfiguration::default()->withTimeout(120.0);
        $client = ClientBuilder::create()
            ->withDefaultDriverConfiguration($driverconfig)
            ->withDefaultSessionConfiguration($sessionConfig)
            ->withDefaultTransactionConfiguration($transactionConfig)
            ->build();

        self::assertInstanceOf(Client::class, $client);
        self::assertEquals($driverconfig, $client->getDriverSetups()->getDriverConfiguration());
        self::assertEquals($sslConfig, $client->getDriverSetups()->getDriverConfiguration()->getSslConfiguration());
        self::assertEquals($sessionConfig, $client->getDefaultSessionConfiguration());
        self::assertEquals($transactionConfig, $client->getDefaultTransactionConfiguration());
    }
}
