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

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\VerifyConnectivityRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;
use Throwable;

/**
 * @implements RequestHandlerInterface<VerifyConnectivityRequest>
 */
final class VerifyConnectivity implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param VerifyConnectivityRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $driver = $this->repository->getDriver($request->getDriverId());

            $driver->createSession()->run('RETURN 2 as x');

            return new DriverResponse($request->getDriverId());
        } catch (Neo4jException $e) {
            return new DriverErrorResponse($request->getDriverId(), $e);
        } catch (Throwable $e) {
            $neo4jError = Neo4jError::fromMessageAndCode(
                'Neo.ClientError.Database.DatabaseUnavailable',
                $e->getMessage()
            );

            return new DriverErrorResponse($request->getDriverId(), new Neo4jException([$neo4jError], $e));
        }
    }
}
