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

namespace Laudis\Neo4j;

use Bolt\Bolt;
use Ds\Map;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Authentication\UrlAuth;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverConfigurationInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Bolt\Session;
use Laudis\Neo4j\Network\RoutingTable;
use function time;

/**
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class Neo4jDriver implements DriverInterface
{
    private AuthenticateInterface $auth;

    private ?RoutingTable $table = null;
    /** @var BoltDriver<Vector<Map<string, scalar|array|null>>> */
    private BoltDriver $driver;
    /** @var DriverConfigurationInterface<T> */
    private DriverConfigurationInterface $configuration;
    /** @var ParsedUrl */
    private array $parsedUrl;

    /**
     * @param ParsedUrl                                          $parsedUrl
     * @param BoltDriver<Vector<Map<string, scalar|array|null>>> $driver
     * @param DriverConfigurationInterface<T>                    $configuration
     */
    public function __construct(
        array $parsedUrl,
        AuthenticateInterface $auth,
        BoltDriver $driver,
        DriverConfigurationInterface $configuration
    ) {
        $this->auth = $auth;
        $this->driver = $driver;
        $this->parsedUrl = $parsedUrl;
        $this->configuration = $configuration;
    }

    /**
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= $this->configuration->getSessionConfiguration();

        return new Session($this, $config);
    }

    /**
     * @throws Exception
     */
    public function acquireConnection(SessionConfiguration $configuration): Bolt
    {
        return $this->getNextDriver($configuration)->acquireConnection($configuration);
    }

    /**
     * @throws Exception
     */
    private function getNextDriver(SessionConfiguration $config): BoltDriver
    {
        $drivers = $this->setupDrivers($config);

        if ($config->getAccessMode() === AccessMode::WRITE()) {
            $currentLeader = random_int(0, $drivers['leaders']->count() - 1);

            return $drivers['leaders']->get($currentLeader)();
        }

        $currentFollower = random_int(0, $drivers['followers']->count() - 1);

        return $drivers['followers']->get($currentFollower)();
    }

    /**
     * @throws Exception
     *
     * @return array{'leaders': Vector<callable():BoltDriver>, 'followers': Vector<callable():BoltDriver>}
     */
    private function setupDrivers(SessionConfiguration $config): array
    {
        if ($this->table === null || $this->table->getTtl() < time()) {
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
        }

        $leaders = $this->table->getWithRole(RoutingRoles::LEADER());
        $followers = $this->table->getWithRole(RoutingRoles::FOLLOWER());

        $auth = $this->auth;
        // Translate authentication from uri to basic because the routing table does not provide uri authentication
        if (isset($this->parsedUrl['user'], $this->parsedUrl['pass']) && $auth instanceof UrlAuth) {
            $auth = Authenticate::basic($this->parsedUrl['user'], $this->parsedUrl['pass']);
        }

        /** @var Vector<callable():BoltDriver> $leadersTbr */
        $leadersTbr = new Vector();
        foreach ($leaders as $leader) {
            $leadersTbr->push($this->makeDriver($leader, $auth));
        }

        /** @var Vector<callable():BoltDriver> $followersTbr */
        $followersTbr = new Vector();
        foreach ($followers as $follower) {
            $followersTbr->push($this->makeDriver($follower, $auth));
        }

        return ['leaders' => $leadersTbr, 'followers' => $followersTbr];
    }

    private function makeDriver(string $url, AuthenticateInterface $auth): callable
    {
        return function () use ($url, $auth) {
            return new BoltDriver(
                ConnectionManager::parseUrl($url),
                $auth,
                new ConnectionManager($this->configuration->getHttpPsrBindings()),
                $this->getConfiguration()
            );
        };
    }

    public function withUserAgent($userAgent): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withUserAgent($userAgent));
    }

    public function withSessionConfiguration(?SessionConfiguration $configuration): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withSessionConfiguration($configuration));
    }

    public function withTransactionConfiguration(?TransactionConfiguration $configuration): DriverInterface
    {
        $transactionConfiguration = $this->configuration->getTransactionConfiguration()->merge($configuration);
        $merged = $this->configuration->withTransactionConfiguration($transactionConfiguration);

        return new self($this->parsedUrl, $this->auth, $this->driver, $merged);
    }

    public function withConfiguration($configuration): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $configuration);
    }

    public function getTransactionConfiguration(): StaticTransactionConfiguration
    {
        return $this->configuration->getTransactionConfiguration();
    }

    public function getSessionConfiguration(): SessionConfiguration
    {
        return $this->configuration->getSessionConfiguration();
    }

    public function withFormatter($formatter): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withFormatter($formatter));
    }

    public function withTransactionTimeout($timeout): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withTransactionTimeout($timeout));
    }

    public function withDatabase($database): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withDatabase($database));
    }

    public function withFetchSize($fetchSize): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withFetchSize($fetchSize));
    }

    public function withAccessMode($accessMode): DriverInterface
    {
        return new self($this->parsedUrl, $this->auth, $this->driver, $this->configuration->withAccessMode($accessMode));
    }

    public function getConfiguration(): DriverConfigurationInterface
    {
        return $this->configuration;
    }
}
