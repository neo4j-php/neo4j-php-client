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
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\FrontendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Log\LoggerInterface;
use function str_contains;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<SessionRunRequest>
 */
final class SessionRun implements RequestHandlerInterface
{
    private MainRepository $repository;
    private LoggerInterface $logger;

    public function __construct(MainRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * @param SessionRunRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $session = $this->repository->getSession($request->getSessionId());
        $id = Uuid::v4();

        try {
            $params = $this->decodeToValue($request->getParams());
            $result = $session->run($request->getCypher(), $params);
        } catch (Neo4jException $exception) {
            $this->logger->debug($exception);
            if (str_contains($exception->getMessage(), 'ClientError')) {
                $this->repository->addRecords($id, new DriverErrorResponse(
                    $request->getSessionId(),
                    'todo',
                    $exception->getMessage(),
                    $exception->getNeo4jCode(),
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

    private function decodeToValue(iterable $params): array
    {
        $tbr = [];
        foreach ($params as $key => $param) {
            if ($param['name'] === 'CypherMap') {
                $tbr[$key] = new CypherMap(new Map($this->decodeToValue($param['data']['value'])));
            } elseif ($param['name'] === 'CypherList') {
                $tbr[$key] = new CypherList(new Vector($this->decodeToValue($param['data']['value'])));
            } else {
                $tbr[$key] = $param['data']['value'];
            }
        }

        return $tbr;
    }
}
