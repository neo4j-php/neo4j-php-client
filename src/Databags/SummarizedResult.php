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

use ArrayAccess;
use BadMethodCallException;
use Generator;
use function is_iterable;
use IteratorAggregate;
use JsonSerializable;
use function sprintf;

/**
 * A result containing the values and the summary.
 *
 * @template T
 *
 * @psalm-immutable
 *
 * @implements ArrayAccess<string, ResultSummary|T>
 */
final class SummarizedResult implements JsonSerializable, ArrayAccess, IteratorAggregate
{
    private ResultSummary $summary;
    /** @var T */
    private $result;

    /**
     * @param T $result
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

    public function getIterator(): Generator
    {
        if (is_iterable($this->result)) {
            yield from $this->result;
        } else {
            yield 'summary' => $this->summary;
            yield 'result' => $this->result;
        }
    }

    /**
     * @return array{summary: ResultSummary, result: T}
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->summary,
            'result' => $this->result,
        ];
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->jsonSerialize());
    }

    public function offsetGet($offset)
    {
        return $this->jsonSerialize()[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException(sprintf('%s is immutable', __CLASS__));
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException(sprintf('%s is immutable', __CLASS__));
    }
}
