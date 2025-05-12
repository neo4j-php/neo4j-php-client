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
 * @psalm-immutable
 */
final class Position
{
    public function __construct(
        private int $column,
        private int $offset,
        private int $line,
    ) {
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function toArray(): array
    {
        return [
            'column' => $this->column,
            'offset' => $this->offset,
            'line' => $this->line,
        ];
    }
}
