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
 * @template T
 *
 * @psalm-immutable
 */
final class SummarizedResult
{
    private ResultSummary $summary;
    /** @var T */
    private $result;

    /**
     * @param T                  $result
     * @param CypherList<string> $keys
     */
    public function __construct($result, ResultSummary $summary)
    {
        $this->summary = $summary;
        $this->result = $result;
    }

    /**
     * Returns the actual result.
     *
     * @return T
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Returns the result summary.
     */
    public function getSummary(): ResultSummary
    {
        return $this->summary;
    }
}
