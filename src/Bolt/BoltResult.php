<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use function array_splice;
use Bolt\protocol\V4;
use IteratorAggregate;
use Traversable;

final class BoltResult implements IteratorAggregate
{
    private V4 $protocol;
    private int $fetchSize;

    public function __construct(V4 $protocol, int $fetchSize)
    {
        $this->protocol = $protocol;
        $this->fetchSize = $fetchSize;
    }

    public function getIterator(): Traversable
    {
        $i = 0;
        do {
            $meta = $this->protocol->pull(['n' => $this->fetchSize]);
            foreach (array_splice($meta, 0, count($meta) - 1) as $row) {
                yield $i => $row;
                ++$i;
            }
            $hasMore = $meta[0]['has_more'] ?? false;

            yield $i => $meta[0];
        } while ($hasMore);
    }
}
