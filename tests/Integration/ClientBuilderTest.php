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
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ClientBuilderTest extends TestCase
{
    public function testGetClient(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $sslConfig = SslConfiguration::default()->withVerifyPeer(false)->withMode(SslMode::FROM_URL());
        $driverconfig = DriverConfiguration::default()
            ->withSslConfiguration($sslConfig)
            ->withMaxPoolSize(4096)
            ->withAcquireConnectionTimeout(2.5)
            ->withConnectionTimeout(76)
        ->withLogger(LogLevel::DEBUG, $logger);
        $sessionConfig = SessionConfiguration::default()->withDatabase('neo4j');
        $transactionConfig = TransactionConfiguration::default()->withTimeout(120.0);
        $client = ClientBuilder::create(LogLevel::DEBUG, $logger)
            ->withDefaultDriverConfiguration($driverconfig)
            ->withDefaultSessionConfiguration($sessionConfig)
            ->withDefaultTransactionConfiguration($transactionConfig)
            ->build();

        self::assertInstanceOf(Client::class, $client);

        $driverConfigurationFromClient = $client->getDriverSetups()->getDriverConfiguration();
        self::assertInstanceOf(Neo4jLogger::class, $driverConfigurationFromClient->getLogger());

        self::assertEquals($driverconfig, $driverConfigurationFromClient);
        self::assertEquals($sslConfig, $driverConfigurationFromClient->getSslConfiguration());
        self::assertEquals($sessionConfig, $client->getDefaultSessionConfiguration());
        self::assertEquals($transactionConfig, $client->getDefaultTransactionConfiguration());
    }
}
