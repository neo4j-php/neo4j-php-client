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

use function http_build_query;
use function in_array;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConfiguration;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Http\HttpConfig;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @template T
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class ClientBuilder
{
    public const SUPPORTED_SCHEMES = ['', 'bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc', 'http', 'https'];

    /** @var CypherMap<DriverSetup> */
    private CypherMap $driverConfigurations;
    private DriverConfiguration $configuration;
    private ?string $defaultDriver;
    private FormatterInterface $formatter;

    /**
     * @param CypherMap<DriverSetup> $driverConfigurations
     * @param FormatterInterface<T>  $formatter
     */
    public function __construct(DriverConfiguration $configuration, FormatterInterface $formatter, CypherMap $driverConfigurations, ?string $defaultDriver)
    {
        $this->driverConfigurations = $driverConfigurations;
        $this->configuration = $configuration;
        $this->defaultDriver = $defaultDriver;
        $this->formatter = $formatter;
    }

    /**
     * @return ClientBuilder<CypherList<CypherMap<OGMTypes>>>
     */
    public static function create(): ClientBuilder
    {
        return new self(DriverConfiguration::default(), OGMFormatter::create(), new CypherMap(), null);
    }

    /**
     * @return self<T>
     */
    public function withDriver(string $alias, string $url, ?AuthenticateInterface $authentication = null, ?float $socketTimeout = null): self
    {
        $authentication ??= Authenticate::fromUrl();
        $socketTimeout ??= TransactionConfiguration::DEFAULT_TIMEOUT;

        return $this->withParsedUrl($alias, Uri::create($url), $authentication, $socketTimeout);
    }

    /**
     * @return self<T>
     */
    private function withParsedUrl(string $alias, Uri $uri, AuthenticateInterface $authentication, float $socketTimeout): self
    {
        $scheme = $uri->getScheme();

        if (!in_array($scheme, self::SUPPORTED_SCHEMES, true)) {
            throw UnsupportedScheme::make($scheme, self::SUPPORTED_SCHEMES);
        }

        $setup = new DriverSetup($uri, $authentication, $socketTimeout);
        $configs = new CypherMap(array_merge($this->driverConfigurations->toArray(), [$alias => $setup]));

        return new self($this->configuration, $this->formatter, $configs, $this->defaultDriver);
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @psalm-suppress DeprecatedClass
     *
     * @see Client::withDriver()
     */
    public function addBoltConnection(string $alias, string $url, BoltConfiguration $config = null): self
    {
        $config ??= BoltConfiguration::create();
        $parsedUrl = Uri::create($url);
        $options = $config->getSslContextOptions();
        $postScheme = '';
        if ($options && $options !== []) {
            if (($options['allow_self_signed'] ?? false) === true) {
                $postScheme = '+ssc';
            } else {
                $postScheme = '+s';
            }
        }

        $query = [];
        parse_str($parsedUrl->getQuery(), $query);
        /** @var array<string, string> */
        $query['database'] ??= $config->getDatabase();
        $parsedUrl = $parsedUrl->withPath(http_build_query($query));

        if ($config->hasAutoRouting()) {
            $parsedUrl = $parsedUrl->withScheme('neo4j'.$postScheme);
        } else {
            $parsedUrl = $parsedUrl->withScheme('bolt'.$postScheme);
        }

        return $this->withParsedUrl($alias, $parsedUrl, Authenticate::fromUrl(), TransactionConfiguration::DEFAULT_TIMEOUT);
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

        $uri = Uri::create($url);

        $scheme = $bindings->getRequestFactory()->createRequest('GET', $uri)->getUri()->getScheme();
        $uri = $uri->withScheme($scheme === '' ? 'http' : $scheme);
        $uri = $uri->withPort($uri->getPort() === 7687 ? 7474 : $uri->getPort());

        $self = new self(
            $this->configuration->withHttpPsrBindings($bindings),
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );

        return $self->withParsedUrl($alias, $uri, Authenticate::fromUrl(), TransactionConfiguration::DEFAULT_TIMEOUT);
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
        return $this->withDefaultDriver($alias);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     */
    public function withDefaultDriver(string $alias): self
    {
        return new self($this->configuration, $this->formatter, $this->driverConfigurations, $alias);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return self<U>
     */
    public function withFormatter(FormatterInterface $formatter): self
    {
        return new self($this->configuration, $formatter, $this->driverConfigurations, $this->defaultDriver);
    }

    /**
     * @return ClientInterface<T>
     */
    public function build(): ClientInterface
    {
        return new Client($this->driverConfigurations, $this->configuration, $this->formatter, $this->defaultDriver);
    }

    public function withHttpPsrBindings(HttpPsrBindings $bindings): self
    {
        $config = $this->configuration->withHttpPsrBindings($bindings);

        return new self($config, $this->formatter, $this->driverConfigurations, $this->defaultDriver);
    }
}
