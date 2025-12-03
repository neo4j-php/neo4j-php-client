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

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\Bolt\SystemWideConnectionFactory;
use Laudis\Neo4j\Bolt\UriConfiguration;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 */
class BoltFactory
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private readonly BasicConnectionFactoryInterface $connectionFactory,
        private readonly ProtocolFactory $protocolFactory,
        private readonly SslConfigurationFactory $sslConfigurationFactory,
        private readonly ?Neo4jLogger $logger = null,
    ) {
    }

    public static function create(?Neo4jLogger $logger): self
    {
        return new self(SystemWideConnectionFactory::getInstance(), new ProtocolFactory(), new SslConfigurationFactory(), $logger);
    }

    public function createConnection(ConnectionRequestData $data, SessionConfiguration $sessionConfig): BoltConnection
    {
        [$sslLevel, $sslConfig] = $this->sslConfigurationFactory->create($data->getUri()->withHost($data->getHostname()), $data->getSslConfig());

        $uriConfig = new UriConfiguration(
            $data->getUri()->getHost(),
            $data->getUri()->getPort(),
            $sslLevel,
            $sslConfig,
            TransactionConfiguration::DEFAULT_TIMEOUT
        );

        $connection = $this->connectionFactory->create($uriConfig);
        $protocol = $this->protocolFactory->createProtocol($connection->getIConnection());

        $config = new ConnectionConfiguration(
            '',
            $data->getUri(),
            ConnectionProtocol::determineBoltVersion($protocol),
            $sessionConfig->getAccessMode(),
            $sessionConfig->getDatabase() === null ? null : new DatabaseInfo($sessionConfig->getDatabase()),
            $sslLevel
        );

        $connection = new BoltConnection($protocol, $connection, $data->getAuth(), $data->getUserAgent(), $config, $this->logger);

        $response = $data->getAuth()->authenticateBolt($connection, $data->getUserAgent());

        $config->setServerAgent($response['server']);

        // Apply recv_timeout hint if present
        if (array_key_exists('hints', $response) && array_key_exists('connection.recv_timeout_seconds', $response['hints'])) {
            $connection->setTimeout((float) $response['hints']['connection.recv_timeout_seconds']);
        }

        return $connection;
    }

    public function canReuseConnection(ConnectionInterface $connection, SessionConfiguration $config): bool
    {
        if (!$connection->isOpen()) {
            return false;
        }

        $databaseInfo = $connection->getDatabaseInfo();
        $database = $databaseInfo?->getName();

        return $connection->getAccessMode() === $config->getAccessMode()
            && $database === $config->getDatabase();
    }

    public function reuseConnection(BoltConnection $connection, SessionConfiguration $sessionConfig): BoltConnection
    {
        // TODO make sure session config gets merged
        return $connection;
    }
}
