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

            if (method_exists($driver, 'executeQuery')) {
                return $this->handleWithExecuteQuery($driver, $request);
            }

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

    private function handleWithExecuteQuery($driver, ExecuteQueryRequest $request): TestkitResponseInterface
    {
        $config = $this->buildExecutionConfig($request->getConfig());
        $params = $request->getParams() ?? [];

        $eagerResult = $driver->executeQuery(
            $request->getCypher(),
            $params,
            $config
        );

        $resultId = Uuid::v4();
        $this->repository->addEagerResult($resultId, $eagerResult);

        return new EagerResultResponse($resultId, $eagerResult);
    }

    private function handleWithSession($driver, ExecuteQueryRequest $request): TestkitResponseInterface
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
            $result = $session->run(
                $request->getCypher(),
                $request->getParams() ?? []
            );

            $resultId = Uuid::v4();
            $this->repository->addEagerResult($resultId, $result);

            return new EagerResultResponse($resultId, $result);
        } finally {
            $session->close();
        }
    }

    private function buildExecutionConfig(?array $config): array
    {
        if ($config === null) {
            return [];
        }

        $executionConfig = [];

        if (array_key_exists('database', $config) && $config['database'] !== null) {
            $executionConfig['database'] = $config['database'];
        }

        if (array_key_exists('routing', $config) && $config['routing'] !== null) {
            $executionConfig['routing'] = $config['routing'];
        }

        if (array_key_exists('impersonatedUser', $config) && $config['impersonatedUser'] !== null) {
            $executionConfig['impersonatedUser'] = $config['impersonatedUser'];
        }

        if (array_key_exists('txMeta', $config) && $config['txMeta'] !== null) {
            $executionConfig['txMeta'] = $config['txMeta'];
        }

        if (array_key_exists('timeout', $config) && $config['timeout'] !== null) {
            $executionConfig['timeout'] = $config['timeout'] / 1000;
        }

        if (array_key_exists('authorizationToken', $config) && $config['authorizationToken'] !== null) {
            $authToken = $config['authorizationToken'];
            if (array_key_exists('data', $authToken)) {
                $executionConfig['auth'] = $this->convertAuthToken($authToken['data']);
            }
        }

        return $executionConfig;
    }

    private function convertAuthToken(array $tokenData): array
    {
        $auth = [];

        if (array_key_exists('scheme', $tokenData)) {
            $auth['scheme'] = $tokenData['scheme'];
        }

        if (array_key_exists('principal', $tokenData)) {
            $auth['principal'] = $tokenData['principal'];
        }

        if (array_key_exists('credentials', $tokenData)) {
            $auth['credentials'] = $tokenData['credentials'];
        }

        if (array_key_exists('realm', $tokenData)) {
            $auth['realm'] = $tokenData['realm'];
        }

        if (array_key_exists('parameters', $tokenData)) {
            $auth['parameters'] = $tokenData['parameters'];
        }

        return $auth;
    }
}
