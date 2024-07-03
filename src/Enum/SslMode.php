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
use Laudis\TypedEnum\TypedEnum;
use Stringable;

enum SslMode : string
{
    case ENABLE = 'enable';
    case ENABLE_WITH_SELF_SIGNED = 'enable_with_self_signed';
    case DISABLE = 'disable';

    case FROM_URL = 'from_url';
}
