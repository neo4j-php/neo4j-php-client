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
        $session = $this->repository->getSession($request->getSessionId());

        $config = TransactionConfiguration::default();

        if ($request->getTimeout()) {
            $config = $config->withTimeout($request->getTimeout());
        }

        if ($request->getTxMeta()) {
            $config = $config->withMetaData($request->getTxMeta());
        }

        // TODO - Create beginReadTransaction and beginWriteTransaction
        try {
            $transaction = $session->beginTransaction(null, $config);
        } catch (Neo4jException $exception) {
            $this->logger->debug($exception->__toString());

            return new DriverErrorResponse($request->getSessionId(), $exception);
        }
        $id = Uuid::v4();

        $this->repository->addTransaction($id, $transaction);
        $this->repository->bindTransactionToSession($request->getSessionId(), $id);

        return new TransactionResponse($id);
    }
}
