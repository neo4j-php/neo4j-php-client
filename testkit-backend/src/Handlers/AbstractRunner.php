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

use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\InvalidTransactionStateException;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @template T of \Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest|\Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest
 *
 * @implements RequestHandlerInterface<T>
 */
abstract class AbstractRunner implements RequestHandlerInterface
{
    protected MainRepository $repository;
    private LoggerInterface $logger;

    public function __construct(MainRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    public function handle($request): ResultResponse
    {
        $session = $this->getRunner($request);
        $id = Uuid::v4();

        try {
            $params = [];
            foreach ($request->getParams() as $key => $value) {
                $params[$key] = $this->decodeToValue($value);
            }
            $result = $session->run($request->getCypher(), $params);
        } catch (Neo4jException $exception) {
            $this->logger->debug($exception->__toString());
            if ($exception->getCategory() === 'transaction' ||
                str_contains($exception->getMessage(), 'Neo.ClientError.Security.Unauthorized') ||
                str_contains($exception->getMessage(), 'ClientError')
            ) {
                $this->repository->addRecords($id, new DriverErrorResponse(
                    $this->getId($request),
                    $exception->getNeo4jCode(),
                    $exception->getNeo4jMessage(),
                ));
            } else {
                $this->repository->addRecords($id, new FrontendErrorResponse(
                    $exception->getMessage()
                ));
            }

            return new ResultResponse($id, []);
        }
        $this->repository->addRecords($id, $result);

        return new ResultResponse($id, $result->isEmpty() ? [] : $result->first()->keys());
    }

    /**
     * @param array{name: string, data: array{value: iterable|scalar|null}} $param
     *
     * @return scalar|AbstractCypherObject|iterable|null
     */
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

                return new CypherMap($list);
            }
        }

        return $value;
    }

    /**
     * @param T $request
     *
     * @return SessionInterface<SummarizedResult<CypherMap<OGMTypes>>>|TransactionInterface<SummarizedResult<CypherMap<OGMTypes>>>
     */
    abstract protected function getRunner($request);

    /**
     * @param T $request
     */
    abstract protected function getId($request): Uuid;
}
