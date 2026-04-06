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

namespace Laudis\Neo4j\Bolt;

use Bolt\connection\StreamSocket;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;

final class StreamConnectionFactory implements BasicConnectionFactoryInterface
{
    public function create(UriConfiguration $config): Connection
    {
        // Socket timeout interrupts blocking reads. The server's recv_timeout_seconds hint
        // can override this per-operation via applyRecvTimeoutTemporarily().
        $socketTimeout = $config->getTimeout() ?? DriverConfiguration::DEFAULT_SOCKET_TIMEOUT;

        $connection = new StreamSocket($config->getHost(), $config->getPort() ?? 7687, $socketTimeout);
        if ($config->getSslLevel() !== '') {
            $connection->setSslContextOptions($config->getSslConfiguration());
        }

        return new Connection($connection, $config->getSslLevel(), $config->getLogger());
    }
}
