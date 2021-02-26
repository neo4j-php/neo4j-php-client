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
    public function formatBoltResult(array $meta, iterable $results, Bolt $bolt): Vector
    {
        throw new BadMethodCallException('Not implemented yet');
    }

    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        throw new BadMethodCallException('Not implemented yet');
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
