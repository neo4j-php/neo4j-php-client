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
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\HttpDriver\BoltTransaction;
use function microtime;

/**
 * @template T
 * @implements SessionInterface<T>
 */
final class Session implements SessionInterface
{
    private FormatterInterface $formatter;
    private SessionConfiguration $config;
    /** @var DriverInterface<Bolt> */
    private DriverInterface $driver;

    /**
     * @param DriverInterface<Bolt> $driver
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(DriverInterface $driver, SessionConfiguration $injections, FormatterInterface $formatter)
    {
        $this->config = $injections;
        $this->driver = $driver;
        $this->formatter = $formatter;
    }

    public function runStatements(iterable $statements, ?TransactionConfig $config = null): Vector
    {
        return $this->openTransaction($statements)->commit($statements);
    }

    public function openTransaction(iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function runStatement(Statement $statement, ?TransactionConfig $config = null)
    {
        return $this->runStatements([$statement])->first();
    }

    public function run(string $statement, iterable $parameters, ?TransactionConfig $config = null)
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfig $config = null)
    {
        return $this->retry($tsxHandler, $config ?? TransactionConfig::default());
    }

    /**
     * @template U
     *
     * @param callable(\Laudis\Neo4j\Contracts\ManagedTransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    private function retry(callable $tsxHandler, TransactionConfig $config)
    {
        $timeout = $config->getTimeout();
        if ($timeout) {
            $limit = microtime(true) + $timeout;
        } else {
            $limit = PHP_FLOAT_MAX;
        }
        while (true) {
            try {
                $transaction = $this->openTransaction();
                $tbr = $tsxHandler($transaction);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                if (microtime(true) > $limit) {
                    throw $e;
                }
            }
        }
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfig $config = null)
    {
        return $this->writeTransaction($tsxHandler);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfig $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function getConfig(): SessionConfiguration
    {
        return $this->config;
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface
    {
        try {
            $bolt = $this->driver->acquireConnection($this->config, $config ?? TransactionConfig::default());
            if (!$bolt->begin(['db' => $this->config->getDatabase()])) {
                throw new Neo4jException(new Vector([new Neo4jError('', 'Cannot open new transaction')]));
            }
        } catch (Exception $e) {
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        $tsx = new BoltTransaction($bolt, $this);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    public function withFormatter(FormatterInterface $formatter): SessionInterface
    {
        return new self($this->driver, $this->config, $formatter);
    }
}
