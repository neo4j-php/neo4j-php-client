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

use Bolt\structures\Path;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use UnexpectedValueException;

/**
 * Formats the result in basic CypherLists and CypherMaps. All cypher types are erased so that the map only contains scalar, null or array values.
 *
 * @psalm-type BasicResults = CypherList<CypherMap<scalar|array|null>>
 *
 * @implements FormatterInterface<BasicResults>
 */
final class BasicFormatter implements FormatterInterface
{
    /**
     * Creates a new instance of itself.
     *
     * @pure
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * @param array{fields: array<int, string>} $meta
     * @param array<array-key, array>           $results
     *
     * @return CypherList<CypherMap<array|scalar|null>>
     */
    public function formatBoltResult(array $meta, BoltResult $result, ?ConnectionInterface $connection = null, ?float $runStart = null, ?float $resultAvailableAfter = null, ?Statement $statement = null): CypherList
    {
        return (new CypherList(function () use ($meta, $result) {
            foreach ($result as $row) {
                yield $this->formatRow($meta, $row);
            }
        }))->withCacheLimit($result->getFetchSize());
    }

    /**
     * @psalm-mutation-free
     */
    public function formatHttpResult(ResponseInterface $response, stdClass $body, ?ConnectionInterface $connection = null, ?float $resultsAvailableAfter = null, ?float $resultsConsumedAfter = null, ?iterable $statements = null): CypherList
    {
        /** @var list<CypherList<CypherMap<scalar|array|null>>> */
        $tbr = [];

        /** @var stdClass $results */
        foreach ($body->results as $results) {
            $tbr[] = $this->buildResult($results);
        }

        return new CypherList($tbr);
    }

    /**
     * @return CypherList<CypherMap<scalar|array|null>>
     * @psalm-mutation-free
     */
    private function buildResult(stdClass $result): CypherList
    {
        /** @var list<CypherMap<scalar|array|null>> */
        $tbr = [];

        /** @var list<string> $columns */
        $columns = (array) $result->columns;
        /** @var stdClass $dataRow */
        foreach ($result->data as $dataRow) {
            /** @var array<string, scalar|array|null> $map */
            $map = [];
            /** @var list<stdClass|scalar|array|null> */
            $vector = $dataRow->row;
            foreach ($columns as $index => $key) {
                // Removes the stdClasses from the json objects
                /** @var scalar|array|null */
                $decoded = json_decode(json_encode($vector[$index], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                $map[$key] = $decoded;
            }
            $tbr[] = new CypherMap($map);
        }

        return new CypherList($tbr);
    }

    /**
     * @param array{fields: array<int, string>} $meta
     *
     * @return CypherMap<scalar|array|null>
     */
    private function formatRow(array $meta, array $result): CypherMap
    {
        /** @var array<string, scalar|array|null> $map */
        $map = [];
        foreach ($meta['fields'] as $i => $column) {
            $map[$column] = $this->mapValue($result[$i]);
        }

        return new CypherMap($map);
    }

    private function mapPath(Path $path): array
    {
        $relationships = $path->rels();
        $nodes = $path->nodes();
        $tbr = [];
        /**
         * @var mixed $node
         */
        foreach ($nodes as $i => $node) {
            /** @var mixed */
            $tbr[] = $node;
            if (isset($relationships[$i])) {
                /** @var mixed */
                $tbr[] = $relationships[$i];
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

    /**
     * @psalm-mutation-free
     */
    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    /**
     * @psalm-mutation-free
     */
    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW'],
        ];
    }
}
