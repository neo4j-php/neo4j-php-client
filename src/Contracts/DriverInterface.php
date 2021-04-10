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

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfig;

/**
 * @template T
 */
interface DriverInterface
{
    /**
     * @return SessionInterface<Vector<Map<string, array<array-key, mixed>|null|scalar>>>
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;

    /**
     * @return T
     */
    public function acquireConnection(SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig);
}
