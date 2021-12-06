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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Exception;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ForcedRoutingTableUpdateRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;
use ReflectionClass;
use ReflectionException;

/**
 * @implements RequestHandlerInterface<ForcedRoutingTableUpdateRequest>
 */
final class ForcedRoutingTableUpdate implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param ForcedRoutingTableUpdateRequest $request
     * @throws ReflectionException
     * @throws Exception
     */
    public function handle($request): TestkitResponseInterface
    {
        $driver = $this->repository->getDriver($request->getDriverId());

        if ($driver instanceof Neo4jDriver) {
            $poolProperty = (new ReflectionClass(Neo4jDriver::class))->getProperty('pool');
            $poolProperty->setAccessible(true);
            /** @var ConnectionPoolInterface $pool */
            $pool = $poolProperty->getValue($driver);

            $tableProperty = (new ReflectionClass(Neo4jConnectionPool::class))->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($pool, null);
        }

        $driver->createSession()->run('RETURN 1 AS x');

        return new DriverResponse($request->getDriverId());
    }
}
