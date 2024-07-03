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
 * The components of a Cypher query, containing the query text and parameter mapping.
 *
 * @todo deprecate and create Query Object
 *
 * @psalm-immutable
 *
 * @extends AbstractCypherObject<string, mixed>
 */
final class Statement extends AbstractCypherObject
{
    /**
     * @param iterable<string, mixed> $parameters
     */
    public function __construct(
        private readonly string $text,
        private readonly iterable $parameters
    ) {}

    /**
     * @pure
     *
     * @param iterable<string, mixed>|null $parameters
     */
    public static function create(string $text, ?iterable $parameters = null): Statement
    {
        return new self($text, $parameters ?? []);
    }

    /**
     * The query text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * The parameter mapping.
     *
     * @return iterable<string, mixed>
     */
    public function getParameters(): iterable
    {
        return $this->parameters;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'parameters' => $this->parameters,
        ];
    }
}
