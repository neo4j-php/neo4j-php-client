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
use Bolt\connection\StreamSocket;
use Ds\Map;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BoltCypherFormatter;
use Laudis\Neo4j\HttpDriver\Transaction;
use Laudis\Neo4j\ParameterHelper;
use Throwable;

final class BoltSession implements SessionInterface
{
    private Bolt $bolt;
    private const DEFAULT_TCP_PORT = 7687;
    private BoltCypherFormatter $formatter;
    private BoltInjections $injections;
    /** @var Map<string, Bolt> */
    private Map $transactions;
    /** @var array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} */
    private array $parsedUrl;

    /**
     * @param array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} $parsedUrl
     */
    public function __construct(array $parsedUrl, Bolt $bolt, BoltCypherFormatter $formatter, BoltInjections $injections)
    {
        $this->bolt = $bolt;
        $this->formatter = $formatter;
        $this->injections = $injections;
        $this->transactions = new Map();
        $this->parsedUrl = $parsedUrl;
    }

    public function run(iterable $statements): Vector
    {
        return $this->runStatements($statements, $this->bolt);
    }

    public function runOverTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        return $this->runStatements($statements, $this->transactions->get($transaction->getDomainIdentifier()));
    }

    public function rollbackTransaction(TransactionInterface $transaction): void
    {
        $tsx = $this->transactions->get($transaction->getDomainIdentifier());
        try {
            $tsx->rollback();
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }
    }

    public function commitTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        $tbr = $this->runOverTransaction($transaction, $statements);
        $tsx = $this->transactions->get($transaction->getDomainIdentifier());
        try {
            $tsx->commit();
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        return $tbr;
    }

    public function openTransaction(iterable $statements = null): TransactionInterface
    {
        $userAgent = sprintf('LaudisNeo4j-tsx%s/%s', $this->transactions->count(), ClientInterface::VERSION);
        try {
            $sock = new StreamSocket($this->parsedUrl['host'], $this->parsedUrl['port'] ?? self::DEFAULT_TCP_PORT);
            $bolt = new Bolt($sock);
            $bolt->init($userAgent, $this->parsedUrl['user'], $this->parsedUrl['pass']);
            $extra = ['db' => $this->injections->database()];
            if (!$bolt->begin($extra)) {
                throw new Neo4jException(new Vector([new Neo4jError('', 'Cannot open new transaction')]));
            }
        } catch (Exception $e) {
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }
        $id = bin2hex(microtime().$this->transactions->count());
        $this->transactions->put($id, $bolt);

        $tsx = new Transaction($this, $id);

        $this->runOverTransaction($tsx, $statements ?? []);

        return $tsx;
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    private function runStatements(iterable $statements, Bolt $bolt): Vector
    {
        $tbr = new Vector();
        foreach ($statements as $statement) {
            $extra = ['db' => $this->injections->database()];
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            try {
                /** @var array{fields: array<int, string>} $meta */
                $meta = $bolt->run($statement->getText(), $parameters->toArray(), $extra);
                /** @var array<array> $results */
                $results = $bolt->pullAll();
            } catch (Throwable $e) {
                throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
            }
            $tbr->push($this->formatter->formatResult($meta, $results));
        }

        return $tbr;
    }
}
