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

use Bolt\protocol\IStructure;
use Bolt\protocol\v6\structures\UnsupportedType as BoltUnsupportedType;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

/**
 * OGM type for Bolt UnsupportedType (Bolt 6+).
 *
 * @psalm-immutable
 */
final class UnsupportedType implements BoltConvertibleInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $minimumProtocol,
        private readonly ?string $message = null,
    ) {
    }

    public static function fromBolt(string $name, int $minimumProtocolMajor, int $minimumProtocolMinor, ?string $message): self
    {
        return new self($name, $minimumProtocolMajor.'.'.$minimumProtocolMinor, $message);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMinimumProtocol(): string
    {
        return $this->minimumProtocol;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function convertToBolt(): IStructure
    {
        $parts = explode('.', $this->minimumProtocol, 2);
        $major = (int) $parts[0];
        $minor = (int) ($parts[1] ?? '0');
        if ($major < 0 || $minor < 0) {
            throw new InvalidArgumentException('Invalid minimum protocol: '.$this->minimumProtocol);
        }

        $extra = [];
        if ($this->message !== null) {
            $extra['message'] = $this->message;
        }

        return new BoltUnsupportedType($this->name, $major, $minor, $extra);
    }
}
