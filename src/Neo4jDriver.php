<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j;

use Bolt\Bolt;
use Ds\Map;
use Exception;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Authentication\UriAuth;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Bolt\Session;
use Laudis\Neo4j\Network\RoutingTable;
use function parse_url;
use function random_int;
use function time;

/**
 * @implements DriverInterface<\Bolt\Bolt>
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class Neo4jDriver implements DriverInterface
{
    private string $userAgent;
    private AuthenticateInterface $auth;
    /** @var Map<string, BoltDriver>|null */
    private ?Map $drivers = null;

    private ?RoutingTable $table = null;
    private int $maxLeader = 0;
    private int $maxFollower = 0;
    private BoltDriver $driver;
    private string $defaultDatabase;
    /** @var ParsedUrl */
    private array $parsedUrl;

    /**
     * @param ParsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, string $userAgent, AuthenticateInterface $auth, BoltDriver $driver, string $defaultDatabase = 'neo4j')
    {
        $this->userAgent = $userAgent;
        $this->auth = $auth;
        $this->driver = $driver;
        $this->defaultDatabase = $defaultDatabase;
        $this->parsedUrl = $parsedUrl;
    }

    /**
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= SessionConfiguration::create($this->defaultDatabase);

        return new Session($this, $config, new BasicFormatter());
    }

    /**
     * @throws Exception
     */
    public function acquireConnection(SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig): Bolt
    {
        return $this->getNextDriver($sessionConfig)->acquireConnection($sessionConfig, $tsxConfig);
    }

    /**
     * @throws Exception
     */
    private function getNextDriver(SessionConfiguration $config): BoltDriver
    {
        $mode = $config->getDefaultAccessMode();
        $client = $this->setupDrivers($config);

        if ($mode === AccessMode::WRITE()) {
            return $client->get($this->writeAlias());
        }

        return $client->get($this->readAlias());
    }

    /**
     * @throws Exception
     *
     * @return Map<string, BoltDriver>
     */
    private function setupDrivers(SessionConfiguration $config): Map
    {
        if ($this->table === null || $this->drivers === null || $this->table->getTtl() < time()) {
            $statement = new Statement('CALL dbms.routing.getRoutingTable({context: $context, database: $database})', [
                'context' => [],
                'database' => $config->getDatabase(),
            ]);
            $response = $this->driver->createSession($config)->runStatements([$statement])->first()->first();
            /** @var iterable<array{addresses: list<string>, role:string}> $values */
            $values = $response->get('servers');
            /** @var int $ttl */
            $ttl = $response->get('ttl');
            $this->table = new RoutingTable($values, time() + $ttl);

            /** @var Map<string, BoltDriver> $map */
            $map = new Map();

            $leaders = $this->table->getWithRole(RoutingRoles::LEADER());
            $followers = $this->table->getWithRole(RoutingRoles::FOLLOWER());

            $auth = $this->auth;
            // Translate authentication from uri to basic because the routing table does not provide uri authentication
            if (isset($this->parsedUrl['user'], $this->parsedUrl['pass']) && $auth instanceof UriAuth) {
                $auth = Authenticate::basic($this->parsedUrl['user'], $this->parsedUrl['pass']);
            }

            foreach ($leaders as $i => $leader) {
                $map->put('leader-'.$i, $this->makeDriver($leader, $auth));
                $this->maxLeader = $i;
            }
            foreach ($followers as $i => $follower) {
                $map->put('follower-'.$i, $this->makeDriver($follower, $auth));
                $this->maxFollower = $i;
            }

            $this->drivers = $map;

            return $map;
        }

        return $this->drivers;
    }

    /**
     * @throws Exception
     */
    private function readAlias(): string
    {
        return 'follower-'.random_int(0, $this->maxFollower);
    }

    /**
     * @throws Exception
     */
    private function writeAlias(): string
    {
        return 'leader-'.random_int(0, $this->maxLeader);
    }

    private function makeDriver(string $leader, AuthenticateInterface $auth): BoltDriver
    {
        return new BoltDriver(
            parse_url($leader),
            $this->userAgent,
            $auth,
            new ConnectionManager(),
            $this->defaultDatabase
        );
    }
}
