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

namespace Laudis\Neo4j;

use Ds\Map;
use Ds\Sequence;
use Ds\Vector;
use InvalidArgumentException;
use stdClass;

final class ParameterHelper
{
    public static function asList(iterable $iterable): Sequence
    {
        return new Vector($iterable);
    }

    public static function asMap(iterable $iterable): Map
    {
        return new Map($iterable);
    }

    /**
     * @param iterable|scalar|null $value
     *
     * @return iterable|scalar|stdClass|null
     */
    public static function asParameter($value)
    {
        if ($value instanceof Sequence && $value->count() === 0) {
            return [];
        }
        if (($value instanceof Map && $value->count() === 0) ||
            (is_array($value) && count($value) === 0)
        ) {
            return new stdClass();
        }
        if (is_iterable($value)) {
            return self::iterableToArray($value);
        }

        return $value;
    }

    /**
     * @param iterable<iterable|scalar|null> $parameters
     *
     * @return Map<array-key, iterable|scalar|stdClass|null>
     */
    public static function formatParameters(iterable $parameters): iterable
    {
        /** @var Map<array-key, iterable|scalar|stdClass|null> $tbr */
        $tbr = new Map();
        foreach ($parameters as $key => $value) {
            if (!(is_int($key) || is_string($key))) {
                $msg = 'The parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
            $tbr->put($key, self::asParameter($value));
        }

        return $tbr;
    }

    private static function iterableToArray(iterable $value): array
    {
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $val
         */
        foreach ($value as $key => $val) {
            if (is_int($key) || is_string($key)) {
                /** @psalm-suppress MixedAssignment */
                $tbr[$key] = $val;
            } else {
                $msg = 'Iterable parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
        }

        return $tbr;
    }
}
