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
$oldReporting = error_reporting(error_reporting() & (~E_DEPRECATED));

/**
 * Defines the access mode of a connection.
 *
 * @method static self ROLLBACK()
 * @method static self NONE()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class TransactionEffect extends TypedEnum implements JsonSerializable
{
    private const ROLLBACK = 'rollback';
    private const WRITE = 'none';

    public function jsonSerialize()
    {
        return $this->getValue();
    }
}

/**
 * Turn back on old error reporting after class definition.
 */
error_reporting($oldReporting);
