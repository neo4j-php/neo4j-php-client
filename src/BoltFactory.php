<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\error\ConnectException;
use Bolt\protocol\V3;
use function count;
use Exception;
use function explode;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Neo4j\RoutingTable;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Small wrapper around the bolt library to easily guarantee only bolt version 3 and up will be created and authenticated.
 */
final class BoltFactory
{
    /** @psalm-readonly */
    private Bolt $bolt;
    private AuthenticateInterface $auth;
    private string $userAgent;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(Bolt $bolt, AuthenticateInterface $auth, string $userAgent)
    {
        $this->bolt = $bolt;
        $this->auth = $auth;
        $this->userAgent = $userAgent;
    }

    /**
     * @throws Exception
     */
    public function build(): V3
    {
        $this->bolt->setProtocolVersions(4.4, 4.3, 4.2, 3);
        try {
            $build = $this->bolt->build();
        } catch (ConnectException $exception) {
            $this->bolt->setProtocolVersions(4.1, 4.0, 4, 3);
            $build = $this->bolt->build();
        }

        if (!$build instanceof V3) {
            throw new RuntimeException('Client only supports bolt version 3 and up.');
        }

        $this->auth->authenticateBolt($build, $this->userAgent);

        return $build;
    }

    public static function fromVariables(
        UriInterface $uri,
        ?UriInterface $server,
        ?RoutingTable $table,
        AuthenticateInterface $authenticate,
        string $userAgent
    ): self {
        $connectingTo = $server ?? $uri;
        $socket = new StreamSocket($uri->getHost(), $connectingTo->getPort() ?? 7687);

        self::configureSsl($uri, $connectingTo, $socket, $table);

        return new self(new Bolt($socket), $authenticate, $userAgent);
    }

    private static function configureSsl(UriInterface $uri, UriInterface $server, StreamSocket $socket, ?RoutingTable $table): void
    {
        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with($sslConfig, 's')) {
            // We have to pass a different host when working with ssl on aura.
            // There is a strange behaviour where if we pass the uri host on a single
            // instance aura deployment, we need to pass the original uri for the
            // ssl configuration to be valid.
            if ($table && count($table->getWithRole()) > 1) {
                self::enableSsl($server->getHost(), $sslConfig, $socket);
            } else {
                self::enableSsl($uri->getHost(), $sslConfig, $socket);
            }
        }
    }

    private static function enableSsl(string $host, string $sslConfig, StreamSocket $sock): void
    {
        $options = [
            'verify_peer' => true,
            'peer_name' => $host,
        ];
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $options['SNI_enabled'] = true;
        }
        if ($sslConfig === 's') {
            $sock->setSslContextOptions($options);
        } elseif ($sslConfig === 'ssc') {
            $options['allow_self_signed'] = true;
            $sock->setSslContextOptions($options);
        }
    }
}
