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

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Exception\Neo4jException;
use RuntimeException;

/**
 * Thrown when a PULL receives one or more RECORDs followed by a Bolt FAILURE.
 * Buffered rows must be yielded before the underlying server error is propagated.
 */
final class PullPartialFailureException extends RuntimeException
{
    /** @var list<list<mixed>> */
    private readonly array $rows;

    /**
     * @param non-empty-list<array<array-key, mixed>> $bufferedRows
     */
    public function __construct(
        array $bufferedRows,
        private readonly Neo4jException $failure,
    ) {
        /** @var list<list<mixed>> $rows */
        $rows = [];
        foreach ($bufferedRows as $bufferedRow) {
            $row = array_is_list($bufferedRow) ? $bufferedRow : array_values($bufferedRow);
            $rows[] = $row;
        }

        $this->rows = $rows;
        parent::__construct($failure->getMessage(), 0, $failure);
    }

    /**
     * @return list<list<mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getFailure(): Neo4jException
    {
        return $this->failure;
    }
}
