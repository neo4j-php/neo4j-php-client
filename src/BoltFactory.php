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
 * @implements ConnectionFactoryInterface<V3>
 */
final class BoltFactory implements ConnectionFactoryInterface
{
    private UriInterface $uri;
    private AuthenticateInterface $authenticate;
    private DriverConfiguration $config;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        DriverConfiguration $config
    ) {
        $this->uri = $uri;
        $this->authenticate = $authenticate;
        $this->config = $config;
    }

    private static function configureSsl(UriInterface $uri, StreamSocket $socket, DriverConfiguration $config): void
    {
        $options = (new SslConfigurator())->configure($uri, $config);

        if ($options !== null) {
            $socket->setSslContextOptions($options);
        }
    }

    public function createConnection(AuthenticateInterface $auth, SessionConfiguration $config): ConnectionInterface
    {
        $ssl = (new SslConfigurator())->configure($this->uri, $this->config);
        $port = $this->uri->getPort() ?? 7687;
        if (extension_loaded('sockets') && $ssl === null) {
            $socket = new Socket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
        } else {
            $socket = new StreamSocket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
            self::configureSsl($this->uri, $socket, $this->config);
        }

        $bolt = new Bolt($socket);

        try {
            $bolt->setProtocolVersions(4.4, 4.3, 4.2, 3);
            try {
                $build = $bolt->build();
            } catch (ConnectException $exception) {
                $bolt->setProtocolVersions(4.1, 4.0, 4, 3);
                $build = $bolt->build();
            }

            if (!$build instanceof V3) {
                throw new RuntimeException('Client only supports bolt version 3 and up.');
            }

            $response = $auth->authenticateBolt($build, $this->config->getUserAgent());
        } catch (MessageException $e) {
            throw Neo4jException::fromMessageException($e);
        }

        $config = new ConnectionConfiguration(
            $response['server'],
            $this->uri,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            $this->config,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        return new BoltConnection($factory, $bolt, $config);
    }
}
