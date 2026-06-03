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

namespace Laudis\Neo4j\Authentication;

/**
 * Normalizes Bolt SUCCESS metadata from the underlying bolt library into a stable shape.
 */
final class BoltAuthResponse
{
    /**
     * @param array<array-key, mixed> $content
     *
     * @return array{server: string, connection_id: string, hints: array<string, mixed>}
     */
    public static function normalize(array $content): array
    {
        return [
            'server' => array_key_exists('server', $content) && is_string($content['server']) ? $content['server'] : '',
            'connection_id' => array_key_exists('connection_id', $content) && is_string($content['connection_id']) ? $content['connection_id'] : '',
            'hints' => self::normalizeHints($content['hints'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeHints(mixed $rawHints): array
    {
        if (!is_array($rawHints)) {
            return [];
        }

        $normalizedHints = [];
        foreach ($rawHints as $key => $value) {
            if (!is_string($key) || !self::isSupportedHintValue($value)) {
                continue;
            }

            $normalizedHints[$key] = $value;
        }

        return $normalizedHints;
    }

    /**
     * @psalm-assert-if-true bool|float|int|string|array|null $value
     */
    private static function isSupportedHintValue(mixed $value): bool
    {
        return is_scalar($value) || $value === null || is_array($value);
    }
}
