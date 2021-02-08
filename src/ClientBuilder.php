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

namespace Laudis\Neo4j;

use BadMethodCallException;
use Ds\Map;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Formatter\HttpCypherFormatter;
use Laudis\Neo4j\HttpDriver\RequestFactory;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use Laudis\Neo4j\Network\Http\HttpDriver;
use Laudis\Neo4j\Network\Http\HttpInjections;
use Laudis\Neo4j\Network\VersionDiscovery;

final class ClientBuilder
{
    private ?string $default = null;

    /** @var Map<string, DriverInterface> */
    private Map $connectionPool;

    public function __construct()
    {
        $this->connectionPool = new Map();
    }

    public static function create(): ClientBuilder
    {
        return new self();
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     */
    public function addBoltConnection(string $alias, string $url, BoltInjections $provider = null): ClientBuilder
    {
        $parse = $this->assertCorrectUrl($url);
        $this->connectionPool->put($alias, new BoltDriver($parse, $provider ?? new BoltInjections()));

        return $this;
    }

    /**
     * Adds a new http connection with the given alias and over t he provided url. The configuration will be merged with the one in the client, if provided.
     */
    public function addHttpConnection(string $alias, string $url, HttpInjections $injections = null): ClientBuilder
    {
        $parse = $this->assertCorrectUrl($url);
        $injections = $injections ?? new HttpInjections();
        $factory = $injections->requestFactory();
        $requestFactory = new RequestFactory($factory, $injections->streamFactory(), new HttpCypherFormatter());
        $connection = new HttpDriver($parse, new VersionDiscovery($requestFactory, $injections->client()), $injections);
        $this->connectionPool->put($alias, $connection);

        return $this;
    }

    /**
     * Sets the default connection to the given alias.
     */
    public function setDefaultConnection(string $alias): self
    {
        $this->default = $alias;

        return $this;
    }

    public function build(): ClientInterface
    {
        if ($this->connectionPool->isEmpty()) {
            throw new BadMethodCallException('Client cannot be built with an empty connectionpool');
        }
        if ($this->default === null) {
            $this->default = $this->connectionPool->first()->key;
        }
        if (!$this->connectionPool->hasKey($this->default)) {
            $format = 'Client cannot be built with a default connection "%s" that is not in the connection pool';
            throw new BadMethodCallException(sprintf($format, $this->default));
        }

        return new Client($this->connectionPool, $this->default);
    }

    /**
     * @return array{host:string, user:string, pass:string, scheme:string}
     */
    private function assertCorrectUrl(string $url): array
    {
        $parse = parse_url($url);
        if (!isset($parse['host'], $parse['user'], $parse['pass'], $parse['scheme'])) {
            throw new InvalidArgumentException('The provided url must have a parsed host, user, pass and scheme value');
        }

        return $parse;
    }
}
