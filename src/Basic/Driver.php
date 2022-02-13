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

namespace Laudis\Neo4j\Basic;

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\DriverFactory;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\UriInterface;

/**
 * @implements DriverInterface<SummarizedResult<CypherMap>>
 */
final class Driver implements DriverInterface
{
    /** @var DriverInterface<SummarizedResult<CypherMap>> */
    private DriverInterface $driver;

    /**
     * @param DriverInterface<SummarizedResult<CypherMap>> $driver
     *
     * @psalm-external-mutation-free
     */
    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): Session
    {
        return new Session($this->driver->createSession());
    }

    public function verifyConnectivity(): bool
    {
        return $this->driver->verifyConnectivity();
    }

    /**
     * @param string|UriInterface $uri
     * @pure
     */
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null): self
    {
        /** @var DriverInterface<SummarizedResult<CypherMap>> */
        $driver = DriverFactory::create($uri, $configuration, $authenticate, SummarizedResultFormatter::create());

        return new self($driver);
    }
}
