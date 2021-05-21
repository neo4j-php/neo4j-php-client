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

namespace Laudis\Neo4j\Databags;

use function call_user_func;
use function is_callable;

final class DriverConfiguration
{
    public const DEFAULT_USER_AGENT = 'neo4j-php-client/2.0.0-alpha';

    /** @var callable():(string|null)|string|null */
    private $userAgent;
    /** @var callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $httpPsrBindings;

    /**
     * @param callable():(string|null)|string|null                   $userAgent
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     */
    public function __construct($userAgent, $httpPsrBindings)
    {
        $this->userAgent = $userAgent;
        $this->httpPsrBindings = $httpPsrBindings;
    }

    /**
     * @param callable():(string|null)|string|null                   $userAgent
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     */
    public static function create($userAgent, $httpPsrBindings): self
    {
        return new self($userAgent, $httpPsrBindings);
    }

    public static function default(): self
    {
        return new self(
            self::DEFAULT_USER_AGENT,
            HttpPsrBindings::default()
        );
    }

    public function getUserAgent(): string
    {
        $userAgent = (is_callable($this->userAgent)) ? call_user_func($this->userAgent) : $this->userAgent;

        return $userAgent ?? self::DEFAULT_USER_AGENT;
    }

    /**
     * @param callable():(string|null)|string|null $userAgent
     */
    public function withUserAgent($userAgent): self
    {
        return new self($userAgent, $this->httpPsrBindings);
    }

    /**
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $bindings
     */
    public function withHttpPsrBindings($bindings): self
    {
        return new self($this->userAgent, $bindings);
    }

    public function getHttpPsrBindings(): HttpPsrBindings
    {
        $bindings = (is_callable($this->httpPsrBindings)) ? call_user_func($this->httpPsrBindings) : $this->httpPsrBindings;

        return $bindings ?? HttpPsrBindings::default();
    }
}
