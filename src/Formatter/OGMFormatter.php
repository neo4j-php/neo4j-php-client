<?php

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
use Bolt\structures\Path;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\TypeMapperTrait;
use Bolt\structures\DateTime as BoltDateTime;
use Bolt\structures\Date as BoltDate;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Duration;
use Bolt\structures\Duration as BoltDuration;
use Bolt\structures\Node as BoltNode;
use BadMethodCallException;
use Bolt\Bolt;
use Ds\Vector;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 * @implements FormatterInterface<Vector<\Ds\Map<string, mixed>>>
 */
final class OGMFormatter implements FormatterInterface
{
    use TypeMapperTrait;

    public function formatBoltResult(array $meta, iterable $results, Bolt $bolt): Vector
    {
        $results = array_slice($results, 0, count($results) - 1);

        $tbr = new Vector();

        foreach ($results as $result) {
            $tbr->push($this->formatRow($meta, $result));
        }

        return $tbr;
    }

    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        $tbr = new Vector();

        foreach ($body['results'] as $results) {
            $tbr->push($this->buildResult($results));
        }

        return $tbr;
    }

    private function buildResult(array $result)
    {
        $tbr = new Vector();

        $columns = $result['columns'];
        foreach ($result['data'] as $data) {
            $row = $data['row'];
            $meta = $data['meta'];
            $graph = $data['graph'];

            $record = new Map();
            foreach ($row as $i => $value) {
                if (is_array($value)) {
                    // dealing with properties
                    $record->put($columns[$i], $this->mapValueToType($meta[$i]['type'], $graph['nodes'][$i]));
                } elseif (is_numeric($value)) {
                    // dealing with a number
                    $record->put($columns[$i], $value);
                }
            }

            $tbr->push($record);
        }

        return $tbr;
    }

    private function formatRow(array $meta, array $result): Map
    {
        $map = new Map();
        foreach ($meta['fields'] as $i => $column) {
            $map->put($column, $this->mapValue($result[$i]));
        }

        return $map;
    }

    private function mapValue($value)
    {
        if (is_object($value)) {
            return $this->mapValueToType(get_class($value), $value);
        }

        return $value;
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW', 'graph'],
        ];
    }
}
