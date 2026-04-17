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

/**
 * Psalm anchor for the Bolt/OGM value union. Defined in the Types namespace so
 * unqualified class names in the OGMTypes union resolve to Laudis\Neo4j\Types\*.
 *
 * @psalm-type OGMTypes = string|int|float|bool|null|\Laudis\Neo4j\Types\Date|\Laudis\Neo4j\Types\DateTime|\Laudis\Neo4j\Types\Duration|\Laudis\Neo4j\Types\LocalDateTime|\Laudis\Neo4j\Types\LocalTime|\Laudis\Neo4j\Types\Time|\Laudis\Neo4j\Types\Node|\Laudis\Neo4j\Types\Relationship|\Laudis\Neo4j\Types\Path|\Laudis\Neo4j\Types\Cartesian3DPoint|\Laudis\Neo4j\Types\CartesianPoint|\Laudis\Neo4j\Types\WGS84Point|\Laudis\Neo4j\Types\WGS843DPoint|\Laudis\Neo4j\Types\DateTimeZoneId|\Laudis\Neo4j\Types\Vector|\Laudis\Neo4j\Types\UnsupportedType|\Laudis\Neo4j\Types\CypherList<mixed>|\Laudis\Neo4j\Types\CypherMap<mixed>
 */
final class OGMTypesAlias
{
    private function __construct()
    {
    }
}
