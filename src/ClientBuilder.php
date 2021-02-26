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
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Http\HttpConfig;
use Laudis\Neo4j\Network\Http\HttpDriver;

/**
 * @template T
 * @psalm-immutable
 */
final class ClientBuilder
{
    private ?string $default;
    /** @var Map<string, DriverInterface> */
    private Map $connectionPool;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private string $userAgent;

    /**
     * @param Map<string, DriverInterface> $connectionPool
     * @param FormatterInterface<T>        $formatter
     */
    private function __construct(Map $connectionPool, ?string $default, FormatterInterface $formatter, string $userAgent)
    {
        $this->connectionPool = $connectionPool;
        $this->default = $default;
        $this->formatter = $formatter;
        $this->userAgent = $userAgent;
    }

    /**
     * @return ClientBuilder<Vector<Map<string, scalar|array|null>>>
     */
    public static function create(): ClientBuilder
    {
        return new self(new Map(), null, new BasicFormatter(), 'LaudisNeo4j/'.ClientInterface::VERSION);
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     */
    public function addBoltConnection(string $alias, string $url, BoltConfig $provider = null): self
    {
        $parse = $this->assertCorrectUrl($url);
        $pool = new Map(array_merge(
                $this->connectionPool->toArray(),
                [$alias => new BoltDriver($parse, $provider ?? new BoltConfig(), $this->userAgent)])
        );

        return new self($pool, $this->default, $this->formatter, $this->userAgent);
    }

    /**
     * Adds a new http connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     */
    public function addHttpConnection(string $alias, string $url, HttpConfig $injections = null): self
    {
        $parse = $this->assertCorrectUrl($url);

        $injections = $injections ?? new HttpConfig();
        $connection = new HttpDriver($parse, $injections, $this->userAgent);
        $pool = new Map(array_merge(
            $this->connectionPool->toArray(),
            [$alias => $connection]
        ));

        return new self($pool, $this->default, $this->formatter, $this->userAgent);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     */
    public function setDefaultConnection(string $alias): self
    {
        return new self($this->connectionPool->copy(), $alias, $this->formatter, $this->userAgent);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return ClientBuilder<U>
     */
    public function setFormatter(FormatterInterface $formatter): self
    {
        return new self($this->connectionPool->copy(), $this->default, $formatter, $this->userAgent);
    }

    /**
     * @return self<T>
     */
    public function setUserAgent(string $userAgent): self
    {
        return new self($this->connectionPool->copy(), $this->default, $this->formatter, $userAgent);
    }

    /**
     * @return ClientInterface<T>
     */
    public function build(): ClientInterface
    {
        if ($this->connectionPool->isEmpty()) {
            throw new BadMethodCallException('Client cannot be built with an empty connectionpool');
        }
        $default = $this->default ?? $this->connectionPool->first()->key;
        if (!$this->connectionPool->hasKey($default)) {
            $format = 'Client cannot be built with a default connection "%s" that is not in the connection pool';
            throw new BadMethodCallException(sprintf($format, $default));
        }

        return new Client($this->connectionPool, $default, $this->formatter);
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
