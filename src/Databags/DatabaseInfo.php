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

use Laudis\Neo4j\Types\AbstractCypherObject;

/**
 * Stores relevant information of a database.
 *
 * @psalm-immutable
 *
 * @extends AbstractCypherObject<string, mixed>
 */
final class DatabaseInfo extends AbstractCypherObject
{
    public function __construct(
        private readonly string $name
    ) {}

    /**
     * Returns the name of the database.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
