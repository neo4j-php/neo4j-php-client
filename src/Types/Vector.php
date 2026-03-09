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

use Laudis\Neo4j\Enum\VectorTypeMarker;

/**
 * Neo4j Vector type (e.g. embedding). Holds a list of numbers.
 *
 * This type is only produced when decoding results from the server (Bolt). It is not supported
 * as a query parameter; use a plain list of numbers if you need to pass vector-like data.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<list<int|float>, list<int|float>>
 */
final class Vector extends AbstractPropertyObject
{
    /**
     * @param list<int|float>       $values
     * @param VectorTypeMarker|null $typeMarker Bolt type marker (how values were encoded); set when received from server
     */
    public function __construct(
        private readonly array $values,
        private readonly ?VectorTypeMarker $typeMarker = null,
    ) {
    }

    /**
     * @return list<int|float>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Bolt type marker indicating how the vector payload is encoded (e.g. FLOAT_64, INT_32).
     * Set when the vector was received from the server.
     */
    public function getTypeMarker(): ?VectorTypeMarker
    {
        return $this->typeMarker;
    }

    /**
     * @return array{values: list<int|float>, typeMarker: string|null}
     *
     * @psalm-suppress ImplementedReturnTypeMismatch parent expects array<string, list<int|float>> but we add typeMarker (string|null) for clarity
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'typeMarker' => $this->typeMarker?->name,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }
}
