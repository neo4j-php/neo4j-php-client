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

namespace Laudis\Neo4j;

use Bolt\Bolt;
use Bolt\protocol\V3;
use function explode;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SocketConnectionFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\Bolt\StreamConnectionFactory;
use Laudis\Neo4j\Bolt\UriConfiguration;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\ConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 *
 * @implements ConnectionFactoryInterface<array{0: V3, 1: Connection}>
 */
final class BoltFactory implements ConnectionFactoryInterface
{
    /** @var SocketConnectionFactory|StreamConnectionFactory */
    private $connectionFactory;
    private ProtocolFactory $protocolFactory;
    private SslConfigurationFactory $sslConfigurationFactory;

    /**
     * @param StreamConnectionFactory|SocketConnectionFactory $connectionFactory
     *
     * @psalm-external-mutation-free
     */
    public function __construct($connectionFactory, ProtocolFactory $protocolFactory, SslConfigurationFactory $sslConfigurationFactory)
    {
        $this->connectionFactory = $connectionFactory;
        $this->protocolFactory = $protocolFactory;
        $this->sslConfigurationFactory = $sslConfigurationFactory;
    }

    public function createConnection(ConnectionRequestData $data, SessionConfiguration $sessionConfig): ConnectionInterface
    {
        [$sslLevel, $sslConfig] = $this->sslConfigurationFactory->create($data->getUri(), $data->getSslConfig());

        $uriConfig = new UriConfiguration(
            $data->getUri()->getHost(),
            $data->getUri()->getPort(),
            $sslLevel,
            $sslConfig,
            TransactionConfiguration::DEFAULT_TIMEOUT
        );

        $connection = $this->connectionFactory->create($uriConfig);
        [$protocol, $authResponse] = $this->protocolFactory->createProtocol($connection->getIConnection(), $data->getAuth(), $data->getUserAgent());

        $config = new ConnectionConfiguration(
            $authResponse['server'],
            $data->getUri(),
            explode('/', $authResponse['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($protocol),
            $sessionConfig->getAccessMode(),
            $sessionConfig->getDatabase() === null ? null : new DatabaseInfo($sessionConfig->getDatabase()),
            $sslLevel
        );

        return new BoltConnection($protocol, $connection, $data->getAuth(), $data->getUserAgent(), $config);
    }

    public function canReuseConnection(ConnectionInterface $connection, ConnectionRequestData $data): bool
    {
        return $connection->getServerAddress()->getHost() === $data->getUri()->getHost() &&
               $connection->getServerAddress()->getPort() === $data->getUri()->getPort() &&
               $connection->getAuthentication()->toString($data->getUri()) === $data->getAuth()->toString($data->getUri()) &&
               $connection->getEncryptionLevel() === $this->sslConfigurationFactory->create($data->getUri(), $data->getSslConfig())[0] &&
               $connection->getUserAgent() === $data->getUserAgent();
    }

    public function reuseConnection(ConnectionInterface $connection, SessionConfiguration $sessionConfig): ConnectionInterface
    {
        [$protocol, $connectionImpl] = $connection->getImplementation();

        $config = new ConnectionConfiguration(
            $connection->getServerAgent(),
            $connection->getServerAddress(),
            $connection->getServerVersion(),
            $connection->getProtocol(),
            $sessionConfig->getAccessMode(),
            $sessionConfig->getDatabase() === null ? null : new DatabaseInfo($sessionConfig->getDatabase()),
            $connection->getEncryptionLevel()
        );

        return new BoltConnection(
            $protocol,
            $connectionImpl,
            $connection->getAuthentication(),
            $connection->getUserAgent(),
            $config
        );
    }
}
