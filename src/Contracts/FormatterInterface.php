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

namespace Laudis\Neo4j\Contracts;

use Bolt\Bolt;
use JsonException;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Http\HttpConnection;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * A formatter (aka Hydrator) is reponsible for formatting the incoming results of the driver.
 *
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
 * @psalm-type BoltCypherStats = array{
 *     nodes-created?: int,
 *     nodes-deleted?: int,
 *     relationships-created?: int,
 *     relationships-deleted?: int,
 *     properties-set?: int,
 *     labels-added?: int,
 *     labels-removed?: int,
 *     indexes-added?: int,
 *     indexes-removed?: int,
 *     constraints-added?: int,
 *     constraints-removed?: int,
 *     contains-updates?: bool,
 *     contains-system-updates?: bool,
 *     system-updates?: int,
 *     db?: string
 * }
 * @psalm-type CypherError = array{code: string, message: string}
 * @psalm-type CypherRowResponse = array{row: list<scalar|null|array<array-key,scalar|null|array>>}
 * @psalm-type CypherResponse = array{columns:list<string>, data:list<CypherRowResponse>, stats?:CypherStats}
 * @psalm-type CypherResponseSet = array{results: list<CypherResponse>, errors: list<CypherError>}
 * @psalm-type BoltMeta = array{t_first: int, fields: list<string>, qid ?: int}
 *
 * @template ResultFormat
 *
 * @deprecated Next major version will only use SummarizedResultFormatter
 */
interface FormatterInterface
{
    /**
     * Formats the results of the bolt protocol to the unified format.
     *
     * @param BoltMeta $meta
     *
     * @return ResultFormat
     */
    public function formatBoltResult(array $meta, BoltResult $result, BoltConnection $connection, float $runStart, float $resultAvailableAfter, Statement $statement, BookmarkHolder $holder);

    /**
     * Formats the results of the HTTP protocol to the unified format.
     *
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     *
     * @return CypherList<ResultFormat>
     *
     * @psalm-mutation-free
     */
    public function formatHttpResult(ResponseInterface $response, stdClass $body, HttpConnection $connection, float $resultsAvailableAfter, float $resultsConsumedAfter, iterable $statements): CypherList;

    /**
     * Decorates a request to make make sure it requests the correct format.
     *
     * @see https://neo4j.com/docs/http-api/current/actions/result-format/
     *
     * @psalm-mutation-free
     */
    public function decorateRequest(RequestInterface $request, ConnectionInterface $connection): RequestInterface;

    /**
     * Overrides the statement config of the HTTP protocol.
     *
     * @see https://neo4j.com/docs/http-api/current/actions/result-format/
     *
     * @return array{resultDataContents?: list<'GRAPH'|'ROW'|'REST'>, includeStats?:bool}
     *
     * @psalm-mutation-free
     */
    public function statementConfigOverride(ConnectionInterface $connection): array;
}
