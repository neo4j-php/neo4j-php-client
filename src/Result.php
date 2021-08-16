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

namespace Laudis\Neo4j;

use Iterator;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

final class Result implements Iterator
{
    private CypherList $keys;
    private ResultSummary $statistics;
    private CypherList $results;
    private int $cursor = 0;

    public function __construct(CypherList $keys, ResultSummary $statistics, CypherList $results)
    {
        $this->keys = $keys;
        $this->statistics = $statistics;
        $this->results = $results;
    }

    public function current()
    {
        return $this->results->get($this->cursor);
    }

    public function next()
    {
        ++$this->cursor;
    }

    public function key()
    {
        return $this->cursor;
    }

    public function valid()
    {
        return $this->cursor < $this->results->count();
    }

    public function rewind()
    {
        $this->cursor = 0;
    }

    public function peek(): CypherMap
    {
        return $this->current();
    }

    public function single(): ?CypherMap
    {
        if ($this->valid()) {
            $tbr = $this->current();
        } else {
            $tbr = null;
        }

        $this->cursor = $this->results->count();

        return $tbr;
    }

    public function all(): CypherList
    {
        return $this->results;
    }

    public function keys(): CypherList
    {
        return $this->keys;
    }

    public function consume(): ResultSummary
    {
        $this->cursor = $this->results->count();

        return $this->statistics;
    }
}
