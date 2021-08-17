<?php
declare(strict_types=1);


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
    )
    {
        $this->column = $column;
        $this->line = $line;
        $this->offset = $offset;
    }

    /**
     * @return int
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }
}
