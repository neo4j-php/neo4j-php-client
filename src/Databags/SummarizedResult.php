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

use Closure;
use Generator;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * A result containing the values and the summary.
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 *
 * @extends CypherList<CypherMap<OGMTypes>>
 */
final class SummarizedResult extends CypherList
{
    private ?ResultSummary $summary = null;
    /**
     * @var list<string>
     */
    private array $keys;

    /**
     * Bolt: before materializing all records, use PULL n=-1 for remaining pulls (Result.list()).
     *
     * @var (Closure():void)|null
     */
    private readonly ?Closure $prepareListFetchAll;

    /**
     * Keeps the Bolt result stream alive until this summarized result is consumed (avoids premature BoltResult::__destruct).
     *
     * @var object|null
     */
    private readonly ?object $boltResultRef;

    /**
     * @psalm-mutation-free
     *
     * @param iterable<mixed, CypherMap<OGMTypes>>|callable():Generator<mixed, CypherMap<OGMTypes>> $iterable
     * @param list<string>                                                                          $keys
     * @param (Closure():void)|null                                                                 $prepareListFetchAll
     */
    public function __construct(?ResultSummary &$summary, iterable|callable $iterable = [], array $keys = [], ?Closure $prepareListFetchAll = null, ?object $boltResultRef = null)
    {
        parent::__construct($iterable);
        $this->summary = &$summary;
        $this->keys = $keys;
        $this->prepareListFetchAll = $prepareListFetchAll;
        $this->boltResultRef = $boltResultRef;
    }

    /**
     * Returns the result summary.
     */
    public function getSummary(): ResultSummary
    {
        while ($this->summary === null && $this->valid()) {
            $this->next();
        }

        /** @var ResultSummary */
        return $this->summary;
    }

    /**
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function getResults(): CypherList
    {
        return new CypherList($this);
    }

    /**
     * @return array{summary: ResultSummary|null, result: mixed}
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->summary,
            'result' => parent::jsonSerialize(),
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * Materialize all remaining records (Bolt: remaining pulls use fetch-all / PULL n=-1 when configured).
     *
     * Does not rewind: if the caller already consumed rows with next(), only unconsumed rows are returned.
     * (iterator_to_array() would call rewind() and duplicate those rows.)
     *
     * @return list<CypherMap<OGMTypes>>
     */
    public function list(): array
    {
        $this->prepareListFetchAll?->__invoke();

        $rows = [];
        while ($this->valid()) {
            $rows[] = $this->current();
            $this->next();
        }

        return $rows;
    }
}
