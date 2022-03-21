<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Formatter\Specialised;

use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * @psalm-immutable
 *
 * @psalm-import-type OGMTypes from OGMFormatter
 */
final class JoltHttpOGMTranslator
{
    /**
     * @return CypherList<CypherList<CypherMap<OGMTypes>>>
     */
    public function formatHttpResult(
        ResponseInterface $response,
        stdClass $body,
        ConnectionInterface $connection,
        float $resultsAvailableAfter,
        float $resultsConsumedAfter,
        iterable $statements
    ): CypherList {
        /** @var CypherList<CypherList<CypherMap<OGMTypes>>> */
        return new CypherList(new CypherList());
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        /** @psalm-suppress ImpureMethodCall */
        return $request->withHeader(
            'Accept',
            'application/vnd.neo4j.jolt+json-seq;strict=true;charset=UTF-8'
        );
    }

    /**
     * @return array{resultDataContents?: list<'GRAPH'|'ROW'|'REST'>, includeStats?:bool}
     */
    public function statementConfigOverride(): array
    {
        return [];
    }
}
