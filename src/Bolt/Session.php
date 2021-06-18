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

namespace Laudis\Neo4j\Bolt;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Closure;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 *
 * @implements SessionInterface<T>
 */
final class Session implements SessionInterface
{
    private SessionConfiguration $config;
    /** @var ConnectionPoolInterface<StreamSocket> */
    private ConnectionPoolInterface $pool;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private string $userAgent;
    private UriInterface $uri;
    private AuthenticateInterface $auth;

    /**
     * @param FormatterInterface<T>                 $formatter
     * @param ConnectionPoolInterface<StreamSocket> $pool
     */
    public function __construct(
        SessionConfiguration $config,
        ConnectionPoolInterface $pool,
        FormatterInterface $formatter,
        string $userAgent,
        UriInterface $uri,
        AuthenticateInterface $auth
    ) {
        $this->config = $config;
        $this->pool = $pool;
        $this->formatter = $formatter;
        $this->userAgent = $userAgent;
        $this->uri = $uri;
        $this->auth = $auth;
    }

    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        return $this->openTransaction()->commit($statements);
    }

    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement])->first();
    }

    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return TransactionHelper::retry(
            Closure::fromCallable([$this, 'beginTransaction']),
            $tsxHandler,
            $config ?? TransactionConfiguration::default()
        );
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        try {
            $bolt = new Bolt($this->pool->acquire($this->uri, $this->config->getAccessMode()));
            $this->auth->authenticateBolt($bolt, $this->uri, $this->userAgent);

            $begin = $bolt->begin(['db' => $this->config->getDatabase()]);

            if (!$begin) {
                throw new Neo4jException(new Vector([new Neo4jError('', 'Cannot open new transaction')]));
            }
        } catch (Exception $e) {
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        $tsx = new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $bolt);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }
}
