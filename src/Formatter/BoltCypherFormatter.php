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

namespace Laudis\Neo4j\Formatter;

use Ds\Map;
use Ds\Vector;
use UnexpectedValueException;

final class BoltCypherFormatter
{
    /**
     * @param array{fields: array<int, string>} $meta
     * @param array<array-key, array>           $results
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function formatResult(array $meta, array $results): Vector
    {
        $results = array_slice($results, 0, count($results) - 1);

        $tbr = new Vector();
        foreach ($results as $result) {
            /** @var Map<string, scalar|array|null> $map */
            $map = new Map();
            foreach ($meta['fields'] as $i => $column) {
                /** @var mixed $value */
                $value = $result[$i];
                if (is_object($value)) {
                    if (method_exists($value, 'properties')) {
                        /** @var array $properties */
                        $properties = $value->properties();
                        $map->put($column, $properties);
                    } else {
                        throw new UnexpectedValueException('Cannot handle objects without a properties method. Class given: '.get_class($value));
                    }
                } elseif ($value === null || is_scalar($value) || is_array($value)) {
                    $map->put($column, $value);
                } else {
                    throw new UnexpectedValueException('Did not expect to receive value of type: '.gettype($value));
                }
            }
            $tbr->push($map);
        }

        return $tbr;
    }
}
