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

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 */
interface ConnectionInterface
{
    /**
     * @return T
     */
    public function getImplementation();

    public function getServerAgent(): string;

    public function getServerAddress(): UriInterface;

    public function getServerVersion(): string;

    public function getProtocol(): ConnectionProtocol;

    public function getAccessMode(): AccessMode;

    public function getDatabaseInfo(): DatabaseInfo;
}
