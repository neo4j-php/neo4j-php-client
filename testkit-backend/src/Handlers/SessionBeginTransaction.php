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
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionBeginTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\TransactionResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @implements RequestHandlerInterface<SessionBeginTransactionRequest>
 */
final class SessionBeginTransaction implements RequestHandlerInterface
{
    private MainRepository $repository;
    private LoggerInterface $logger;

    public function __construct(MainRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * @param SessionBeginTransactionRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $this->logger->debug('SessionBeginTransaction: Starting', ['sessionId' => $request->getSessionId()]);

        try {
            $session = $this->repository->getSession($request->getSessionId());
            $this->logger->debug('SessionBeginTransaction: Got session');

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

            $this->logger->debug('SessionBeginTransaction: About to call beginTransaction');
            // TODO - Create beginReadTransaction and beginWriteTransaction
            $transaction = $session->beginTransaction(null, $config);
            $this->logger->debug('SessionBeginTransaction: beginTransaction returned successfully');

            $id = Uuid::v4();

            $this->repository->addTransaction($id, $transaction);
            $this->repository->bindTransactionToSession($request->getSessionId(), $id);

            $this->logger->debug('SessionBeginTransaction: Returning TransactionResponse', ['id' => $id]);

            return new TransactionResponse($id);
        } catch (Neo4jException $exception) {
            $this->logger->error('SessionBeginTransaction: Neo4jException', [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return new DriverErrorResponse($request->getSessionId(), $exception);
        } catch (Throwable $exception) {
            $this->logger->error('SessionBeginTransaction: Unexpected exception', [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $neo4jException = new Neo4jException([new Neo4jError(
                'PHP.ClientError',
                $exception->getMessage()
            )]);

            return new DriverErrorResponse($request->getSessionId(), $neo4jException);
        }
    }
}
