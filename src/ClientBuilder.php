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

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\ClientConfiguration;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Network\Bolt\BoltConfiguration;
use Laudis\Neo4j\Network\Http\HttpConfig;

/**
 * @template T
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 *
 * @deprecated use the client itself to configure it
 * @see Client::create()
 */
final class ClientBuilder
{
    /** @var Client<T> */
    private Client $client;

    /**
     * @param Client<T> $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return ClientBuilder<Vector<Map<string, scalar|array|null>>>
     */
    public static function create(): ClientBuilder
    {
        return new self(new Client(new Map(), ClientConfiguration::default()));
    }

    /**
     * @return self<T>
     */
    public function withDriver(string $alias, string $url, ?AuthenticateInterface $authentication = null): self
    {
        return new self($this->client->withDriver($alias, $url, $authentication));
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @see Client::withDriver()
     */
    public function addBoltConnection(string $alias, string $url, BoltConfiguration $config = null): self
    {
        $config ??= BoltConfiguration::create();
        $parsedUrl = ConnectionManager::parseUrl($url);
        $options = $config->getSslContextOptions();
        $postScheme = '';
        if ($options !== []) {
            if (($options['allow_self_signed'] ?? false) === true) {
                $postScheme = '+ssc';
            } else {
                $postScheme = '+s';
            }
        }

        $parsedUrl['query']['database'] ??= $config->getDatabase();

        if ($config->hasAutoRouting()) {
            $parsedUrl['scheme'] = 'neo4j'.$postScheme;
        } else {
            $parsedUrl['scheme'] = 'bolt'.$postScheme;
        }

        return new self($this->client->withParsedUrl($alias, $parsedUrl, Authenticate::fromUrl()));
    }

    /**
     * Adds a new http connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @see ClientBuilder::withDriver()
     *
     * @psalm-suppress DeprecatedClass
     */
    public function addHttpConnection(string $alias, string $url, HttpConfig $config = null): self
    {
        $config ??= HttpConfig::create();

        $bindings = new HttpPsrBindings($config->getClient(), $config->getStreamFactory(), $config->getRequestFactory());
        $client = $this->client->withHttpPsrBindings($bindings);

        $parsedUrl = ConnectionManager::parseUrl($url);

        $parsedUrl['scheme'] = $bindings->getRequestFactory()->createRequest('GET', '')->getUri()->getScheme();
        $parsedUrl['scheme'] = $parsedUrl['scheme'] === '' ? 'http' : '';
        $parsedUrl['port'] = $parsedUrl['port'] === 7687 ? 7474 : $parsedUrl['port'];

        return new self($client->withParsedUrl($alias, $parsedUrl, Authenticate::fromUrl()));
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
        return new self($this->client->withDefaultDriver($alias));
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     */
    public function withDefaultDriver(string $alias): self
    {
        return new self($this->client->withDefaultDriver($alias));
    }

    /**
     * @template U
     *
     * @param callable():FormatterInterface<U>|FormatterInterface<U> $formatter
     *
     * @return self<U>
     */
    public function withFormatter($formatter): self
    {
        return new self($this->client->withFormatter($formatter));
    }

    /**
     * @param callable():(\Laudis\Neo4j\Enum\AccessMode|null)|\Laudis\Neo4j\Enum\AccessMode|null $accessMode
     *
     * @return self<T>
     */
    public function withUserAgent(string $userAgent): self
    {
        return new self($this->client->withUserAgent($userAgent));
    }

    /**
     * @return ClientInterface<T>
     */
    public function build(): ClientInterface
    {
        return $this->client;
    }

    public function withHttpPsrBindings(HttpPsrBindings $bindings): self
    {
        return new self($this->client->withHttpPsrBindings($bindings));
    }
}
