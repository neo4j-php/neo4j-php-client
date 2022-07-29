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
use Bolt\connection\Socket;
use Bolt\connection\StreamSocket;
use Bolt\error\ConnectException;
use Bolt\error\MessageException;
use Exception;
use function extension_loaded;
use Laudis\Neo4j\Bolt\SslConfigurator;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 */
final class BoltFactory
{
    /** @psalm-readonly */
    private Bolt $bolt;
    /** @psalm-readonly */
    private AuthenticateInterface $auth;
    /** @psalm-readonly */
    private string $userAgent;
    /** @psalm-readonly */
    private IConnection $connection;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(Bolt $bolt, AuthenticateInterface $auth, string $userAgent, IConnection $connection)
    {
        $this->bolt = $bolt;
        $this->auth = $auth;
        $this->userAgent = $userAgent;
        $this->connection = $connection;
    }

    /**
     * @throws Exception
     *
     * @return array{0: AProtocol, 1: array{server: string, connection_id: string, hints: list}}
     */
    public function build(): array
    {
        try {
            $this->bolt->setProtocolVersions(4.4, 4.3, 4.2, 3);
            try {
                $build = $this->bolt->build();
            } catch (ConnectException $exception) {
                $this->bolt->setProtocolVersions(4.1, 4.0, 4, 3);
                $build = $this->bolt->build();
            }

            if (version_compare($build->getVersion(), '3', '<')) {
                throw new RuntimeException('Client only supports bolt version 3 and up.');
            }

            $response = $this->auth->authenticateBolt($build, $this->userAgent);
        } catch (MessageException $e) {
            throw Neo4jException::fromMessageException($e);
        }

        return [$build, $response];
    }

    public function getConnection(): IConnection
    {
        return $this->connection;
    }

    public static function fromVariables(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        DriverConfiguration $config
    ): self {
        $ssl = (new SslConfigurator())->configure($uri, $config);
        $port = $uri->getPort() ?? 7687;
        if (extension_loaded('sockets') && $ssl === null) {
            $socket = new Socket($uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
        } else {
            $socket = new StreamSocket($uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
            self::configureSsl($uri, $socket, $config);
        }

        return new self(new Bolt($socket), $authenticate, $config->getUserAgent(), $socket);
    }

    private static function configureSsl(UriInterface $uri, StreamSocket $socket, DriverConfiguration $config): void
    {
        $options = (new SslConfigurator())->configure($uri, $config);

        if ($options !== null) {
            $socket->setSslContextOptions($options);
        }
    }

    public function getAuth(): AuthenticateInterface
    {
        return $this->auth;
    }
}
