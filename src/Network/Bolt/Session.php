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

use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\HttpDriver\BoltUnmanagedTransaction;
use Laudis\Neo4j\Neo4jDriver;
use function microtime;

/**
 * @template T
 * @implements SessionInterface<T>
 */
final class Session implements SessionInterface
{
    private SessionConfiguration $config;
    /** @var BoltDriver<T>|Neo4jDriver<T> */
    private $driver;

    /**
     * @param BoltDriver<T>|Neo4jDriver<T> $driver
     */
    public function __construct($driver, SessionConfiguration $config)
    {
        $this->config = $config;
        $this->driver = $driver;
    }

    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): Vector
    {
        return $this->openTransaction($statements)->commit();
    }

    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement])->first();
    }

    public function run(string $statement, iterable $parameters, ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->retry($tsxHandler, $this->driver->getTransactionConfiguration()->merge($config));
    }

    /**
     * @template U
     *
     * @param callable(TransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    private function retry(callable $tsxHandler, StaticTransactionConfiguration $config)
    {
        $timeout = $config->getTimeout();
        if ($timeout) {
            $limit = microtime(true) + $timeout;
        } else {
            $limit = PHP_FLOAT_MAX;
        }
        $tbr = null;
        $continue = true;
        while ($continue) {
            try {
                $transaction = $this->openTransaction();
                $tbr = $tsxHandler($transaction);
                $transaction->commit();

                $continue = false;
            } catch (Neo4jException $e) {
                if (microtime(true) > $limit) {
                    throw $e;
                }
            }
        }

        return $tbr;
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function getConfig(): SessionConfiguration
    {
        return $this->config;
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        try {
            $bolt = $this->driver->acquireConnection($this->config);
            if (!$bolt->begin(['db' => $this->config->getDatabase()])) {
                throw new Neo4jException(new Vector([new Neo4jError('', 'Cannot open new transaction')]));
            }
        } catch (Exception $e) {
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        $tsxConfig = $this->driver->getTransactionConfiguration()->merge($config);
        $tsx = new BoltUnmanagedTransaction($this->config, $tsxConfig, $bolt);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }

    public function getTransactionConfig(): StaticTransactionConfiguration
    {
        return $this->driver->getTransactionConfiguration();
    }

    public function withFormatter($formatter): SessionInterface
    {
        return new self($this->driver->withFormatter($formatter), $this->config);
    }

    public function withTransactionTimeout($timeout): SessionInterface
    {
        return new self($this->driver->withTransactionTimeout($timeout), $this->config);
    }

    public function withDatabase($database): SessionInterface
    {
        return new self($this->driver->withDatabase($database), $this->config);
    }

    public function withFetchSize($fetchSize): SessionInterface
    {
        return new self($this->driver->withFetchSize($fetchSize), $this->config);
    }

    public function withAccessMode($accessMode): SessionInterface
    {
        return new self($this->driver->withAccessMode($accessMode), $this->config);
    }

    public function withBookmarks($bookmarks): SessionInterface
    {
        return new self($this->driver, $this->config->withBookmarks($bookmarks));
    }

    public function withConfiguration(SessionConfiguration $configuration): SessionInterface
    {
        return new self($this->driver, $configuration);
    }

    public function withTransactionConfiguration(?TransactionConfiguration $configuration): SessionInterface
    {
        return new self($this->driver->withTransactionConfiguration($configuration), $this->config);
    }
}
