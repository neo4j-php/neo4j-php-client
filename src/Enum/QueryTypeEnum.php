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

namespace Laudis\Neo4j\Enum;

use const E_DEPRECATED;
use function error_reporting;
use JsonSerializable;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\TypedEnum\TypedEnum;

/**
 * Turn of error reporting for class definition. PHP Users of 8.1 receive a deprectation warning otherwise but
 * it is not fixable from the minimum version 7.4 as it required the "mixed" keyword.
 */
$oldReporting = error_reporting(error_reporting() & ~E_DEPRECATED);

/**
 * The actual type of query after is has been run.
 *
 * @method static self READ_ONLY()
 * @method static self READ_WRITE()
 * @method static self SCHEMA_WRITE()
 * @method static self WRITE_ONLY()
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class QueryTypeEnum extends TypedEnum implements JsonSerializable
{
    private const READ_ONLY = 'read_only';
    private const READ_WRITE = 'read_write';
    private const SCHEMA_WRITE = 'schema_write';
    private const WRITE_ONLY = 'write_only';

    /**
     * Decide the type of the query from the provided counters.
     *
     * @pure
     *
     * @psalm-suppress ImpureMethodCall
     */
    public static function fromCounters(SummaryCounters $counters): self
    {
        if ($counters->containsUpdates() || $counters->containsSystemUpdates()) {
            return self::READ_WRITE();
        }

        if ($counters->constraintsAdded() || $counters->constraintsRemoved() || $counters->indexesAdded() || $counters->indexesRemoved()) {
            return self::SCHEMA_WRITE();
        }

        return self::READ_ONLY();
    }

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
