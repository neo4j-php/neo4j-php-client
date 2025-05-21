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
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
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
}
