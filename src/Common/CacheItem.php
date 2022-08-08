<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Common;

use DateTime;
use DateTimeInterface;
use function get_debug_type;
use InvalidArgumentException;
use function is_int;
use function microtime;
use const PHP_FLOAT_MAX;
use Psr\Cache\CacheItemInterface;
use function sprintf;

/**
 * @template T
 */
class CacheItem implements CacheItemInterface
{
    private float $expiresAt = PHP_FLOAT_MAX;
    private bool $isHit;
    private string $key;
    /** @var T */
    private $value;

    /**
     * @param T $value
     */
    public function __construct(string $key, $value, bool $isHit)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit && $this->expiresAt < microtime(true);
    }

    /**
     * @param T $value
     *
     * @return $this
     */
    public function set($value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        if ($expiration === null) {
            $this->expiresAt = PHP_FLOAT_MAX;
        } else {
            $this->expiresAt = (float) $expiration->format('U.u');
        }

        return $this;
    }

    public function expiresAfter($time): static
    {
        if ($time === null) {
            $this->expiresAt = PHP_FLOAT_MAX;
        } elseif ($time instanceof DateTimeInterface) {
            $this->expiresAt = (float) (new DateTime())->add($time)->format('U.u');
        } elseif (is_int($time)) {
            $this->expiresAt = microtime(true) + $time;
        } else {
            throw new InvalidArgumentException(sprintf('Expiration date must be an integer, a DateInterval or null, "%s" given.', get_debug_type($time)));
        }

        return $this;
    }
}
