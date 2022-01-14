<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Enum;

use const E_DEPRECATED;
use function error_reporting;
use JsonSerializable;
use Laudis\TypedEnum\TypedEnum;

/**
 * Turn of error reporting for class definition. PHP Users of 8.1 receive a deprectation warning otherwise but
 * it is not fixable from the minimum version 7.4 as it required the "mixed" keyword.
 */
$oldReporting = error_reporting(error_reporting() & ~E_DEPRECATED);

/**
 * @method static self ENABLE()
 * @method static self DISABLE()
 * @method static self FROM_URL()
 * @method static self ENABLE_WITH_SELF_SIGNED()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class SslMode extends TypedEnum implements JsonSerializable
{
    private const ENABLE = 'enable';
    private const ENABLE_WITH_SELF_SIGNED = 'enable_with_self_signed';
    private const DISABLE = 'disable';
    private const FROM_URL = 'from_url';

    public function __toString()
    {
        /** @noinspection MagicMethodsValidityInspection */
        return $this->getValue();
    }

    public function jsonSerialize()
    {
        return $this->getValue();
    }
}

/**
 * Turn back on old error reporting after class definition.
 */
error_reporting($oldReporting);
