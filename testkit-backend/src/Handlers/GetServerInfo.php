<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Exception;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\GetServerInfoRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ServerInfoResponse;
use ReflectionClass;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<GetServerInfoRequest>
 */
final class GetServerInfo implements RequestHandlerInterface
{
    public function __construct(
        private MainRepository $repository
    ) {}

    /**
     * @param GetServerInfoRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $driver = $this->repository->getDriver($request->getDriverId());

            if ($driver instanceof BoltDriver || $driver instanceof Neo4jDriver) {
                return $this->getServerInfoFromDriver($driver);
            }

            return $this->getServerInfoFromSession($driver);

        } catch (Exception $e) {
            $uuid = Uuid::v4();

            if ($e instanceof Neo4jException || $e instanceof TransactionException) {
                return new DriverErrorResponse($uuid, $e);
            }

            $neo4jError = new Neo4jError(
                $e->getMessage(),
                (string) $e->getCode(),
                'DatabaseError',
                'Service',
                'Service Unavailable'
            );

            return new DriverErrorResponse($uuid, new Neo4jException([$neo4jError], $e));
        }
    }

    private function getServerInfoFromDriver($driver): ServerInfoResponse
    {
        $connection = null;
        $pool = null;

        try {
            $pool = $this->getConnectionPool($driver);
            $connection = $this->acquireConnectionFromPool($pool, SessionConfiguration::default());
            return new ServerInfoResponse($this->extractServerInfo($connection));
        } finally {
            if ($connection !== null && $pool !== null) {
                $pool->release($connection);
            }
        }
    }

    /**
     * Extracts connection pool from driver using reflection.
     */
    private function getConnectionPool($driver)
    {
        $reflection = new ReflectionClass($driver);

        foreach (['pool', 'connectionPool', '_pool', 'connections'] as $propertyName) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $pool = $property->getValue($driver);

                if ($pool !== null) {
                    return $pool;
                }
            }
        }

        throw new Exception('Could not access connection pool from driver');
    }

    /**
     * Acquire a connection from the pool.
     */
    private function acquireConnectionFromPool($pool, SessionConfiguration $sessionConfig)
    {
        // Fail early if routing table has no readers
        if (method_exists($pool, 'getRoutingTable')) {
            $routingTable = $pool->getRoutingTable();
            if ($routingTable !== null && empty($routingTable->getReaders())) {
                throw new Neo4jException([
                    new Neo4jError(
                        'No readers available in routing table',
                        'N/A',
                        'ClientError',
                        'Routing',
                        'RoutingTable'
                    )
                ]);
            }
        }

        $connectionGenerator = $pool->acquire($sessionConfig);
        $connection = GeneratorHelper::getReturnFromGenerator($connectionGenerator);

        if ($connection === null) {
            throw new Exception('Connection pool returned no connections');
        }

        return $connection;
    }

    /**
     * Extract server information from an active connection.
     */
    private function extractServerInfo($connection): ServerInfo
    {
        foreach (['getServerAddress', 'getServerAgent', 'getProtocol'] as $method) {
            if (!method_exists($connection, $method)) {
                throw new Exception("Connection does not support {$method}()");
            }
        }

        $address  = $connection->getServerAddress();
        $agent    = $connection->getServerAgent();
        $protocol = $connection->getProtocol();

        if (empty($address) || empty($agent)) {
            throw new Exception('Server info is incomplete');
        }

        return new ServerInfo($address, $protocol, $agent);
    }

    private function getServerInfoFromSession($driver): ServerInfoResponse
    {
        if (method_exists($driver, 'session')) {
            $session = $driver->session();
        } elseif (method_exists($driver, 'createSession')) {
            $session = $driver->createSession();
        } elseif (method_exists($driver, 'newSession')) {
            $session = $driver->newSession();
        } else {
            throw new Exception('No session creation method found on driver');
        }

        try {
            $result = $session->run('RETURN 1');
            return new ServerInfoResponse($result->summary()->getServerInfo());
        } finally {
            $session->close();
        }
    }
}
