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

use function array_flip;
use Bolt\Bolt;
use Bolt\protocol\V3;
use Exception;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\BoltConnection;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Neo4j\RoutingTable;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Manages singular Bolt connections.
 *
 * @implements ConnectionPoolInterface<V3>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    /** @var array<string, list<ConnectionInterface<V3>>> */
    private static array $connectionCache = [];

    /**
     * @throws Exception
     */
    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        float $socketTimeout,
        string $userAgent,
        SessionConfiguration $config,
        ?RoutingTable $table = null,
        ?UriInterface $server = null
    ): ConnectionInterface {
        $connectingTo = $server ?? $uri;
        $key = $connectingTo->getHost().':'.($connectingTo->getPort() ?? '7687');
        if (!isset(self::$connectionCache[$key])) {
            self::$connectionCache[$key] = [];
        }

        foreach (self::$connectionCache[$key] as $connection) {
            if (!$connection->isOpen()) {
                $connection->open();

                return $connection;
            }
        }

        $factory = BoltFactory::fromVariables($connectingTo, null, null, $authenticate, $userAgent);
        $bolt = $factory->build();

        /**
         * @var array{'name': 0, 'version': 1, 'edition': 2}
         * @psalm-suppress all
         */
        $fields = array_flip($bolt->run(<<<'CYPHER'
CALL dbms.components()
YIELD name, versions, edition
UNWIND versions AS version
RETURN name, version, edition
CYPHER
        )['fields']);

        /** @var array{0: array{0: string, 1: string, 2: string}} $results */
        $results = $bolt->pullAll();

        $connection = new BoltConnection(
            $results[0][$fields['name']].'-'.$results[0][$fields['edition']].'/'.$results[0][$fields['version']],
            $connectingTo,
            $results[0][$fields['version']],
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase()),
            $factory,
            $bolt
        );

        self::$connectionCache[$key][] = $connection;

        return $connection;
    }

    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate, ?string $userAgent = null): bool
    {
        $bolt = BoltFactory::fromVariables($uri, null, null, $authenticate, $userAgent ?? DriverConfiguration::DEFAULT_USER_AGENT);

        try {
            $bolt->build();
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
