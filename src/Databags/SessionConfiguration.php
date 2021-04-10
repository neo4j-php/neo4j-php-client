<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

use function call_user_func;
use Ds\Vector;
use function is_callable;
use Laudis\Neo4j\Enum\AccessMode;

final class SessionConfiguration
{
    /** @var callable():string|string */
    private $database;
    /** @var callable():int|int */
    private $fetchSize;
    /** @var callable():AccessMode|AccessMode */
    private $defaultAccessMode;
    /** @var callable():Vector<string>|Vector<string> */
    private $bookmarks;

    /**
     * @param callable():string|string|null            $database
     * @param callable():int|int|null                  $fetchSize
     * @param callable():AccessMode|AccessMode|null    $defaultAccessMode
     * @param callable():Vector<string>|Vector<string> $bookmarks
     */
    public function __construct($database = null, $fetchSize = null, $defaultAccessMode = null, $bookmarks = null)
    {
        $this->database = $database ?? 'neo4j';
        $this->fetchSize = $fetchSize ?? 1000;
        $this->defaultAccessMode = $defaultAccessMode ?? AccessMode::WRITE();
        $this->bookmarks = $bookmarks ?? new Vector();
    }

    /**
     * @psalm-immutable
     *
     * @param callable():string|string|null            $database
     * @param callable():int|int|null                  $fetchSize
     * @param callable():AccessMode|AccessMode|null    $defaultAccessMode
     * @param callable():Vector<string>|Vector<string> $bookmarks
     */
    public static function create($database = null, $fetchSize = null, $defaultAccessMode = null, $bookmarks = null): self
    {
        return new self($database, $fetchSize, $defaultAccessMode, $bookmarks);
    }

    /**
     * @param string|callable():string $database
     */
    public function withDatabase($database): self
    {
        return new self($database, $this->fetchSize, $this->defaultAccessMode, $this->bookmarks);
    }

    /**
     * @param callable():int|int $size
     */
    public function withFetchSize($size): self
    {
        return new self($this->database, $size, $this->defaultAccessMode, $this->bookmarks);
    }

    /**
     * @param callable():AccessMode|AccessMode|null $defaultAccessMode
     */
    public function withDefaultAccessMode($defaultAccessMode): self
    {
        return new self($this->database, $this->fetchSize, $defaultAccessMode, $this->bookmarks);
    }

    /**
     * @param (callable():Vector<string>)|Vector<string> $bookmarks
     */
    public function withBookmarks($bookmarks): self
    {
        return new self($this->database, $this->fetchSize, $this->defaultAccessMode, $bookmarks);
    }

    public function getFetchSize(): int
    {
        if (is_callable($this->fetchSize)) {
            $this->fetchSize = call_user_func($this->fetchSize);
        }

        return $this->fetchSize;
    }

    public function getDefaultAccessMode(): AccessMode
    {
        if (is_callable($this->defaultAccessMode)) {
            $this->defaultAccessMode = call_user_func($this->defaultAccessMode);
        }

        return $this->defaultAccessMode;
    }

    public function getDatabase(): string
    {
        if (is_callable($this->database)) {
            $this->database = call_user_func($this->database);
        }

        return $this->database;
    }

    public function getBookmarks(): Vector
    {
        if (is_callable($this->bookmarks)) {
            $this->bookmarks = call_user_func($this->bookmarks);
        }

        return $this->bookmarks;
    }
}
