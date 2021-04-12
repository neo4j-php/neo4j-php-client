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
use Ds\Vector;
use function in_array;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Http\HttpConfig;
use Laudis\Neo4j\Network\Http\HttpDriver;
use function parse_url;

/**
 * @template T
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class ClientBuilder
{
    private ?string $default;
    /** @var Map<string, DriverInterface> */
    private Map $driverPool;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private string $userAgent;
    private HttpPsrBindings $bindings;

    /**
     * @param Map<string, DriverInterface> $driverPool
     * @param FormatterInterface<T>        $formatter
     */
    private function __construct(HttpPsrBindings $bindings, Map $driverPool, ?string $default, FormatterInterface $formatter, string $userAgent)
    {
        $this->bindings = $bindings;
        $this->driverPool = $driverPool;
        $this->default = $default;
        $this->formatter = $formatter;
        $this->userAgent = $userAgent;
    }

    /**
     * @return ClientBuilder<Vector<Map<string, scalar|array|null>>>
     */
    public static function create(): ClientBuilder
    {
        return new self(HttpPsrBindings::create(), new Map(), null, new BasicFormatter(), 'LaudisNeo4j/'.ClientInterface::VERSION);
    }

    public function withDriver(string $alias, string $uri, ?AuthenticateInterface $authentication = null): self
    {
        $parsedUrl = parse_url($uri);
        $scheme = $parsedUrl['scheme'] ?? 'bolt';
        $authentication ??= Authenticate::fromUri();

        if (in_array($scheme, ['bolt', 'bolt+s', 'bolt+ssc'])) {
            return $this->addBoltDriver($alias, $parsedUrl, $authentication);
        }

        if (in_array($scheme, ['neo4j', 'neo4j+s', 'neo4j+ssc'])) {
            return $this->addNeo4jDriver($alias, $parsedUrl, $authentication);
        }

        if (in_array($scheme, ['http', 'https'])) {
            return $this->addHttpDriver($alias, $parsedUrl, $authentication);
        }

        throw UnsupportedScheme::make($scheme, ['bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc', 'http', 'https']);
    }

    /**
     * @param ParsedUrl $parsedUrl
     */
    private function addHttpDriver(string $alias, array $parsedUrl, AuthenticateInterface $authenticate, ?string $defaultDatabase = null): self
    {
        $pool = $this->driverPool->copy();
        $pool->put($alias, new HttpDriver($parsedUrl, $this->bindings, $this->userAgent, $authenticate, $defaultDatabase ?? 'neo4j'));

        return new self($this->bindings, $pool, $this->default, $this->formatter, $this->userAgent);
    }

    /**
     * @param ParsedUrl $parsedUrl
     */
    private function addBoltDriver(string $alias, array $parsedUrl, AuthenticateInterface $authenticate, string $defaultDatabase = null): self
    {
        $pool = $this->driverPool->copy();
        $pool->put($alias, new BoltDriver($parsedUrl, $this->userAgent, $authenticate, new ConnectionManager(), $defaultDatabase ?? 'neo4j'));

        return new self($this->bindings, $pool, $this->default, $this->formatter, $this->userAgent);
    }

    /**
     * @param ParsedUrl $parsedUrl
     */
    private function addNeo4jDriver(string $alias, array $parsedUrl, AuthenticateInterface $authenticate, string $defaultDatabase = null): self
    {
        $pool = $this->driverPool->copy();
        $baseDriver = new BoltDriver($parsedUrl, $this->userAgent, $authenticate, new ConnectionManager(), $defaultDatabase ?? 'neo4j');
        $driver = new Neo4jDriver($parsedUrl, $this->userAgent, $authenticate, $baseDriver, $defaultDatabase ?? 'neo4j');

        $pool->put($alias, $driver);

        return new self($this->bindings, $pool, $this->default, $this->formatter, $this->userAgent);
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @see ClientBuilder::addConnection()
     */
    public function addBoltConnection(string $alias, string $url, BoltConfig $config = null): self
    {
        $config ??= BoltConfig::create();
        $parsedUrl = parse_url($url);
        $options = $config->getSslContextOptions();
        $postScheme = '';
        if ($options !== []) {
            if (($options['allow_self_signed'] ?? false) === true) {
                $postScheme = '+ssc';
            } else {
                $postScheme = '+s';
            }
        }

        if ($config->hasAutoRouting()) {
            $parsedUrl['scheme'] = 'neo4j'.$postScheme;

            return $this->addNeo4jDriver($alias, $parsedUrl, Authenticate::fromUri(), $config->getDatabase());
        }

        $parsedUrl['scheme'] = 'bolt'.$postScheme;

        return $this->addBoltDriver($alias, $parsedUrl, Authenticate::fromUri(), $config->getDatabase());
    }

    /**
     * Adds a new http connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @see ClientBuilder::withDriver()
     */
    public function addHttpConnection(string $alias, string $url, HttpConfig $config = null): self
    {
        $config ??= HttpConfig::create();
        $bindings = new HttpPsrBindings($config->getClient(), $config->getStreamFactory(), $config->getRequestFactory());

        return $this->addHttpDriver($alias, parse_url($url), Authenticate::fromUri(), $config->getDatabase())->withHttpPsrBindings($bindings);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     *
     * @deprecated
     * @see ClientBuilder::withDefaultDriver()
     */
    public function setDefaultConnection(string $alias): self
    {
        return new self($this->bindings, $this->driverPool->copy(), $alias, $this->formatter, $this->userAgent);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     */
    public function withDefaultDriver(string $alias): self
    {
        return new self($this->bindings, $this->driverPool->copy(), $alias, $this->formatter, $this->userAgent);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return ClientBuilder<U>
     */
    public function withFormatter(FormatterInterface $formatter): self
    {
        return new self($this->bindings, $this->driverPool->copy(), $this->default, $formatter, $this->userAgent);
    }

    /**
     * @return self<T>
     */
    public function withUserAgent(string $userAgent): self
    {
        return new self($this->bindings, $this->driverPool->copy(), $this->default, $this->formatter, $userAgent);
    }

    /**
     * @return ClientInterface<T>
     */
    public function build(): ClientInterface
    {
        if ($this->driverPool->isEmpty()) {
            throw new BadMethodCallException('Client cannot be built with an empty driver pool');
        }
        $default = $this->default ?? $this->driverPool->first()->key;
        if (!$this->driverPool->hasKey($default)) {
            $format = 'Client cannot be built with a default connection "%s" that is not in the driver pool';
            throw new BadMethodCallException(sprintf($format, $default));
        }

        return new Client($this->driverPool, $default, $this->formatter);
    }

    public function withHttpPsrBindings(HttpPsrBindings $bindings): self
    {
        return new self($bindings, $this->driverPool, $this->default, $this->formatter, $this->userAgent);
    }
}
