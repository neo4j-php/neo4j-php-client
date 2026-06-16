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
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ExecuteQueryRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\EagerResultResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<ExecuteQueryRequest>
 */
final class ExecuteQuery implements RequestHandlerInterface
{
    public function __construct(
        private MainRepository $repository,
    ) {
    }

    /**
     * @param ExecuteQueryRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $driver = $this->repository->getDriver($request->getDriverId());

            return $this->handleWithSession($driver, $request);
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

    private function handleWithSession(DriverInterface $driver, ExecuteQueryRequest $request): TestkitResponseInterface
    {
        $config = $request->getConfig();

        $sessionConfig = SessionConfiguration::default();

        if (array_key_exists('database', $config)) {
            $sessionConfig = $sessionConfig->withDatabase($config['database']);
        }

        $accessMode = AccessMode::READ();
        if (array_key_exists('routing', $config) && $config['routing'] === 'w') {
            $accessMode = AccessMode::WRITE();
        }
        $sessionConfig = $sessionConfig->withAccessMode($accessMode);

        $session = $driver->createSession($sessionConfig);

        try {
            $result = $session->executeQuery(
                $request->getCypher(),
                $request->getParams() ?? []
            );
            $result->preload();

            return new EagerResultResponse($result);
        } finally {
            $session->close();
        }
    }
}
