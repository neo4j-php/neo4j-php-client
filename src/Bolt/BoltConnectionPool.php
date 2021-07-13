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

use Bolt\connection\StreamSocket;
use Exception;
use function explode;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Enum\AccessMode;
use Psr\Http\Message\UriInterface;
use function str_starts_with;

/**
 * @implements ConnectionPoolInterface<StreamSocket>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    /**
     * @throws Exception
     */
    public function acquire(UriInterface $uri, AccessMode $mode, AuthenticateInterface $authenticate): StreamSocket
    {
        $host = $uri->getHost();
        $socket = new StreamSocket($host, $uri->getPort() ?? 7687);

        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $sslConfig)) {
            TransactionHelper::enableSsl($host, $sslConfig, $socket);
        }

        return $socket;
    }
}
