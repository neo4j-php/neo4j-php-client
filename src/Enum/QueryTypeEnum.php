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

enum QueryTypeEnum : string
{
    case READ_ONLY = 'r';
    case READ_WRITE = 'w';
    case SCHEMA_ONLY = 's';
    case WRITE_ONLY = 'rw';
}
