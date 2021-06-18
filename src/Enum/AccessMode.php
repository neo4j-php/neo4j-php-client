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

use Laudis\TypedEnum\TypedEnum;

/**
 * @method static self READ()
 * @method static self WRITE()
 *
 * @extends TypedEnum<string>
 */
final class AccessMode extends TypedEnum
{
    private const READ = 'read';
    private const WRITE = 'write';
}
