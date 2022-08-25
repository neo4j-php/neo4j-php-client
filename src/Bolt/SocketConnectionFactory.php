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

final class SocketConnectionFactory
{
    private StreamConnectionFactory $factory;

    public function __construct(StreamConnectionFactory $factory)
    {
        $this->factory = $factory;
    }

    public function create(UriConfiguration $config): Connection
    {
        if ($config->getSslLevel() !== '') {
            return $this->factory->create($config);
        }

        $connection = new Socket($config->getHost(), $config->getPort(), $config->getTimeout());

        return new Connection($connection, '');
    }
}
