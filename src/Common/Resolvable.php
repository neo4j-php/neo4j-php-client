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

namespace Laudis\Neo4j\Common;

use function call_user_func;

/**
 * @template Resolved
 */
final class Resolvable
{
    /** @var Resolved|null */
    private $resolved;
    private bool $isResolved = false;
    /** @var callable():Resolved */
    private $toResolve;

    /** @var array<string, mixed> */
    private static array $cache = [];

    /**
     * @psalm-mutation-free
     *
     * @param callable():Resolved $toResolve
     */
    public function __construct($toResolve)
    {
        $this->toResolve = $toResolve;
    }

    /**
     * @pure
     *
     * @template U
     *
     * @param callable():U $toResolve
     *
     * @return Resolvable<U>
     */
    public static function once(string $key, $toResolve): Resolvable
    {
        /** @psalm-suppress MissingClosureReturnType */
        $tbr = static function () use ($key, $toResolve) {
            if (!array_key_exists($key, self::$cache)) {
                self::$cache[$key] = call_user_func($toResolve);
            }

            /** @var U */
            return self::$cache[$key];
        };

        /** @var self<U> */
        return new Resolvable($tbr);
    }

    /**
     * @return Resolved
     */
    public function resolve()
    {
        if (!$this->isResolved) {
            $this->resolved = call_user_func($this->toResolve);
            $this->isResolved = true;
        }

        return $this->resolved;
    }
}
