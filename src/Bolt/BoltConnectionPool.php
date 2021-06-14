<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Ds\Map;
use Exception;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Enum\AccessMode;
use Psr\Http\Message\UriInterface;
use function explode;
use function filter_var;
use function str_starts_with;
use const FILTER_VALIDATE_IP;

/**
 * @implements ConnectionPoolInterface<Bolt>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    /** @var Map<string, Bolt> */
    private Map $map;

    public function __construct()
    {
        $this->map = new Map();
    }

    /**
     * @throws Exception
     */
    public function acquire(UriInterface $uri, AccessMode $mode): Bolt
    {
        $host = $uri->getHost();
        $socket = new StreamSocket($host, $uri->getPort() ?? 7687);

        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $host)) {
            $this->enableSsl($host, $sslConfig, $socket);
        }

        $bolt = new Bolt($socket);
        $this->map->put($uri->__toString(), $bolt);

        return $bolt;
    }

    private function enableSsl(string $host, string $sslConfig, StreamSocket $sock): void
    {
        // Pass a standard option to enable ssl as there is no direct flag
        // and \Bolt\Bolt only turns on ssl if an option is passed.
        $options = ['verify_peer' => true];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
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
