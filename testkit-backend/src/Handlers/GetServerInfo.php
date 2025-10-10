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
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\GetServerInfoRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ServerInfoResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<GetServerInfoRequest>
 */
final class GetServerInfo implements RequestHandlerInterface
{
    public function __construct(
        private MainRepository $repository,
    ) {
    }

    /**
     * @param GetServerInfoRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $driver = $this->repository->getDriver($request->getDriverId());

            $serverInfo = $driver->getServerInfo();

            return new ServerInfoResponse($serverInfo);
        } catch (Neo4jException|TransactionException $e) {
            return new DriverErrorResponse(Uuid::v4(), $e);
        } catch (Exception $e) {
            $neo4jError = new Neo4jError(
                $e->getMessage(),
                (string) $e->getCode(),
                'DatabaseError',
                'Service',
                'Service Unavailable'
            );

            return new DriverErrorResponse(Uuid::v4(), new Neo4jException([$neo4jError], $e));
        }
    }
}
