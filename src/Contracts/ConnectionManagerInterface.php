<?php

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
use Laudis\Neo4j\Databags\TransactionConfig;

/**
 * @template T
 */
interface ConnectionManagerInterface
{
    /**
     * @return T
     */
    public function acquireConnection(string $uri, SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig);
}
