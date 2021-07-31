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

namespace Laudis\Neo4j\TestkitBackend\Actions;

use Bolt\error\MessageException;
use Ds\Map;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\Types\CypherList;
use Symfony\Component\Uid\Uuid;

final class SessionRun implements ActionInterface
{
    private Map $sessions;
    private Map $results;

    public function __construct(Map $sessions, Map $results)
    {
        $this->sessions = $sessions;
        $this->results = $results;
    }

    public function handle(array $data): array
    {
        $id = $data['sessionId'];
        $cypher = $data['cypher'];
        $params = [];
        if ($data['params'] !== null) {
            $params = $data['params'];
        }

        /** @var SessionInterface $session */
        $session = $this->sessions->get($id);
        try {
            /** @var CypherList $result */
            $result = $session->run($cypher, $params);
        } catch (MessageException $messageException) {
            return [
                'name' => 'DriverError',
            ];
        }
        $id = Uuid::v4()->toRfc4122();
        $this->results->put($id, $result->getIterator());

        return [
            'name' => 'Result',
            'data' => [
                'id' => $id,
                'keys' => $result->isEmpty() ? [] : $result->first()->keys(),
            ],
        ];
    }
}
