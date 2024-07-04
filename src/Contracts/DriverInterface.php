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

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\SessionConfiguration;

/**
 * The driver creates sessions for carrying out work.
 */
interface DriverInterface
{
    /**
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;

    /**
     * Returns true if the driver can make a valid connection with the server.
     */
    public function verifyConnectivity(?SessionConfiguration $config = null): bool;
}
