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

namespace Laudis\Neo4j\Common;

use Generator;

use function microtime;

use RuntimeException;

use function sprintf;

final class GeneratorHelper
{
    /**
     * @template T
     *
     * @param Generator<mixed, mixed, bool, T> $generator
     *
     * @return T
     */
    public static function getReturnFromGenerator(Generator $generator, ?float $timeout = null)
    {
        $start = microtime(true);
        while ($generator->valid()) {
            if ($timeout !== null) {
                self::guardTiming($start, $timeout);
            }
            $generator->next();
        }

        return $generator->getReturn();
    }

    public static function guardTiming(float $start, float $timeout): void
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed > $timeout) {
            throw new RuntimeException(sprintf('Cannot generator timed out out after %s seconds', $elapsed));
        }
    }
}
