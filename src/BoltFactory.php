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
use Bolt\connection\AConnection;
use Bolt\connection\Socket;
use Bolt\connection\StreamSocket;
use Bolt\error\ConnectException;
use Bolt\error\MessageException;
use Bolt\protocol\V3;
use function explode;
use function extension_loaded;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\SslConfigurator;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 *
 * @implements ConnectionFactoryInterface<array{0: V3, 1: AConnection}>
 */
final class BoltFactory implements ConnectionFactoryInterface
{
    private UriInterface $uri;
    private AuthenticateInterface $auth;
    private DriverConfiguration $config;
    private SslConfigurator $sslConfigurator;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        DriverConfiguration $config,
        SslConfigurator $sslConfigurator
    ) {
        $this->uri = $uri;
        $this->auth = $authenticate;
        $this->config = $config;
        $this->sslConfigurator = $sslConfigurator;
    }

    public function createConnection(AuthenticateInterface $auth, SessionConfiguration $config): ConnectionInterface
    {
        [$encryptionLevel, $sslConfig] = $this->sslConfigurator->configure($this->uri, $this->config);
        $port = $this->uri->getPort() ?? 7687;
        if (extension_loaded('sockets') && $sslConfig === null) {
            $connection = new Socket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
        } else {
            $connection = new StreamSocket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
            if ($sslConfig !== null) {
                $connection->setSslContextOptions($sslConfig);
            }
        }

        $bolt = new Bolt($connection);

        try {
            $bolt->setProtocolVersions(4.4, 4.3, 4.2, 3);
            try {
                $protocol = $bolt->build();
            } catch (ConnectException $exception) {
                $bolt->setProtocolVersions(4.1, 4.0, 4, 3);
                $protocol = $bolt->build();
            }

            if (!$protocol instanceof V3) {
                throw new RuntimeException('Client only supports bolt version 3 and up.');
            }

            $response = $auth->authenticateBolt($protocol, $this->config->getUserAgent());
        } catch (MessageException $e) {
            throw Neo4jException::fromMessageException($e);
        }

        $config = new ConnectionConfiguration(
            $response['server'],
            $this->uri,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($protocol),
            $config->getAccessMode(),
            $this->config,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        return new BoltConnection($protocol, $connection, $config, $this->auth, $encryptionLevel);
    }

    public function canReuseConnection(ConnectionInterface $connection): bool
    {
        return $connection->getAuthentication()->toString($this->uri) == $this->auth->toString($this->uri) ||
               $connection->getEncryptionLevel() === $this->sslConfigurator->configure($this->uri, $this->config)[0];
    }

    public function reuseConnection(ConnectionInterface $connection, SessionConfiguration $config): ConnectionInterface
    {
        $config = new ConnectionConfiguration(
            $connection->getServerAgent(),
            $this->uri,
            $connection->getServerVersion(),
            $connection->getProtocol(),
            $config->getAccessMode(),
            $this->config,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        [$protocol, $connectionImpl] = $connection->getImplementation();

        return new BoltConnection($protocol, $connectionImpl, $config, $this->auth, $connection->getEncryptionLevel());
    }
}
