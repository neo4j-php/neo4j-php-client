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

namespace Laudis\Neo4j\Formatter;

use Laudis\Neo4j\Exception\Neo4jException;

/**
 * @internal Placeholder row when a RECORD cannot be mapped to OGM (e.g. unknown IANA zone id in a temporal).
 *           The underlying {@see \Laudis\Neo4j\Bolt\BoltResult} cursor is still advanced so further rows can be read.
 */
final class RowDecodeFailure
{
    public function __construct(
        public readonly Neo4jException $exception,
    ) {
    }
}
