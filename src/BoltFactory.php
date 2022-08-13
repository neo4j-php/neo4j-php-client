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
use Bolt\connection\IConnection;
use Bolt\protocol\V3;
use function explode;
use Laudis\Neo4j\Bolt\AConnectionFactory;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\ProtocolFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 *
 * @implements ConnectionFactoryInterface<array{0: V3, 1: IConnection}>
 */
final class BoltFactory implements ConnectionFactoryInterface
{
    private UriInterface $uri;
    private AConnectionFactory $connectionFactory;
    private ProtocolFactory $protocolFactory;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(UriInterface $uri, AConnectionFactory $connectionFactory, ProtocolFactory $protocolFactory)
    {
        $this->uri = $uri;
        $this->connectionFactory = $connectionFactory;
        $this->protocolFactory = $protocolFactory;
    }

    public function createConnection(
        string $userAgent,
        SslConfiguration $sslConfig,
        SessionConfiguration $sessionConfig,
        AuthenticateInterface $auth
    ): ConnectionInterface {
        [$connection, $encryptionLevel] = $this->connectionFactory->create($sslConfig);
        [$protocol, $authResponse] = $this->protocolFactory->createProtocol($connection, $userAgent);

        $sessionConfig = new ConnectionConfiguration(
            $authResponse['server'],
            $this->uri,
            explode('/', $authResponse['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($protocol),
            $sessionConfig->getAccessMode(),
            $sessionConfig->getDatabase() === null ? null : new DatabaseInfo($sessionConfig->getDatabase())
        );

        return new BoltConnection($protocol, $connection, $sessionConfig, $auth, $encryptionLevel, $userAgent);
    }

    public function canReuseConnection(
        ConnectionInterface $connection,
        string $userAgent,
        SslConfiguration $sslConfig,
        AuthenticateInterface $auth
    ): bool {
        return $connection->getAuthentication()->toString($this->uri) === $auth->toString($this->uri) &&
               $this->connectionFactory->sameEncryptionLevel($connection->getEncryptionLevel(), $this->uri, $sslConfig) &&
               $connection->getUserAgent() === $userAgent &&
               $connection->getServerAddress()->getHost() === $this->uri->getHost() &&
               $connection->getServerAddress()->getPort() === $this->uri->getPort();
    }

    public function reuseConnection(ConnectionInterface $connection, SessionConfiguration $config): ConnectionInterface
    {
        $config = new ConnectionConfiguration(
            $connection->getServerAgent(),
            $this->uri,
            $connection->getServerVersion(),
            $connection->getProtocol(),
            $config->getAccessMode(),
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        [$protocol, $connectionImpl] = $connection->getImplementation();

        return new BoltConnection(
            $protocol,
            $connectionImpl,
            $config,
            $connection->getAuthentication(),
            $connection->getEncryptionLevel(),
            $connection->getUserAgent()
        );
    }
}
