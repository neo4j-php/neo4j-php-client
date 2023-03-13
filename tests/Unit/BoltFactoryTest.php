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
use Bolt\packstream\v1\Packer;
use Bolt\packstream\v1\Unpacker;
use Bolt\protocol\ServerState;
use Bolt\protocol\V5;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
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
            ->willReturnCallback(static fn (IConnection $connection) => [
                new V5(new Packer(), new Unpacker(), $connection, new ServerState()),
                ['server' => 'abc', 'connection_id' => 'i'],
                ]);

        $this->factory = new BoltFactory(
            $basicConnectionFactory,
            $protocolFactory,
            new SslConfigurationFactory()
        );
    }

    public function testCreateBasic(): void
    {
        $connection = $this->factory->createConnection(
            new ConnectionRequestData(Uri::create(''), Authenticate::disabled(), '', SslConfiguration::default()),
            SessionConfiguration::default()
        );

        self::assertInstanceOf(BoltConnection::class, $connection);
        self::assertEquals('', $connection->getEncryptionLevel());
        self::assertInstanceOf(V5::class, $connection->getImplementation()[0]);
        self::assertInstanceOf(Connection::class,
            $connection->getImplementation()[1]);
    }
}
