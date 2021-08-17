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
use Exception;
use function explode;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Psr\Http\Message\UriInterface;
use function str_starts_with;

/**
 * @implements ConnectionPoolInterface<Bolt>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    /**
     * @throws Exception
     */
    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        float $socketTimeout,
        string $userAgent,
        SessionConfiguration $config
    ): ConnectionInterface {
        $host = $uri->getHost();
        $socket = new StreamSocket($host, $uri->getPort() ?? 7687, $socketTimeout);

        $this->configureSsl($uri, $host, $socket);

        return TransactionHelper::connectionFromSocket($socket, $uri, $userAgent, $authenticate, $config);
    }

    private function configureSsl(UriInterface $uri, string $host, StreamSocket $socket): void
    {
        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $sslConfig)) {
            TransactionHelper::enableSsl($host, $sslConfig, $socket);
        }
    }
}
