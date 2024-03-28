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

/**
 * An input position refers to a specific character in a query.
 *
 * @psalm-immutable
 */
final class InputPosition
{
    public function __construct(
        private readonly int $column,
        private readonly int $line,
        private readonly int $offset
    ) {}

    /**
     * The column number referred to by the position; column numbers start at 1.
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * The line number referred to by the position; line numbers start at 1.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * The character offset referred to by this position; offset numbers start at 0.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }
}
