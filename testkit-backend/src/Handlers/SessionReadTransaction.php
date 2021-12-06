<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionReadTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<SessionReadTransactionRequest>
 */
final class SessionReadTransaction implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param SessionReadTransactionRequest $request
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
        $transaction = $session->beginTransaction(null, $config);
        $id = Uuid::v4();

        $this->repository->addTransaction($id, $transaction);
        $this->repository->bindTransactionToSession($request->getSessionId(), $id);

        return new RetryableTryResponse($id);
    }
}
