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
use Laudis\Neo4j\Enum\RoutingRoles;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\GetRoutingTableRequest;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RoutingTableResponse;
use ReflectionClass;
use ReflectionException;

/**
 * @implements RequestHandlerInterface<GetRoutingTableRequest>
 */
final class GetRoutingTable implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param GetRoutingTableRequest $request
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function handle($request): TestkitResponseInterface
    {
        $driver = $this->repository->getDriver($request->getDriverId());

        if ($driver instanceof Neo4jDriver) {
            $poolProperty = (new ReflectionClass(Neo4jDriver::class))->getProperty('pool');
            $poolProperty->setAccessible(true);
            /** @var Neo4jConnectionPool $pool */
            $pool = $poolProperty->getValue($driver);

            // Use public getter to access routing table registry
            $database = $request->getDatabase() ?? 'neo4j';
            $table = $pool->getRoutingTable($database);

            if ($table === null) {
                return new FrontendErrorResponse(sprintf(
                    'There is no routing table for database "%s". (It might not have been initialized yet)',
                    $database
                ));
            }

            return new RoutingTableResponse(
                $database,
                $table->getTtl(),
                $table->getWithRole(RoutingRoles::ROUTE()),
                $table->getWithRole(RoutingRoles::FOLLOWER()),
                $table->getWithRole(RoutingRoles::LEADER())
            );
        }

        return new FrontendErrorResponse('Only the neo4j scheme allows for a routing table');
    }
}
