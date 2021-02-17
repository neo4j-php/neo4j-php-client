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

namespace Laudis\Neo4j\Network;

use Ds\Map;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\Injections;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Enum\RoutingRoles;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use Laudis\Neo4j\Network\Http\HttpInjections;
use function parse_url;
use function preg_match;
use function random_int;
use function time;

final class AutoRoutedSession implements SessionInterface
{
    private SessionInterface $referenceSession;
    private ?ClientInterface $client = null;
    private ?RoutingTable $table = null;
    /** @var BoltInjections|HttpInjections */
    private Injections $injections;
    private int $maxLeader = 0;
    private int $maxFollower = 0;
    private array $parsedUrl;

    /**
     * @param BoltInjections|HttpInjections $injections
     */
    public function __construct(SessionInterface $referenceSession, Injections $injections, array $parsedUrl)
    {
        $this->referenceSession = $referenceSession;
        $this->injections = $injections;
        $this->parsedUrl = $parsedUrl;
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return array{0: Map<int, Statement>, 1: Map<int, Statement>}
     */
    private function sortStatements(iterable $statements): array
    {
        /** @psalm-var Map<int, Statement> $writeStatements */
        $writeStatements = new Map();
        /** @psalm-var Map<int, Statement> $readStatements */
        $readStatements = new Map();

        $index = 0;
        foreach ($statements as $statement) {
            if (preg_match('/(CREATE|SET|MERGE|DELETE|CALL)/m', $statement->getText())) {
                $writeStatements->put($index, $statement);
            } else {
                $readStatements->put($index, $statement);
            }
            ++$index;
        }

        return [$readStatements, $writeStatements];
    }

    private function setupClient(): ClientInterface
    {
        if ($this->table === null || $this->client === null || $this->table->getTtl() < time()) {
            $statement = new Statement('CALL dbms.routing.getRoutingTable({context: $context, database: $database})', [
                'context' => [],
                'database' => $this->injections->database(),
            ]);
            $response = $this->referenceSession->run([$statement])->first()->first();
            /** @var iterable<array{addresses: list<string>, role:string}> $values */
            $values = $response->get('servers');
            /** @var int $ttl */
            $ttl = $response->get('ttl');
            if ($this->injections instanceof HttpInjections) {
                $values = $this->translateTableToHttp($values);
            }
            $this->table = new RoutingTable($values, time() + $ttl);

            $builder = ClientBuilder::create();
            $leaders = $this->table->getWithRole(RoutingRoles::LEADER());
            $followers = $this->table->getWithRole(RoutingRoles::FOLLOWER());
            $injections = $this->injections->withAutoRouting(false);

            if ($injections instanceof BoltInjections) {
                $builder = $this->buildBoltConnections($leaders, $builder, $injections, $followers);
            } else {
                $builder = $this->buildHttpConnections($leaders, $builder, $injections, $followers);
            }

            $this->client = $builder->build();
        }

        return $this->client;
    }

    /**
     * @throws Exception
     */
    public function run(iterable $statements): Vector
    {
        $client = $this->setupClient();
        [$toRead, $toWrite] = $this->sortStatements($statements);
        /** @psalm-var Vector<Vector<Map<string, array|scalar|null>>|null> */
        $tbr = new Vector();
        $capacity = $toRead->count() + $toWrite->count();
        for ($i = 0; $i < $capacity; ++$i) {
            $tbr->push(null);
        }

        if ($toRead->count()) {
            $results = $client->runStatements($toRead->values(), $this->readConnection());
            $this->weaveResults($toRead, $tbr, $results);
        }
        if ($toWrite->count()) {
            $results = $client->runStatements($toWrite->values(), $this->writeConnection());
            $this->weaveResults($toWrite, $tbr, $results);
        }

        /** @psalm-var Vector<Vector<Map<string, array|scalar|null>>> */
        return $tbr;
    }

    /**
     * @throws Exception
     */
    private function readConnection(): string
    {
        return 'follower-'.random_int(0, $this->maxFollower);
    }

    /**
     * @throws Exception
     */
    private function writeConnection(): string
    {
        return 'leader-'.random_int(0, $this->maxLeader);
    }

    /**
     * @throws Exception
     */
    public function openTransaction(?iterable $statements = null, ?string $connectionAlias = null): TransactionInterface
    {
        return $this->setupClient()->openTransaction($statements, $connectionAlias ?? $this->writeConnection());
    }

    /**
     * @param Map<int, Statement>                                 $reference
     * @param Vector<Vector<Map<string, array|scalar|null>>|null> $tbr
     * @param Vector<Vector<Map<string, array|scalar|null>>>      $results
     */
    private function weaveResults(Map $reference, Vector $tbr, Vector $results): void
    {
        $i = 0;
        foreach ($reference->keys() as $position) {
            $tbr->set($position, $results->get($i));
            ++$i;
        }
    }

    public function runOverTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        return $transaction->runStatements($statements);
    }

    public function rollbackTransaction(TransactionInterface $transaction): void
    {
        $transaction->rollback();
    }

    public function commitTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        return $transaction->commit($statements);
    }

    private function rebuildUrl(array $parsedUrl): string
    {
        $parts = array_merge($this->parsedUrl, $parsedUrl);

        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '').
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : '').
            (isset($parts['user']) ? (string) ($parts['user']) : '').
            (isset($parts['pass']) ? ":{$parts['pass']}" : '').
            (isset($parts['user']) ? '@' : '').
            (isset($parts['host']) ? (string) ($parts['host']) : '').
            (isset($parts['port']) ? ":{$parts['port']}" : '').
            (isset($parts['path']) ? (string) ($parts['path']) : '').
            (isset($parts['query']) ? "?{$parts['query']}" : '').
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }

    /**
     * @param Vector<string> $leaders
     * @param Vector<string> $followers
     */
    private function buildBoltConnections(
        Vector $leaders,
        ClientBuilder $builder,
        BoltInjections $injections,
        Vector $followers
    ): ClientBuilder {
        foreach ($leaders as $i => $leader) {
            $builder = $builder->addBoltConnection('leader-'.$i, $this->rebuildUrl(parse_url($leader)), $injections);
            $this->maxLeader = $i;
        }
        foreach ($followers as $i => $follower) {
            $builder = $builder->addBoltConnection('follower-'.$i, $this->rebuildUrl(parse_url($follower)), $injections);
            $this->maxFollower = $i;
        }

        return $builder;
    }

    /**
     * @param Vector<string> $leaders
     * @param Vector<string> $followers
     */
    private function buildHttpConnections(
        Vector $leaders,
        ClientBuilder $builder,
        HttpInjections $injections,
        Vector $followers
    ): ClientBuilder {
        foreach ($leaders as $i => $leader) {
            $builder = $builder->addHttpConnection('leader-'.$i, $this->rebuildUrl(parse_url($leader)), $injections);
            $this->maxLeader = $i;
        }
        foreach ($followers as $i => $follower) {
            $builder = $builder->addHttpConnection('follower-'.$i, $this->rebuildUrl(parse_url($follower)), $injections);
            $this->maxFollower = $i;
        }

        return $builder;
    }

    /**
     * @param iterable<array{addresses: list<string>, role:string}> $servers
     *
     * @return iterable<array{addresses: list<string>, role:string}>
     */
    private function translateTableToHttp(iterable $servers): iterable
    {
        /** @var list<array{addresses: list<string>, role:string}> */
        $tbr = [];

        foreach ($servers as $server) {
            $row = ['addresses' => [], 'role' => $server['role']];
            foreach ($server['addresses'] as $address) {
                $row['addresses'][] = $this->rebuildUrl(['host' => parse_url($address, PHP_URL_HOST)]);
            }
            $tbr[] = $row;
        }

        return $tbr;
    }
}
