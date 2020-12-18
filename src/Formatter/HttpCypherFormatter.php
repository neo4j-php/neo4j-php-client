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
use InvalidArgumentException;
use JsonException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\StatementStatistics;
use Laudis\Neo4j\ParameterHelper;
use stdClass;

/**
 * @psalm-type CypherStats = array{
 *     nodes_created: int,
 *     nodes_deleted: int,
 *     relationships_created: int,
 *     relationships_deleted: int,
 *     properties_set: int,
 *     labels_added: int,
 *     labels_removed: int,
 *     indexes_added: int,
 *     indexes_removed: int,
 *     constraints_added: int,
 *     constraints_removed: int,
 *     contains_updates: bool,
 *     contains_system_updates?: bool,
 *     system_updates?: int
 * }
 * @psalm-type CypherError = array{code: string, message: string}
 * @psalm-type CypherRowResponse = array{row: list<scalar|null|array<array-key,scalar|null|array>>}
 * @psalm-type CypherResponse = array{columns:list<string>, data:list<CypherRowResponse>, stats?:CypherStats}
 * @psalm-type CypherResponseSet = array{results: list<CypherResponse>, errors: list<CypherError>}
 */
final class HttpCypherFormatter
{
    /**
     * @param CypherResponseSet $responseSet
     *
     * @return Vector<StatementStatistics>
     *
     * @codeCoverageIgnore Still dead code
     */
    public function formatStats(array $responseSet): Vector
    {
        $tbr = new Vector();
        foreach ($responseSet['results'] as $result) {
            if (!isset($result['stats'])) {
                throw new InvalidArgumentException('No stats found in the response set');
            }
            $tbr->push(new StatementStatistics(
                $result['stats']['nodes_created'],
                $result['stats']['nodes_deleted'],
                $result['stats']['relationships_created'],
                $result['stats']['relationships_deleted'],
                $result['stats']['properties_set'],
                $result['stats']['labels_added'],
                $result['stats']['labels_removed'],
                $result['stats']['indexes_added'],
                $result['stats']['indexes_removed'],
                $result['stats']['constraints_added'],
                $result['stats']['constraints_removed'],
                $result['stats']['contains_updates'],
                $result['stats']['contains_system_updates'] ?? false,
                $result['stats']['system_updates'] ?? 0,
            ));
        }

        return $tbr;
    }

    /**
     * @param CypherResponseSet $responseSet
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function formatResponse(array $responseSet): Vector
    {
        $tbr = new Vector();

        foreach ($responseSet['results'] as $results) {
            $tbr->push($this->buildResult($results));
        }

        return $tbr;
    }

    /**
     * @param CypherResponseSet $responseSet
     *
     * @return Vector<Neo4jError>
     */
    public function filterError(array $responseSet): Vector
    {
        $errors = new Vector();
        foreach ($responseSet['errors'] as $error) {
            $errors->push(new Neo4jError($error['code'], $error['message']));
        }

        return $errors;
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
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public function prepareBody(iterable $statements, RequestData $config): string
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $st = [
                'statement' => $statement->getText(),
                'resultDataContents' => ['ROW'],
                'includeStats' => $config->includeStats(),
            ];
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            $st['parameters'] = $parameters->count() === 0 ? new stdClass() : $parameters->toArray();
            $tbr[] = $st;
        }

        return json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);
    }
}
