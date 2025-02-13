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
     * @param iterable<mixed, CypherMap<OGMTypes>>|callable():Generator<mixed, CypherMap<OGMTypes>> $iterable
     *
     * @psalm-mutation-free
     */
    public function __construct(?ResultSummary &$summary, iterable|callable $iterable = [])
    {
        parent::__construct($iterable);
        $this->summary = &$summary;
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
}
