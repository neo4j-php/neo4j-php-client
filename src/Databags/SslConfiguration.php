<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Enum\SslMode;

/**
 * @psalm-immutable
 */
final class SslConfiguration
{
    private SslMode $mode;
    private bool $verifyPeer;

    public function __construct(SslMode $mode, bool $verifyPeer)
    {
        $this->mode = $mode;
        $this->verifyPeer = $verifyPeer;
    }

    public function getMode(): SslMode
    {
        return $this->mode;
    }

    public function isVerifyPeer(): bool
    {
        return $this->verifyPeer;
    }

    /**
     * @pure
     */
    public static function create(SslMode $mode, bool $verifyPeer): self
    {
        return new self($mode, $verifyPeer);
    }

    /**
     * @pure
     */
    public static function default(): self
    {
        /** @psalm-suppress ImpureMethodCall */
        return self::create(SslMode::FROM_URL(), true);
    }

    public function withMode(SslMode $mode): self
    {
        $tbr = clone $this;
        $tbr->mode = $mode;

        return $tbr;
    }

    public function withVerifyPeer(bool $verify): self
    {
        $tbr = clone $this;
        $tbr->verifyPeer = $verify;

        return $tbr;
    }
}
