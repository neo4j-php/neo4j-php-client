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

namespace Laudis\Neo4j\Enum;

use JsonSerializable;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\TypedEnum\TypedEnum;
use Stringable;

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
 * @extends TypedEnum<string>
 *
 * @psalm-suppress MutableDependency
 */
final class QueryTypeEnum extends TypedEnum implements JsonSerializable, Stringable
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

    public function __toString(): string
    {
        return $this->getValue();
    }

    public function jsonSerialize(): string
    {
        return $this->getValue();
    }
}
