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

namespace Laudis\Neo4j\Basic;

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\DriverFactory;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Psr\Http\Message\UriInterface;

final class Driver implements DriverInterface
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private readonly DriverInterface $driver,
    ) {
    }

    /**
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): Session
    {
        return new Session($this->driver->createSession($config));
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        return $this->driver->verifyConnectivity($config);
    }

    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null): self
    {
        $driver = DriverFactory::create($uri, $configuration, $authenticate, SummarizedResultFormatter::create());

        return new self($driver);
    }

    public function closeConnections(): void
    {
        $this->driver->closeConnections();
    }
}
