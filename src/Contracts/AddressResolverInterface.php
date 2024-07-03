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

use Generator;

interface AddressResolverInterface
{
    /**
     * Returns the addresses.
     *
     * @return Generator<string>
     */
    public function getAddresses(string $host): Generator;
}
