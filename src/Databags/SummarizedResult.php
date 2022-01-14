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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Types\CypherList;

/**
 * A result containing the values and the summary.
 *
 * @template TValue
 *
 * @extends CypherList<TValue>
 *
 * @psalm-immutable
 */
final class SummarizedResult extends CypherList
{
    private ResultSummary $summary;

    /**
     * @param iterable<mixed, TValue> $iterable
     */
    public function __construct(ResultSummary $summary, iterable $iterable = [])
    {
        parent::__construct($iterable);
        $this->summary = $summary;
    }

    /**
     * @template Value
     *
     * @param iterable<mixed, Value> $iterable
     *
     * @return static<Value>
     */
    protected function withIterable(iterable $iterable): SummarizedResult
    {
        return new self($this->summary, $iterable);
    }

    /**
     * Returns the result summary.
     */
    public function getSummary(): ResultSummary
    {
        return $this->summary;
    }

    public function getResults(): CypherList
    {
        return new CypherList($this);
    }

    /**
     * @return array{summary: ResultSummary, result: mixed}
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->summary,
            'result' => parent::jsonSerialize(),
        ];
    }
}
