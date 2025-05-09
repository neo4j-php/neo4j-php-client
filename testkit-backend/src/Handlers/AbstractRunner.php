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
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;
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
            if ($request->getParams() !== null) {
                foreach ($request->getParams() as $key => $value) {
                    $params[$key] = $this->decodeToValue($value);
                }
            }

            if ($request instanceof SessionRunRequest && $session instanceof SessionInterface) {
                $metaData = $request->getTxMeta();
                $actualMeta = [];
                if ($metaData !== null) {
                    foreach ($metaData as $key => $meta) {
                        $actualMeta[$key] = $this->decodeToValue($meta);
                    }
                }
                $config = TransactionConfiguration::default()->withMetadata($actualMeta)->withTimeout($request->getTimeout());

                $result = $session->run($request->getCypher(), $params, $config);
            } else {
                $result = $session->run($request->getCypher(), $params);
            }

            $this->repository->addRecords($id, $result);

            return new ResultResponse($id, $result->isEmpty() ? [] : $result->first()->keys());
        } catch (Neo4jException $exception) {
            $this->logger->debug($exception->__toString());
            $this->repository->addRecords($id, new DriverErrorResponse(
                $this->getId($request),
                $exception
            ));

            return new ResultResponse($id, []);
        } // NOTE: all other exceptions will be caught in the Backend
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

                return new CypherList($list);
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
