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

namespace Laudis\Neo4j\Contracts;

use Bolt\Bolt;
use JsonException;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
 *     system-updates?: int
 * }
 * @psalm-type CypherError = array{code: string, message: string}
 * @psalm-type CypherRowResponse = array{row: list<scalar|null|array<array-key,scalar|null|array>>}
 * @psalm-type CypherResponse = array{columns:list<string>, data:list<CypherRowResponse>, stats?:CypherStats}
 * @psalm-type CypherResponseSet = array{results: list<CypherResponse>, errors: list<CypherError>}
 *
 * @psalm-type BoltMeta = array{t_first: int, fields: list<string>}
 *
 * @psalm-type
 *
 * @template T
 */
interface FormatterInterface
{
    /**
     * @param BoltMeta                $meta
     * @param array<array-key, array> $results
     *
     * @return T
     */
    public function formatBoltResult(array $meta, array $results, Bolt $bolt);

    /**
     * @param CypherResponseSet $body
     *
     * @throws JsonException
     *
     * @return CypherList<T>
     */
    public function formatHttpResult(ResponseInterface $response, array $body): CypherList;

    public function decorateRequest(RequestInterface $request): RequestInterface;

    /**
     * @return array{resultDataContents?: list<'GRAPH'|'ROW'|'REST'>, includeStats?:bool}
     */
    public function statementConfigOverride(): array;
}
