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

use function in_array;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Log\LoggerInterface;

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
        private SessionConfiguration $defaultSessionConfig,
        /** @psalm-readonly */
        private TransactionConfiguration $defaultTransactionConfig,
        private DriverSetupManager $driverSetups,
    ) {}

    /**
     * Creates a client builder with default configurations and an OGMFormatter.
     *
     * @return ClientBuilder<SummarizedResult<CypherMap<OGMTypes>>>
     */
    public static function create(?string $logLevel = null, ?LoggerInterface $logger = null): ClientBuilder
    {
        $configuration = DriverConfiguration::default();
        if ($logLevel !== null && $logger !== null) {
            $configuration = $configuration->withLogger($logLevel, $logger);
        }

        return new self(
            SessionConfiguration::default(),
            TransactionConfiguration::default(),
            new DriverSetupManager(SummarizedResultFormatter::create(), $configuration)
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

        $authentication ??= Authenticate::fromUrl($uri, $this->driverSetups->getLogger());

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
     * @return self<T>
     *
     * @psalm-mutation-free
     */
    public function withDefaultDriverConfiguration(DriverConfiguration $config): self
    {
        $tbr = clone $this;

        $tbr->driverSetups = $tbr->driverSetups->withDriverConfiguration($config);

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
