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

use Bolt\connection\IConnection;
use Bolt\enum\ServerState;
use Bolt\protocol\V5;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\Bolt\SystemWideConnectionFactory;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SocketType;
use PHPUnit\Framework\TestCase;

final class BoltFactoryTest extends TestCase
{
    private BoltFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $basicConnectionFactory = $this->createMock(BasicConnectionFactoryInterface::class);
        $basicConnectionFactory->method('create')
            ->willReturn(new Connection($this->createMock(IConnection::class), ''));

        $protocolFactory = $this->createMock(ProtocolFactory::class);
        $protocolFactory->method('createProtocol')
            ->willReturnCallback(static function (IConnection $connection) {
                $protocol = new V5(1, $connection);
                $protocol->serverState = ServerState::READY;

                return $protocol;
            });

        $this->factory = new BoltFactory(
            $basicConnectionFactory,
            $protocolFactory,
            new SslConfigurationFactory()
        );
    }

    public function testCreateBasic(): void
    {
        $auth = $this->createMock(AuthenticateInterface::class);
        $auth->method('authenticateBolt')
            ->willReturn(['server' => 'abc', 'connection_id' => 'i', 'hints' => []]);

        $connection = $this->factory->createConnection(
            new ConnectionRequestData('', Uri::create(''), $auth, '', SslConfiguration::default()),
            SessionConfiguration::default()
        );

        self::assertInstanceOf(BoltConnection::class, $connection);
        self::assertEquals('', $connection->getEncryptionLevel());
        self::assertInstanceOf(V5::class, $connection->getImplementation()[0]);
        self::assertInstanceOf(Connection::class,
            $connection->getImplementation()[1]);
    }

    public function testSystemWideConnectionFactoryStreamOverride(): void
    {
        $factory = SystemWideConnectionFactory::getInstance(SocketType::STREAM());
        self::assertInstanceOf(SystemWideConnectionFactory::class, $factory);
    }

    public function testSystemWideConnectionFactorySocketOverride(): void
    {
        if (!extension_loaded('sockets')) {
            self::markTestSkipped('sockets extension not loaded');
        }

        $factory = SystemWideConnectionFactory::getInstance(SocketType::SOCKETS());
        self::assertInstanceOf(SystemWideConnectionFactory::class, $factory);
    }

    public function testDriverConfigurationWithSocketType(): void
    {
        $socketType = SocketType::STREAM();
        $config = DriverConfiguration::default()
            ->withSocketType($socketType);

        self::assertEquals($socketType, $config->getSocketType());
    }

    public function testBoltFactoryWithSocketTypeOverride(): void
    {
        $factory = BoltFactory::create(null, SocketType::STREAM());
        self::assertInstanceOf(BoltFactory::class, $factory);
    }
}
