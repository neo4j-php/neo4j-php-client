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

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionCloseRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\TransactionResponse;

/**
 * @implements RequestHandlerInterface<TransactionCloseRequest>
 */
final class TransactionClose implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param TransactionCloseRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $tsx = $this->repository->getTransaction($request->getTxId());

        if ($tsx === null) {
            return new BackendErrorResponse('Transaction not found');
        }

        $this->repository->removeTransaction($request->getTxId());

        return new TransactionResponse($request->getTxId());
    }
}

