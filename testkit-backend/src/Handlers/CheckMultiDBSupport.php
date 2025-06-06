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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\CheckMultiDBSupportRequest;
use Laudis\Neo4j\TestkitBackend\Responses\MultiDBSupportResponse;

/**
 * @implements RequestHandlerInterface<CheckMultiDBSupportRequest>
 */
final class CheckMultiDBSupport implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param CheckMultiDBSupportRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $driver = $this->repository->getDriver($request->getDriverId());

        try {
            $session = $driver->createSession(SessionConfiguration::default()->withDatabase('system'));
            $session->run('SHOW databases');
        } catch (Exception $e) {
            return new MultiDBSupportResponse($request->getDriverId(), false);
        }

        return new MultiDBSupportResponse($request->getDriverId(), true);
    }
}
