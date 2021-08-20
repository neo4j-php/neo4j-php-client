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

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
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
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
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
            $params = $this->decodeToValue($request->getParams());
            $result = $session->run($request->getCypher(), $params);
        } catch (Neo4jException|InvalidTransactionStateException $exception) {
            $this->logger->debug($exception->__toString());
            if ($exception instanceof InvalidTransactionStateException || (str_contains($exception->getMessage(), 'ClientError') && !str_contains($exception->getMessage(), 'ArithmeticError'))) {
                $this->repository->addRecords($id, new DriverErrorResponse(
                    $this->getId($request),
                    'todo',
                    $exception->getMessage(),
                    $exception instanceof Neo4jException ? $exception->getNeo4jCode() : 'n/a',
                ));
            } else {
                $this->repository->addRecords($id, new FrontendErrorResponse(
                    $exception->getMessage()
                ));
            }

            return new ResultResponse($id, []);
        }
        $this->repository->addRecords($id, $result);

        return new ResultResponse($id, $result->getResult()->isEmpty() ? [] : $result->getResult()->first()->keys());
    }

    /**
     * @param iterable<string, array{name: string, data: array{value: iterable|scalar|null}}> $params
     *
     * @return array<string, scalar|CypherContainerInterface|iterable|null>
     */
    private function decodeToValue(iterable $params): array
    {
        $tbr = [];
        foreach ($params as $key => $param) {
            if (is_iterable($param['data']['value'])) {
                if ($param['name'] === 'CypherMap') {
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $tbr[$key] = new CypherMap(new Map($this->decodeToValue($param['data']['value'])));
                    continue;
                }

                if ($param['name'] === 'CypherList') {
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $tbr[$key] = new CypherList(new Vector($this->decodeToValue($param['data']['value'])));
                    continue;
                }
            }
            $tbr[$key] = $param['data']['value'];
        }

        return $tbr;
    }

    /**
     * @param T $request
     *
     * @return SessionInterface<SummarizedResult<OGMResults>>|TransactionInterface<SummarizedResult<OGMResults>>
     */
    abstract protected function getRunner($request);

    /**
     * @param T $request
     */
    abstract protected function getId($request): Uuid;
}
