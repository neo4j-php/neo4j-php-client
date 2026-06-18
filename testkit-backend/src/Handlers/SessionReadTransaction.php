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

use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionReadTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Laudis\Neo4j\Types\CypherList;
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
        $this->repository->addTransaction($id, new class($session, $config) implements TransactionInterface {
            public function __construct(
                private readonly SessionInterface $session,
                private readonly TransactionConfiguration $config,
            ) {
            }

            public function run(string $statement, iterable $parameters = []): SummarizedResult
            {
                return $this->session->readTransaction(static function (TransactionInterface $tx) use ($statement, $parameters): SummarizedResult {
                    return $tx->run($statement, $parameters);
                }, $this->config);
            }

            public function runStatement(Statement $statement): SummarizedResult
            {
                return $this->run($statement->getText(), $statement->getParameters());
            }

            public function runStatements(iterable $statements): CypherList
            {
                $list = [];
                foreach ($statements as $statement) {
                    $list[] = $this->runStatement($statement);
                }

                return new CypherList($list);
            }
        });
        $this->repository->bindTransactionToSession($request->getSessionId(), $id);

        return new RetryableTryResponse($id);
    }
}
