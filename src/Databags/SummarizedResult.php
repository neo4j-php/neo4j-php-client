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
use Laudis\Neo4j\Formatter\RowDecodeFailure;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use OutOfBoundsException;

/**
 * A result containing the values and the summary.
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 *
 * @extends CypherList<CypherMap<OGMTypes>|RowDecodeFailure>
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
     */
    private readonly ?object $boltResultRef;

    /**
     * @psalm-mutation-free
     *
     * @param iterable<mixed, CypherMap<OGMTypes>|RowDecodeFailure>|callable():Generator<mixed, CypherMap<OGMTypes>|RowDecodeFailure> $iterable
     * @param list<string>                                                                                                            $keys
     * @param (Closure():void)|null                                                                                                   $prepareListFetchAll
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
        return new CypherList(array_values($this->toArray()));
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

    public function current(): CypherMap
    {
        $value = parent::current();

        if ($value instanceof RowDecodeFailure) {
            throw $value->exception;
        }

        return $value;
    }

    /**
     * @internal testKit backend: same as {@see parent::current()} but keeps {@see RowDecodeFailure} rows
     *            so the client can map them to {@see \Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse}
     *            without losing the underlying Bolt row cursor
     */
    public function currentAllowingDecodeFailures(): mixed
    {
        return parent::current();
    }

    public function peek(): ?CypherMap
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->current();
    }

    public function first(): CypherMap
    {
        foreach ($this as $value) {
            return $value;
        }

        throw new OutOfBoundsException('Cannot grab first element of an empty list');
    }

    public function last(): CypherMap
    {
        $rows = array_values($this->toArray());
        if ($rows === []) {
            throw new OutOfBoundsException('Cannot grab last element of an empty list');
        }

        return $rows[count($rows) - 1];
    }

    /**
     * @param callable(CypherMap<OGMTypes>, int):void $callable
     *
     * @return $this
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }

    /**
     * @return array<int, CypherMap<OGMTypes>>
     */
    public function toArray(): array
    {
        $this->preload();

        /** @var array<int, CypherMap<OGMTypes>> $out */
        $out = [];
        foreach ($this->cache as $i => $value) {
            if ($value instanceof RowDecodeFailure) {
                throw $value->exception;
            }
            $out[$i] = $value;
        }

        return $out;
    }

    public function offsetGet(mixed $offset): CypherMap
    {
        $value = parent::offsetGet($offset);
        if ($value instanceof RowDecodeFailure) {
            throw $value->exception;
        }

        return $value;
    }

    public function get(int $key): CypherMap
    {
        return $this->offsetGet($key);
    }
}
