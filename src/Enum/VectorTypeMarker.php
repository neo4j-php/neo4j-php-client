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

/**
 * Type marker for Neo4j Vector element encoding (Bolt structure semantics).
 * Indicates how the vector payload is encoded (integer or float, and width).
 *
 * @see https://neo4j.com/docs/bolt/current/bolt/structure-semantics/#structure-vector
 */
enum VectorTypeMarker: int
{
    case INT_8 = 0xC8;
    case INT_16 = 0xC9;
    case INT_32 = 0xCA;
    case INT_64 = 0xCB;
    case FLOAT_32 = 0xC6;
    case FLOAT_64 = 0xC1;
}
