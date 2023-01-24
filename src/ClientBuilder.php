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

namespace Laudis\Neo4j;

use function http_build_query;
use function in_array;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConfiguration;
use Laudis\Neo4j\Common\DriverSetupManager;
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
 * @psalm-import-type OGMTypes from OGMFormatter
 */
final class ClientBuilder
{
    public const SUPPORTED_SCHEMES = ['', 'bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc', 'http', 'https'];

    /**
     * @psalm-mutation-free
     *
     * @param DriverSetupManager<T> $driverSetups
     */
    public function __construct(
        /** @psalm-readonly */
        private DriverConfiguration $defaultDriverConfig,
        /** @psalm-readonly */
        private SessionConfiguration $defaultSessionConfig,
        /** @psalm-readonly */
        private TransactionConfiguration $defaultTransactionConfig,
        private DriverSetupManager $driverSetups
    ) {}

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
            new DriverSetupManager(SummarizedResultFormatter::create(), DriverConfiguration::default())
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @return self<T>
     */
    public function withDriver(string $alias, string $url, ?AuthenticateInterface $authentication = null, ?int $priority = 0): self
    {
        $uri = Uri::create($url);

        $authentication ??= Authenticate::fromUrl($uri);

        return $this->withParsedUrl($alias, $uri, $authentication, $priority ?? 0);
    }

    /**
     * @psalm-external-mutation-free
     *
     * @return self<T>
     */
    private function withParsedUrl(string $alias, Uri $uri, AuthenticateInterface $authentication, int $priority): self
    {
        $scheme = $uri->getScheme();

        if (!in_array($scheme, self::SUPPORTED_SCHEMES, true)) {
            throw UnsupportedScheme::make($scheme, self::SUPPORTED_SCHEMES);
        }

        $tbr = clone $this;
        $tbr->driverSetups = $this->driverSetups->withSetup(new DriverSetup($uri, $authentication), $alias, $priority);

        return $tbr;
    }

    /**
     * Adds a new bolt connection with the given alias and over the provided url. The configuration will be merged with the one in the client, if provided.
     *
     * @return self<T>
     *
     * @deprecated
     *
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

        return $this->withParsedUrl($alias, $parsedUrl, Authenticate::fromUrl($parsedUrl), 0);
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

        $tbr = clone $this;
        $tbr->defaultDriverConfig = $this->defaultDriverConfig->withHttpPsrBindings($bindings);

        return $tbr->withParsedUrl($alias, $uri, Authenticate::fromUrl($uri), 0);
    }

    /**
     * Sets the default connection to the given alias.
     *
     * @return self<T>
     *
     * @deprecated
     * @see Driver::withDefaultDriver()
     *
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
     *
     * @psalm-mutation-free
     */
    public function withDefaultDriver(string $alias): self
    {
        $tbr = clone $this;
        $tbr->driverSetups = $this->driverSetups->withDefault($alias);

        return $tbr;
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return self<U>
     *
     * @psalm-mutation-free
     */
    public function withFormatter(FormatterInterface $formatter): self
    {
        return new self(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->driverSetups->withFormatter($formatter)
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
            $this->driverSetups,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
        );
    }

    /**
     * @deprecated
     * @see self::withDefaultDriverConfiguration
     */
    public function withHttpPsrBindings(HttpPsrBindings $bindings): self
    {
        $config = $this->defaultDriverConfig->withHttpPsrBindings($bindings);

        $tbr = clone $this;
        $tbr->defaultDriverConfig = $config;

        return $tbr;
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultDriverConfiguration(DriverConfiguration $config): self
    {
        $tbr = clone $this;
        $tbr->defaultDriverConfig = $config;

        return $tbr;
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultSessionConfiguration(SessionConfiguration $config): self
    {
        $tbr = clone $this;
        $tbr->defaultSessionConfig = $config;

        return $tbr;
    }

    /**
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultTransactionConfiguration(TransactionConfiguration $config): self
    {
        $tbr = clone $this;
        $tbr->defaultTransactionConfig = $config;

        return $tbr;
    }
}
