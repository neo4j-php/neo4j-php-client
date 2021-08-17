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

final class InputPosition
{
    private int $column;
    private int $line;
    private int $offset;

    public function __construct(
        int $column,
        int $line,
        int $offset
    ) {
        $this->column = $column;
        $this->line = $line;
        $this->offset = $offset;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
