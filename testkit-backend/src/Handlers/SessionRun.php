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

use Bolt\error\MessageException;
use Ds\Map;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ActionInterface<SessionRunRequest>
 */
final class SessionRun implements ActionInterface
{
    private Map $sessions;
    private Map $results;

    public function __construct(Map $sessions, Map $results)
    {
        $this->sessions = $sessions;
        $this->results = $results;
    }

    /**
     * @param SessionRunRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        /** @var SessionInterface $session */
        $session = $this->sessions->get($request->getSessionId()->toRfc4122());
        try {
            $result = $session->run($request->getCypher(), $request->getParams());
        } catch (MessageException $exception) {
            return new DriverErrorResponse(
                $request->getSessionId(),
                'todo',
                $exception->getMessage(),
                'todo'
            );
        }
        $id = Uuid::v4();
        $this->results->put($id->toRfc4122(), $result->getIterator());

        return new ResultResponse($id, $result->isEmpty() ? [] : $result->first()->keys());
    }
}
