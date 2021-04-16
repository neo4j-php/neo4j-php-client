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

namespace Laudis\Neo4j\Network\Http;

use Exception;
use Laudis\Neo4j\ConnectionManager;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverConfigurationInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Network\VersionDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

/**
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class HttpDriver implements DriverInterface
{
    /** @var ParsedUrl */
    private array $parsedUrl;
    private HttpPsrBindings $bindings;
    private AuthenticateInterface $auth;
    private DriverConfigurationInterface $config;
    private ConnectionManager $manager;

    /**
     * @param ParsedUrl                       $parsedUrl
     * @param DriverConfigurationInterface<T> $config
     */
    public function __construct(
        array $parsedUrl,
        HttpPsrBindings $bindings,
        DriverConfigurationInterface $config,
        AuthenticateInterface $auth,
        ConnectionManager $manager
    ) {
        $this->parsedUrl = $parsedUrl;
        $this->bindings = $bindings;
        $this->auth = $auth;
        $this->config = $config;
        $this->manager = $manager;
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= $this->config->getSessionConfiguration();
        $url = sprintf('%s://%s:%s%s',
            $this->parsedUrl['scheme'],
            $this->parsedUrl['host'],
            $this->parsedUrl['port'],
            $this->parsedUrl['path']
        );

        $factory = $this->bindings->getRequestFactory();

        $request = $factory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', $this->config->getUserAgent());

        $request = $this->auth->authenticateHttp($request, $this->parsedUrl);

        $tsx = (new VersionDiscovery($this->bindings->getClient()))->discoverTransactionUrl($request, $config->getDatabase());

        $request = $factory->createRequest('POST', $tsx)
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', $this->config->getUserAgent());

        $request = $this->auth->authenticateHttp($request, $this->parsedUrl);

        return new HttpSession(
            $request,
            $this->bindings->getStreamFactory(),
            $this,
            $config
        );
    }

    public function acquireConnection(SessionConfiguration $configuration): ClientInterface
    {
        return $this->manager->acquireHttpConnection();
    }

    public function withUserAgent($userAgent): DriverInterface
    {
        return new self($this->parsedUrl, $this->bindings, $this->config->withUserAgent($userAgent), $this->auth, $this->manager);
    }

    public function withSessionConfiguration(?SessionConfiguration $configuration): DriverInterface
    {
        return new self($this->parsedUrl, $this->bindings, $this->config->withSessionConfiguration($configuration), $this->auth, $this->manager);
    }

    public function withTransactionConfiguration(?TransactionConfiguration $configuration): DriverInterface
    {
        $transactionConfig = $this->config->getTransactionConfiguration()->merge($configuration);
        $driverConfig = $this->config->withTransactionConfiguration($transactionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function withConfiguration(DriverConfigurationInterface $configuration): DriverInterface
    {
        return new self($this->parsedUrl, $this->bindings, $configuration, $this->auth, $this->manager);
    }

    public function getTransactionConfiguration(): StaticTransactionConfiguration
    {
        return $this->config->getTransactionConfiguration();
    }

    public function getSessionConfiguration(): SessionConfiguration
    {
        return $this->config->getSessionConfiguration();
    }

    public function withFormatter($formatter): DriverInterface
    {
        $transactionConfig = $this->config->getTransactionConfiguration()->withFormatter($formatter);
        $driverConfig = $this->config->withTransactionConfiguration($transactionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function withTransactionTimeout($timeout): DriverInterface
    {
        $transactionConfig = $this->config->getTransactionConfiguration()->withTimeout($timeout);
        $driverConfig = $this->config->withTransactionConfiguration($transactionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function withDatabase($database): DriverInterface
    {
        $sessionConfig = $this->config->getSessionConfiguration()->withDatabase($database);
        $driverConfig = $this->config->withSessionConfiguration($sessionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function withFetchSize($fetchSize): DriverInterface
    {
        $sessionConfig = $this->config->getSessionConfiguration()->withFetchSize($fetchSize);
        $driverConfig = $this->config->withSessionConfiguration($sessionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function withAccessMode($accessMode): DriverInterface
    {
        $sessionConfig = $this->config->getSessionConfiguration()->withAccessMode($accessMode);
        $driverConfig = $this->config->withSessionConfiguration($sessionConfig);

        return new self($this->parsedUrl, $this->bindings, $driverConfig, $this->auth, $this->manager);
    }

    public function getConfiguration(): DriverConfigurationInterface
    {
        return $this->config;
    }
}
