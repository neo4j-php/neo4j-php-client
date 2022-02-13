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

use Laudis\Neo4j\Types\AbstractCypherSequence;
use Laudis\Neo4j\Types\CypherList;

/**
 * A result containing the values and the summary.
 *
 * @template TValue
 *
 * @extends CypherList<TValue>
 */
final class SummarizedResult extends CypherList
{
    private ?ResultSummary $summary;

    /**
     * @param iterable<mixed, TValue>|callable():\Generator<mixed, TValue> $iterable
     *
     * @psalm-mutation-free
     */
    public function __construct(?ResultSummary &$summary, $iterable = [])
    {
        parent::__construct($iterable);
        $this->summary = &$summary;
    }

    /**
     * @template Value
     *
     * @param callable():(\Generator<mixed, Value>) $operation
     *
     * @return static<Value>
     *
     * @psalm-mutation-free
     */
    protected function withOperation($operation): AbstractCypherSequence
    {
        /**
         * @psalm-suppress UnsafeInstantiation
         * @psalm-suppress ImpurePropertyAssignment
         */
        return new self($this->summary, $operation);
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
}
