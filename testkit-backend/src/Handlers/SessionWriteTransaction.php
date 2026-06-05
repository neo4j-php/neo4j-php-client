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

use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\TelemetryAPI;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\DriverExceptionHelper;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Throwable;
use Laudis\Neo4j\TestkitBackend\Requests\SessionWriteTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<SessionWriteTransactionRequest>
 */
final class SessionWriteTransaction implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param SessionWriteTransactionRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $session = $this->repository->getSession($request->getSessionId());

        $config = TransactionConfiguration::default();

        if ($request->getTimeout()) {
            $config = $config->withTimeout($request->getTimeout());
        }

        if ($request->getTxMeta()) {
            $metaData = $request->getTxMeta();
            $actualMeta = [];
            if ($metaData !== null) {
                foreach ($metaData as $key => $meta) {
                    $actualMeta[$key] = AbstractRunner::decodeToValue($meta);
                }
            }
            $config = $config->withMetaData($actualMeta);
        }

        $id = Uuid::v4();
        try {
            $transaction = $session instanceof Session
                ? $session->beginTransaction(null, $config, TelemetryAPI::TX_FUNC)
                : $session->beginTransaction(null, $config);

            $this->repository->addTransaction($id, $transaction);
            $this->repository->bindTransactionToSession($request->getSessionId(), $id);
        } catch (Neo4jException $exception) {
            if (DriverExceptionHelper::isConnectionNeo4jException($exception)
                || DriverExceptionHelper::isRetryableConnectionFailure($exception)) {
                $exception = DriverExceptionHelper::toConnectionNeo4jException($exception);
            }

            $this->repository->addRecords($id, new DriverErrorResponse($id, $exception));

            return new DriverErrorResponse($id, $exception);
        } catch (Throwable $e) {
            if (!DriverExceptionHelper::isRetryableConnectionFailure($e)) {
                throw $e;
            }

            $exception = DriverExceptionHelper::toConnectionNeo4jException($e);
            $this->repository->addRecords($id, new DriverErrorResponse($id, $exception));

            return new DriverErrorResponse($id, $exception);
        }

        return new RetryableTryResponse($id);
    }
}
