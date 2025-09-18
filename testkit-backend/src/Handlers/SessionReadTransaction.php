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
use Laudis\Neo4j\TestkitBackend\Requests\SessionReadTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
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
                    $actualMeta[$key] = $this->decodeToValue($meta);
                }
            }
            $config = $config->withMetaData($actualMeta);
        }

        if ($request->getBookmarks()) {
            $config = $config->withBookmarks($request->getBookmarks());
        }

        $id = Uuid::v4();
        try {
            // TODO - Create beginReadTransaction and beginWriteTransaction
            $transaction = $session->beginTransaction(null, $config);

            $this->repository->addTransaction($id, $transaction);
            $this->repository->bindTransactionToSession($request->getSessionId(), $id);
        } catch (Neo4jException $exception) {
            $this->repository->addRecords($id, new DriverErrorResponse(
                $id,
                $exception
            ));

            return new DriverErrorResponse($id, $exception);
        }

        return new RetryableTryResponse($id);
    }

    // f1aa000cede64d6a8879513c97633777
    private function decodeToValue(array $param)
    {
        $value = $param['data']['value'];
        if (is_iterable($value)) {
            if ($param['name'] === 'CypherMap') {
                /** @psalm-suppress MixedArgumentTypeCoercion */
                $map = [];
                /**
                 * @var numeric $k
                 * @var mixed   $v
                 */
                foreach ($value as $k => $v) {
                    /** @psalm-suppress MixedArgument */
                    $map[(string) $k] = $this->decodeToValue($v);
                }

                return new CypherMap($map);
            }

            if ($param['name'] === 'CypherList') {
                $list = [];
                /**
                 * @var mixed $v
                 */
                foreach ($value as $v) {
                    /** @psalm-suppress MixedArgument */
                    $list[] = $this->decodeToValue($v);
                }

                return new CypherList($list);
            }
        }

        return $value;
    }
}
