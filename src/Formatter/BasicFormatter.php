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
use Bolt\structures\Path;
use function count;
use Ds\Map;
use Ds\Vector;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * @psalm-import-type CypherError from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherRowResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 * @implements FormatterInterface<Vector<Map<string, scalar|array|null>>>
 */
final class BasicFormatter implements FormatterInterface
{
    /**
     * @param array{fields: array<int, string>} $meta
     * @param array<array-key, array>           $results
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function formatBoltResult(array $meta, array $results, Bolt $bolt): Vector
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

    /**
     * @psalm-param CypherResponse $result
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    private function buildResult(array $result): Vector
    {
        $tbr = new Vector();

        $columns = $result['columns'];
        foreach ($result['data'] as $dataRow) {
            $row = $dataRow['row'];
            /** @psalm-var Map<string,null|scalar|array> $map */
            $map = new Map();
            $vector = new Vector($row);
            foreach ($columns as $index => $key) {
                $map->put($key, $vector->get($index));
            }
            $tbr->push($map);
        }

        return $tbr;
    }

    /**
     * @param array{fields: array<int, string>} $meta
     *
     * @return Map<string, scalar|array|null>
     */
    private function formatRow(array $meta, array $result): Map
    {
        /** @var Map<string, scalar|array|null> $map */
        $map = new Map();
        foreach ($meta['fields'] as $i => $column) {
            $map->put($column, $this->mapValue($result[$i]));
        }

        return $map;
    }

    private function mapPath(Path $path): array
    {
        $rels = $path->rels();
        $nodes = $path->nodes();
        $tbr = [];
        /**
         * @var mixed $node
         */
        foreach ($nodes as $i => $node) {
            /** @var mixed */
            $tbr[] = $node;
            if (isset($rels[$i])) {
                /** @var mixed */
                $tbr[] = $rels[$i];
            }
        }

        return $tbr;
    }

    /**
     * @param mixed $value
     *
     * @return scalar|array|null
     */
    private function mapValue($value)
    {
        if ($value instanceof Path) {
            $value = $this->mapPath($value);
        }

        if (is_object($value)) {
            return $this->objectToProperty($value);
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->remapObjectsInArray($value);
        }

        throw new UnexpectedValueException('Did not expect to receive value of type: '.gettype($value));
    }

    private function objectToProperty(object $object): array
    {
        if ($object instanceof Path) {
            return $this->mapPath($object);
        }

        if (!method_exists($object, 'properties')) {
            $message = 'Cannot handle objects without a properties method. Class given: '.get_class($object);
            throw new UnexpectedValueException($message);
        }

        /** @var array */
        return $object->properties();
    }

    private function remapObjectsInArray(array $value): array
    {
        /**
         * @psalm-var mixed $variable
         */
        foreach ($value as $key => $variable) {
            if (is_object($variable)) {
                $value[$key] = $this->objectToProperty($variable);
            }
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
            'resultDataContents' => ['ROW'],
        ];
    }
}
