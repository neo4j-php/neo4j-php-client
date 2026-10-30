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

namespace Laudis\Neo4j\Types;

use InvalidArgumentException;
use Stringable;

/**
 * A Neo4j UUID (Bolt 6.1 / PackStream 1.1).
 *
 * The value is the RFC 9562 hyphenated form, for example
 * `550e8400-e29b-41d4-a716-446655440000`.
 *
 * Sending this as a query parameter requires a Bolt 6.1 connection.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<string, string>
 */
final class Uuid extends AbstractPropertyObject implements Stringable
{
    public function __construct(
        private readonly string $value,
    ) {
        if (preg_match('/^[a-f\d]{8}-([a-f\d]{4}-){3}[a-f\d]{12}$/i', $value) !== 1) {
            throw new InvalidArgumentException('UUID has invalid format');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return array{value: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
