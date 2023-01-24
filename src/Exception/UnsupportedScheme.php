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

namespace Laudis\Neo4j\Exception;

use function implode;

use RuntimeException;

/**
 * Exception when a requested scheme cannot be handled by the drivers available in the client.
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class UnsupportedScheme extends RuntimeException
{
    /**
     * @param list<string> $supportedSchemas
     */
    public static function make(string $schema, array $supportedSchemas): self
    {
        return new self('Unsupported schema: '.$schema.', available schema\'s are: '.implode(',', $supportedSchemas));
    }
}
