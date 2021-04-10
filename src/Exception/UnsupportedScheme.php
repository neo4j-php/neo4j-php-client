<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Exception;

use RuntimeException;
use function implode;

final class UnsupportedScheme extends RuntimeException
{
    public static function make(string $schema, array $supportedSchemas): self
    {
        return new self('Unsupported schema: '.$schema.', available schema\'s are: '.implode(',', $supportedSchemas));
    }
}
