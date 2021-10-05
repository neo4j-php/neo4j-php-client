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

use Laudis\Neo4j\Databags\SessionConfiguration;

/**
 * The driver creates sessions for carrying out work.
 *
 * @template ResultFormat
 *
 * @psalm-type ParsedUrl = array{host: string, pass: string|null, path: string, port: int, query: array<string,string>, scheme: string, user: string|null}
 *
 * @psalm-type BasicDriver = DriverInterface<\Laudis\Neo4j\Formatter\CypherList<\Laudis\Neo4j\Formatter\CypherMap<string, scalar|array|null>>>
 *
 * @psalm-immutable
 */
interface DriverInterface
{
    /**
     * @return SessionInterface<ResultFormat>
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;
}
