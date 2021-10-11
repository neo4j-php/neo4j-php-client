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
use Bolt\structures\Path;
use function count;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * Formats the result in basic CypherLists and CypherMaps. All cypher types are erased so that the map only contains scalar, null or array values.
 *
 * @psalm-import-type CypherError from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherRowResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @psalm-type BasicResults = CypherList<CypherMap<scalar|array|null>>
 *
 * @implements FormatterInterface<BasicResults>
 *
 * @psalm-immutable
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
    public function formatBoltResult(array $meta, array $results, ?ConnectionInterface $connection = null, ?float $resultAvailableAfter = null, ?float $resultConsumedAfter = null, ?Statement $statement = null): CypherList
    {
        $results = array_slice($results, 0, count($results) - 1);

        /** @var list<CypherMap<scalar|array|null>> */
        $tbr = [];
        foreach ($results as $result) {
            $tbr[] = $this->formatRow($meta, $result);
        }

        return new CypherList($tbr);
    }

    public function formatHttpResult(ResponseInterface $response, array $body, ?ConnectionInterface $connection = null, ?float $resultsAvailableAfter = null, ?float $resultsConsumedAfter = null, ?iterable $statements = null): CypherList
    {
        /** @var list<CypherList<CypherMap<scalar|array|null>>> */
        $tbr = [];

        foreach ($body['results'] as $results) {
            $tbr[] = $this->buildResult($results);
        }

        return new CypherList($tbr);
    }

    /**
     * @psalm-param CypherResponse $result
     *
     * @return CypherList<CypherMap<scalar|array|null>>
     */
    private function buildResult(array $result): CypherList
    {
        /** @var list<CypherMap<scalar|array|null>> */
        $tbr = [];

        $columns = $result['columns'];
        foreach ($result['data'] as $dataRow) {
            $row = $dataRow['row'];
            /** @var array<string, scalar|array|null> $map */
            $map = [];
            $vector = $row;
            foreach ($columns as $index => $key) {
                $map[$key] = $vector[$index];
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
