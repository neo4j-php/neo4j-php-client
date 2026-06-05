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

namespace Laudis\Neo4j\Databags;

use IteratorAggregate;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherMap;
use Traversable;

/**
 * Eagerly consumed query result returned by Driver::executeQuery.
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 *
 * @implements IteratorAggregate<int, CypherMap<OGMTypes>>
 */
final class EagerResult implements IteratorAggregate
{
    /**
     * @param list<string>              $keys
     * @param list<CypherMap<OGMTypes>> $records
     */
    public function __construct(
        private readonly array $keys,
        private readonly array $records,
        private readonly ResultSummary $summary,
    ) {
    }

    public static function fromSummarizedResult(SummarizedResult $result): self
    {
        return new self(
            $result->keys(),
            $result->list(),
            $result->getSummary(),
        );
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * @return list<string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @return list<CypherMap<OGMTypes>>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function getSummary(): ResultSummary
    {
        return $this->summary;
    }

    public function getIterator(): Traversable
    {
        yield from $this->records;
    }
}
