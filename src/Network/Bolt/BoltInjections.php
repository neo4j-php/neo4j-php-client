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

namespace Laudis\Neo4j\Network\Bolt;

final class BoltInjections
{
    /** @var callable():string|string */
    private $database;

    /**
     * BoltInjections constructor.
     *
     * @param callable():string|string|null $database
     */
    public function __construct($database = null)
    {
        $this->database = $database ?? static function (): string { return 'neo4j'; };
    }

    public static function create(?string $database = null): self
    {
        return new self($database);
    }

    /**
     * @param string|callable():string $database
     */
    public function withDatabase($database): self
    {
        return new self($database);
    }

    public function database(): string
    {
        if (is_callable($this->database)) {
            $this->database = call_user_func($this->database);
        }

        return $this->database;
    }
}
