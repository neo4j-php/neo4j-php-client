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

use function array_slice;
use Bolt\Bolt;
use Bolt\structures\Date as BoltDate;
use Bolt\structures\DateTime as BoltDateTime;
use Bolt\structures\Duration as BoltDuration;
use Bolt\structures\Time as BoltTime;
use Bolt\structures\Node as BoltNode;
use function call_user_func;
use function count;
use DateTimeImmutable;
use Ds\Map;
use Ds\Vector;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Time;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function strlen;
use function substr;
use UnexpectedValueException;

/**
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMFormatter = scalar|null|\Ds\Vector|\Ds\Map|Date|DateTime|Duration|Node|Relationship|Path
 * @implements FormatterInterface<Vector<\Ds\Map<string, mixed>>>
 */
final class OGMFormatter implements FormatterInterface
{
    private array $rawToTypes;

    public function __construct()
    {
        $this->rawToTypes = [
            BoltNode::class => [Node::class, 'makeFromBoltNode'],
            BoltDate::class => [Date::class, 'makeFromBoltDate'],
            BoltDuration::class => [Duration::class, 'makeFromBoltDuration'],
            BoltDateTime::class => [DateTime::class, 'makeFromBoltDateTime'],
            BoltTime::class => static fn (BoltTime $time): Time => new Time((float) $time->nanoseconds() / 1000000000),
            'array' => [$this, 'mapArray'],
            'int' => [$this, 'mapInteger'],
            'null' => static fn (): ?object => null,
            'bool' => static fn (bool $x): bool => $x,
            'string' => static fn (string $x): string => $x,
            'float' => static fn (float $x): float => $x,
        ];
    }

    public function formatBoltResult(array $meta, iterable $results, Bolt $bolt): CypherList
    {
        $results = array_slice($results, 0, count($results) - 1);

        $tbr = new Vector();

        foreach ($results as $result) {
            $tbr->push($this->formatRow($meta, $result));
        }

        return new CypherList($tbr);
    }

    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        $tbr = new Vector();

        foreach ($body['results'] as $results) {
            $tbr->push($this->buildResult($results));
        }

        return $tbr;
    }

    private function buildResult(array $result): Vector
    {
        $tbr = new Vector();

        $columns = $result['columns'];
        foreach ($result['data'] as $data) {
            $row = $data['row'];
            $meta = $data['meta'];

            $record = new Map();
            foreach ($row as $i => $value) {
                $internalPointer = 0;
                $record->put($columns[$i], $this->mapHttpValue($row[$i], $meta, $internalPointer));
            }

            $tbr->push($record);
        }

        return $tbr;
    }

    private function formatRow(array $meta, array $result): Map
    {
        $map = new Map();
        foreach ($meta['fields'] as $i => $column) {
            $map->put($column, $this->mapValueToType($result[$i]));
        }

        return $map;
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW'],
        ];
    }

    private function mapHttpValue($value, array $meta, int &$internalPointer)
    {
        if (is_array($value)) {
            if (isset($value[0])) {
                $tbr = new Vector();
                foreach ($value as $x) {
                    $tbr->push($this->mapHttpValue($x, $meta, $internalPointer));
                    ++$internalPointer;
                }

                return new CypherList($tbr);
            }

            $tbr = new Map();
            foreach ($value as $key => $x) {
                $tbr->put($key, $this->mapHttpValue($x, $meta, $internalPointer));
                ++$internalPointer;
            }

            return new CypherMap($tbr);
        }
        if (is_int($value)) {
            if ($meta[$internalPointer] === null) {
                return $value;
            }
        }
        if (is_string($value)) {
            $type = $meta[$internalPointer]['type'];
            if ($type === 'date') {
                $epoch = new DateTimeImmutable('@0');
                $diff = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value)->diff($epoch);

                return new Date((int) $diff->format('%a'));
            }
            if ($type === 'time') {
                if (strlen($value) === 6) {
                    $value = substr($value, 0, 5);
                    $values = explode(':', $value);

                    return new Time($values[0] * 60 * 60 + $values[1] * 60);
                }
            }
        }
        if (is_bool($value) || is_float($value)) {
            return $value;
        }
    }

    private function mapValueToType($value)
    {
        $type = get_debug_type($value);
        if (!isset($this->rawToTypes[$type])) {
            throw new UnexpectedValueException('Cannot handle value of debug type: '.$type);
        }

        return call_user_func($this->rawToTypes[$type], $value);
    }

    private function mapArray($value)
    {
        if (isset($value[0])) {
            $vector = new Vector();
            foreach ($value as $x) {
                $vector->push($this->mapValueToType($x));
            }

            return new CypherList($vector);
        }

        $map = new Map();
        foreach ($value as $key => $x) {
            $map->put($key, $this->mapValueToType($x));
        }

        return new CypherMap($map);
    }

    private function mapInteger($value): int
    {
        return (int) $value;
    }
}
