<?php

declare(strict_types=1);

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

            // Check if driver has executeQuery method
            if (method_exists($driver, 'executeQuery')) {
                return $this->handleWithExecuteQuery($driver, $request);
            }

            // Fallback: use session-based approach
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

        // Build session configuration
        $sessionConfig = SessionConfiguration::default();

        if (isset($config['database'])) {
            $sessionConfig = $sessionConfig->withDatabase($config['database']);
        }

        // REMOVE THIS - IT DOESN'T WORK YET
        // if (isset($config['impersonatedUser'])) {
        //     $sessionConfig = $sessionConfig->withImpersonatedUser($config['impersonatedUser']);
        // }

        // Determine access mode
        $accessMode = AccessMode::READ();
        if (isset($config['routing']) && $config['routing'] === 'w') {
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

        if (isset($config['database']) && $config['database'] !== null) {
            $executionConfig['database'] = $config['database'];
        }

        if (isset($config['routing']) && $config['routing'] !== null) {
            $executionConfig['routing'] = $config['routing'];
        }

        if (isset($config['impersonatedUser']) && $config['impersonatedUser'] !== null) {
            $executionConfig['impersonatedUser'] = $config['impersonatedUser'];
        }

        if (isset($config['txMeta']) && $config['txMeta'] !== null) {
            $executionConfig['txMeta'] = $config['txMeta'];
        }

        if (isset($config['timeout']) && $config['timeout'] !== null) {
            $executionConfig['timeout'] = $config['timeout'] / 1000;
        }

        if (isset($config['authorizationToken']) && $config['authorizationToken'] !== null) {
            $authToken = $config['authorizationToken'];
            if (isset($authToken['data'])) {
                $executionConfig['auth'] = $this->convertAuthToken($authToken['data']);
            }
        }

        return $executionConfig;
    }

    private function convertAuthToken(array $tokenData): array
    {
        $auth = [];

        if (isset($tokenData['scheme'])) {
            $auth['scheme'] = $tokenData['scheme'];
        }

        if (isset($tokenData['principal'])) {
            $auth['principal'] = $tokenData['principal'];
        }

        if (isset($tokenData['credentials'])) {
            $auth['credentials'] = $tokenData['credentials'];
        }

        if (isset($tokenData['realm'])) {
            $auth['realm'] = $tokenData['realm'];
        }

        if (isset($tokenData['parameters'])) {
            $auth['parameters'] = $tokenData['parameters'];
        }

        return $auth;
    }
}
