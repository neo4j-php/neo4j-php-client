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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Http\HttpConfig;
use Laudis\Neo4j\Types\CypherMap;

/**
 * Immutable factory for creating a client.
 *
 * @template T
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class ClientBuilder
{
    public const SUPPORTED_SCHEMES = ['', 'bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc', 'http', 'https'];

    /**
     * @psalm-readonly
     *
     * @var array<string, DriverSetup>
     */
    private array $driverConfigurations;
    /** @psalm-readonly */
    private DriverConfiguration $defaultDriverConfig;
    /** @psalm-readonly */
    private TransactionConfiguration $defaultTransactionConfig;
    /** @psalm-readonly */
    private SessionConfiguration $defaultSessionConfig;
    /** @psalm-readonly */
    private ?string $defaultDriver;
    /** @psalm-readonly */
    private FormatterInterface $formatter;

    /**
     * @psalm-mutation-free
     *
     * @param array<string, DriverSetup> $driverConfigurations
     * @param FormatterInterface<T>      $formatter
     */
    public function __construct(DriverConfiguration $configuration, SessionConfiguration $sessionConfiguration, TransactionConfiguration $transactionConfiguration, FormatterInterface $formatter, array $driverConfigurations, ?string $defaultDriver)
    {
        $this->driverConfigurations = $driverConfigurations;
        $this->defaultDriverConfig = $configuration;
        $this->defaultDriver = $defaultDriver;
        $this->formatter = $formatter;
        $this->defaultSessionConfig = $sessionConfiguration;
        $this->defaultTransactionConfig = $transactionConfiguration;
    }

    /**
     * Creates a client builder with default configurations and an OGMFormatter.
     *
     * @pure
     *
     * @return ClientBuilder<SummarizedResult<CypherMap<OGMTypes>>>
     */
    public static function create(): ClientBuilder
    {
        return new self(
            DriverConfiguration::default(),
            SessionConfiguration::default(),
            TransactionConfiguration::default(),
            SummarizedResultFormatter::create(),
            [],
            null
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @return self<T>
     */
    public function withDriver(string $alias, string $url, ?AuthenticateInterface $authentication = null): self
    {
        $uri = Uri::create($url);

        $authentication ??= Authenticate::fromUrl($uri);

        return $this->withParsedUrl($alias, $uri, $authentication);
    }

    /**
     * @psalm-mutation-free
     *
     * @return self<T>
     */
    private function withParsedUrl(string $alias, Uri $uri, AuthenticateInterface $authentication): self
    {
        $scheme = $uri->getScheme();

        if (!in_array($scheme, self::SUPPORTED_SCHEMES, true)) {
            throw UnsupportedScheme::make($scheme, self::SUPPORTED_SCHEMES);
        }

        $setup = new DriverSetup($uri, $authentication);
        $configs = array_merge($this->driverConfigurations, [$alias => $setup]);

        return new self(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $configs,
            $this->defaultDriver
        );
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
        /** @psalm-suppress ImpureMethodCall */
        $options = $config->getSslContextOptions();
        $postScheme = '';
        if ($options && $options !== []) {
            if (($options['allow_self_signed'] ?? false) === true) {
                $postScheme = '+ssc';
            } else {
                $postScheme = '+s';
            }
        }

        parse_str($parsedUrl->getQuery(), $query);
        /** @var array<string, string> */
        $query['database'] ??= $config->getDatabase();
        $parsedUrl = $parsedUrl->withPath(http_build_query($query));

        if ($config->hasAutoRouting()) {
            $parsedUrl = $parsedUrl->withScheme('neo4j'.$postScheme);
        } else {
            $parsedUrl = $parsedUrl->withScheme('bolt'.$postScheme);
        }

        return $this->withParsedUrl($alias, $parsedUrl, Authenticate::fromUrl($parsedUrl));
    }

    /**
     * Adds a new http connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     * @see Driver::withDriver()
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
            $this->defaultDriverConfig->withHttpPsrBindings($bindings),
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );

        return $self->withParsedUrl($alias, $uri, Authenticate::fromUrl($uri));
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     *
     * @deprecated
     * @see Driver::withDefaultDriver()
     * @psalm-mutation-free
     */
    public function setDefaultConnection(string $alias): self
    {
        return $this->withDefaultDriver($alias);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     * @psalm-mutation-free
     */
    public function withDefaultDriver(string $alias): self
    {
        return new self(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->driverConfigurations,
            $alias
        );
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return self<U>
     * @psalm-mutation-free
     */
    public function withFormatter(FormatterInterface $formatter): self
    {
        return new self(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );
    }

    /**
     * @return ClientInterface<T>
     *
     * @psalm-mutation-free
     */
    public function build(): ClientInterface
    {
        return new Client(
            $this->driverConfigurations,
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->defaultDriver
        );
    }

    /**
     * @deprecated
     * @see self::withDefaultDriverConfiguration
     *
     * @psalm-mutation-free
     */
    public function withHttpPsrBindings(HttpPsrBindings $bindings): self
    {
        $config = $this->defaultDriverConfig->withHttpPsrBindings($bindings);

        return new self(
            $config,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultDriverConfiguration(DriverConfiguration $config): self
    {
        return new self(
            $config,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultSessionConfiguration(SessionConfiguration $config): self
    {
        return new self(
            $this->defaultDriverConfig,
            $config,
            $this->defaultTransactionConfig,
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultTransactionConfiguration(TransactionConfiguration $config): self
    {
        return new self(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $config,
            $this->formatter,
            $this->driverConfigurations,
            $this->defaultDriver
        );
    }
}
