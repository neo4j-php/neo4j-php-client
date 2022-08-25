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

use Bolt\connection\IConnection;
use Bolt\connection\StreamSocket;
use function explode;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Psr\Http\Message\UriInterface;

final class StreamConnectionFactory
{
    public function create(UriConfiguration $config): Connection
    {
        $connection = new StreamSocket($config->getHost(), $config->getPort(), $config->getTimeout());
        if ($config->getSslLevel() !== '') {
            $connection->setSslContextOptions($config->getSslConfiguration());
        }

        return new Connection($connection, $config->getSslLevel());
    }
}
