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
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Formatter\BoltCypherFormatter;

class CasualClusterSession implements SessionInterface
{
    private RoutingTable $routingTable;
    private array $parsedUrl;
    private Bolt $bolt;
    private BoltCypherFormatter $formatter;
    private BoltInjections $injections;

    public function __construct(RoutingTable $routingTable, array $parsedUrl, Bolt $bolt, BoltCypherFormatter $formatter, BoltInjections $injections)
    {
        $this->routingTable = $routingTable;
        $this->parsedUrl = $parsedUrl;
        $this->bolt = $bolt;
        $this->formatter = $formatter;
        $this->injections = $injections;
    }

    public function run(iterable $statements): Vector
    {
        return $this->preparedSession($statements)->run($statements);
    }

    public function runOverTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        return $this->preparedSession($statements)->runOverTransaction($transaction, $statements);
    }

    public function rollbackTransaction(TransactionInterface $transaction): void
    {
        $this->preparedSession()->rollbackTransaction($transaction);
    }

    public function commitTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        return $this->preparedSession($statements)->commitTransaction($transaction, $statements);
    }

    public function openTransaction(?iterable $statements = null): TransactionInterface
    {
        return $this->preparedSession($statements)->openTransaction($statements);
    }

    private function needsWriter(array $statements): bool
    {
        return count(preg_grep(
            '/(CREATE|SET|MERGE|DELETE)/m',
            array_map(function ($statement) {
                return $statement->getText();
            }, $statements)
        )) > 0;
    }

    private function preparedSession(?iterable $statements = [])
    {
        if (!is_null($statements) && $this->needsWriter($this->coerce_statements($statements))) {
            $leaders = $this->routingTable->getLeaders();

            $parsedUrl = $this->parsedUrl;
            $parsedUrl['host'] = $leaders->getHost();
            return new BoltSession($parsedUrl, $this->bolt, $this->formatter, $this->injections);
        }

        return new BoltSession($this->parsedUrl, $this->bolt, $this->formatter, $this->injections);
    }

    private function coerce_statements(iterable $statements): array
    {
        if (is_array($statements)) return $statements;
        $s = [];
        array_push($s, ...$statements);
        return $s;
    }
}
