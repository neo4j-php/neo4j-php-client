<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j;

use Bolt\protocol\V3;
use RuntimeException;

final class Bolt
{
    /** @psalm-readonly */
    private \Bolt\Bolt $bolt;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(\Bolt\Bolt $bolt)
    {
        $this->bolt = $bolt;
    }

    /**
     * @throws \Exception
     */
    public function build(): V3
    {
        $build = $this->bolt->build();

        if (!$build instanceof V3) {
            throw new RuntimeException('Client only supports bolt version 3 and up.');
        }

        return $build;
    }
}
