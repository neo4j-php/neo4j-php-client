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

namespace Laudis\Neo4j\Network\Bolt;

use Bolt\Bolt;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\ConnectionManager;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverConfigurationInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;

/**
 * @template T
 *
 * @psalm-type ParsedUrl = array{host: string, pass: string|null, path: string, port: int, query: array<string,string>, scheme: string, user: string|null}
 *
 * @implements DriverInterface<T>
 */
final class BoltDriver implements DriverInterface
{
    /** @var ParsedUrl */
    private array $parsedUrl;
    private AuthenticateInterface $auth;
    private ConnectionManager $manager;
    /** @var DriverConfigurationInterface<T> */
    private DriverConfigurationInterface $config;

    /**
     * @param ParsedUrl                       $parsedUrl
     * @param DriverConfigurationInterface<T> $config
     */
    public function __construct(array $parsedUrl, AuthenticateInterface $auth, ConnectionManager $manager, DriverConfigurationInterface $config)
    {
        $this->parsedUrl = $parsedUrl;
        $this->auth = $auth;
        $this->manager = $manager;
        $this->config = $config;
    }

    /**
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= $this->config->getSessionConfiguration();

        return new Session($this, $config);
    }

    public function acquireConnection(SessionConfiguration $configuration): Bolt
    {
        try {
            $bolt = new Bolt($this->manager->acquireBoltConnection($this->parsedUrl));
            $this->auth->authenticateBolt($bolt, $this->parsedUrl, $this->config->getUserAgent());
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError($e->getMessage(), '')]), $e);
        }

        return $bolt;
    }

    public function withUserAgent($userAgent): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->manager, $this->config->withUserAgent($userAgent));
    }

    public function withSessionConfiguration(?SessionConfiguration $configuration): DriverInterface
    {
        $driverConfiguration = $this->config->withSessionConfiguration($configuration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $driverConfiguration);
    }

    public function withTransactionConfiguration(?TransactionConfiguration $configuration): DriverInterface
    {
        $transactionConfiguration = $this->config->getTransactionConfiguration()->merge($configuration);
        $driverConfiguration = $this->config->withTransactionConfiguration($transactionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $driverConfiguration);
    }

    public function withConfiguration(DriverConfigurationInterface $configuration): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
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
        $transactionConfiguration = $this->config->getTransactionConfiguration()->withFormatter($formatter);
        $configuration = $this->config->withTransactionConfiguration($transactionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
    }

    public function withTransactionTimeout($timeout): DriverInterface
    {
        $transactionConfiguration = $this->config->getTransactionConfiguration()->withTimeout($timeout);
        $configuration = $this->config->withTransactionConfiguration($transactionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
    }

    public function withDatabase($database): DriverInterface
    {
        $sessionConfiguration = $this->config->getSessionConfiguration()->withDatabase($database);
        $configuration = $this->config->withSessionConfiguration($sessionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
    }

    public function withFetchSize($fetchSize): DriverInterface
    {
        $sessionConfiguration = $this->config->getSessionConfiguration()->withFetchSize($fetchSize);
        $configuration = $this->config->withSessionConfiguration($sessionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
    }

    public function withAccessMode($accessMode): DriverInterface
    {
        $sessionConfiguration = $this->config->getSessionConfiguration()->withAccessMode($accessMode);
        $configuration = $this->config->withSessionConfiguration($sessionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->manager, $configuration);
    }

    public function getConfiguration(): DriverConfigurationInterface
    {
        return $this->config;
    }
}
