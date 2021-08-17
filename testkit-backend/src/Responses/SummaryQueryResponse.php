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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents the query info included in the Summary response.
 */
final class SummaryQueryResponse implements TestkitResponseInterface
{
    private Statement $statement;

    public function __construct(Statement $statement)
    {
        $this->statement = $statement;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'SummaryQuery',
            'data' => [
                'text' => $this->statement->getText(),
                'parameters' => $this->statement->getParameters(),
            ],
        ];
    }
}
