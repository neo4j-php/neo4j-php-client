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

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest;
use Symfony\Component\Uid\Uuid;


/**
 * @extends AbstractRunner<TransactionRunRequest>
 */
final class TransactionRun extends AbstractRunner
{
    protected function getRunner($request): TransactionInterface
    {
        return $this->repository->getTransaction($request->getTxId());
    }

    protected function getId($request): Uuid
    {
        return $request->getTxId();
    }
}
