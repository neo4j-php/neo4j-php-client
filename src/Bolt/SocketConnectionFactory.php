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

use Bolt\connection\Socket;
use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;

final class SocketConnectionFactory implements BasicConnectionFactoryInterface
{
    public function __construct(
        private readonly StreamConnectionFactory $factory,
    ) {
    }

    public function create(UriConfiguration $config): Connection
    {
        if ($config->getSslLevel() !== '') {
            return $this->factory->create($config);
        }

        $socketTimeout = $config->getTimeout() ?? DriverConfiguration::DEFAULT_SOCKET_TIMEOUT;
        $connection = new Socket($config->getHost(), $config->getPort() ?? 7687, $socketTimeout);

        return new Connection($connection, '', $config->getLogger());
    }
}
