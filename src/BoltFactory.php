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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
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
    private SslConfigurator $sslConfigurator;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        UriInterface $uri,
        SslConfigurator $sslConfigurator
    ) {
        $this->uri = $uri;
        $this->sslConfigurator = $sslConfigurator;
    }

    public function createConnection(string $userAgent, SslConfiguration $sslConfig, SessionConfiguration $sessionConfig, AuthenticateInterface $auth): ConnectionInterface
    {
        [$encryptionLevel, $sslConfig] = $this->sslConfigurator->configure($this->uri, $sslConfig);
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

            $response = $auth->authenticateBolt($protocol, $userAgent);
        } catch (MessageException $e) {
            throw Neo4jException::fromMessageException($e);
        }

        $sessionConfig = new ConnectionConfiguration(
            $response['server'],
            $this->uri,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($protocol),
            $sessionConfig->getAccessMode(),
            $sessionConfig->getDatabase() === null ? null : new DatabaseInfo($sessionConfig->getDatabase())
        );

        return new BoltConnection($protocol, $connection, $sessionConfig, $auth, $encryptionLevel);
    }

    public function canReuseConnection(ConnectionInterface $connection, string $userAgent, SslConfiguration $sslConfig, AuthenticateInterface $auth): bool
    {
        return $connection->getAuthentication()->toString($this->uri) === $auth->toString($this->uri) &&
                $connection->getEncryptionLevel() === $this->sslConfigurator->configure($this->uri, $sslConfig)[0] &&
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

        return new BoltConnection($protocol, $connectionImpl, $config, $connection->getAuthentication(), $connection->getEncryptionLevel());
    }
}
