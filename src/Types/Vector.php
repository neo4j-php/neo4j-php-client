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

use Bolt\packstream\Bytes;
use Bolt\protocol\IStructure;
use Bolt\protocol\v6\structures\TypeMarker as BoltTypeMarker;
use Bolt\protocol\v6\structures\Vector as BoltVector;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use Laudis\Neo4j\Enum\VectorTypeMarker;

/**
 * Neo4j Vector type (e.g. embedding). Holds a list of numbers and/or a raw Bolt payload for exact round-trips.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<list<int|float>, list<int|float>>
 */
final class Vector extends AbstractPropertyObject implements BoltConvertibleInterface
{
    /**
     * @param list<int|float>       $values
     * @param VectorTypeMarker|null $typeMarker  Bolt type marker (how values were encoded); set when received from server
     * @param string|null           $wireDtype   TestKit dtype string (i8, i16, …) when preserving raw wire form
     * @param string|null           $wireDataHex Spaced lower-case hex of payload bytes (TestKit / Bolt echo)
     */
    public function __construct(
        private readonly array $values,
        private readonly ?VectorTypeMarker $typeMarker = null,
        private readonly ?string $wireDtype = null,
        private readonly ?string $wireDataHex = null,
    ) {
    }

    /**
     * Build from TestKit / NutKit CypherVector (dtype + spaced hex), preserving bytes for parameters and equality.
     */
    public static function fromWire(string $dtype, string $dataHexSpaced): self
    {
        $normalizedHex = self::normalizeHexString($dataHexSpaced);
        $dtypeNorm = strtolower($dtype);
        $marker = self::dtypeStringToBoltMarker($dtypeNorm);
        $binary = self::hexSpacedToBinary($normalizedHex);
        $boltVec = new BoltVector(
            new Bytes([chr($marker->value)]),
            new Bytes(self::binaryStringToByteArray($binary)),
        );

        /** @var list<int|float> $decoded */
        $decoded = $boltVec->decode();
        $vtMarker = VectorTypeMarker::from($marker->value);

        return new self($decoded, $vtMarker, $dtypeNorm, $normalizedHex);
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

    public function getWireDtype(): ?string
    {
        return $this->wireDtype;
    }

    public function getWireDataHex(): ?string
    {
        return $this->wireDataHex;
    }

    /**
     * @return array{values: list<int|float>, typeMarker: string|null, wireDtype: string|null, wireDataHex: string|null}
     *
     * @psalm-suppress ImplementedReturnTypeMismatch shape differs from AbstractCypherObject template
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'typeMarker' => $this->typeMarker?->name,
            'wireDtype' => $this->wireDtype,
            'wireDataHex' => $this->wireDataHex,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    /**
     * @psalm-suppress ImpureMethodCall Bolt encode paths are deterministic
     */
    public function convertToBolt(): IStructure
    {
        if ($this->wireDtype !== null && $this->wireDataHex !== null && $this->typeMarker !== null) {
            $marker = BoltTypeMarker::from($this->typeMarker->value);
            $binary = self::hexSpacedToBinary($this->wireDataHex);

            return new BoltVector(
                new Bytes([chr($marker->value)]),
                new Bytes(self::binaryStringToByteArray($binary)),
            );
        }

        $boltMarker = $this->typeMarker !== null
            ? BoltTypeMarker::from($this->typeMarker->value)
            : null;

        return BoltVector::encode($this->values, $boltMarker);
    }

    private static function normalizeHexString(string $dataHexSpaced): string
    {
        $trimmed = trim($dataHexSpaced);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return '';
        }

        return implode(' ', array_map(static function (string $p): string {
            return strtolower($p);
        }, $parts));
    }

    /**
     * @return list<string>
     */
    private static function binaryStringToByteArray(string $binary): array
    {
        if ($binary === '') {
            return [];
        }

        /** @var list<string> */
        return str_split($binary, 1);
    }

    private static function hexSpacedToBinary(string $normalizedHex): string
    {
        if ($normalizedHex === '') {
            return '';
        }

        $parts = explode(' ', $normalizedHex);
        $out = '';
        foreach ($parts as $p) {
            if (strlen($p) !== 2 || !ctype_xdigit($p)) {
                throw new InvalidArgumentException('Invalid hex byte in vector data: '.$p);
            }
            $out .= chr((int) hexdec($p));
        }

        return $out;
    }

    private static function dtypeStringToBoltMarker(string $dtype): BoltTypeMarker
    {
        return match ($dtype) {
            'i8' => BoltTypeMarker::INT_8,
            'i16' => BoltTypeMarker::INT_16,
            'i32' => BoltTypeMarker::INT_32,
            'i64' => BoltTypeMarker::INT_64,
            'f32' => BoltTypeMarker::FLOAT_32,
            'f64' => BoltTypeMarker::FLOAT_64,
            default => throw new InvalidArgumentException('Unknown vector dtype: '.$dtype),
        };
    }

    public static function markerByteToDtypeString(int $byte): string
    {
        return match ($byte) {
            0xC8 => 'i8',
            0xC9 => 'i16',
            0xCA => 'i32',
            0xCB => 'i64',
            0xC6 => 'f32',
            0xC1 => 'f64',
            default => throw new InvalidArgumentException('Unknown vector type marker byte: '.$byte),
        };
    }
}
