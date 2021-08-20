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

use JsonSerializable;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\TypedEnum\TypedEnum;

/**
 * Class QueryTypeEnum.
 *
 * @method static self READ_ONLY()
 * @method static self READ_WRITE()
 * @method static self SCHEMA_WRITE()
 * @method static self WRITE_ONLY()
 */
final class QueryTypeEnum extends TypedEnum implements JsonSerializable
{
    private const READ_ONLY = 'read_only';
    private const READ_WRITE = 'read_write';
    private const SCHEMA_WRITE = 'schema_write';
    private const WRITE_ONLY = 'write_only';

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
