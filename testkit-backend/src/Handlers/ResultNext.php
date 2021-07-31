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
use Iterator;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\ResultNextRequest;
use Laudis\Neo4j\TestkitBackend\Responses\NullRecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;

/**
 * @implements ActionInterface<ResultNextRequest>
 */
final class ResultNext implements ActionInterface
{
    private Map $results;

    public function __construct(Map $results)
    {
        $this->results = $results;
    }

    /**
     * @param ResultNextRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        /** @var Iterator $iterator */
        $iterator = $this->results->get($request->getResultId()->toRfc4122());

        if (!$iterator->valid()) {
            return new NullRecordResponse();
        }

        $current = $iterator->current()->values();
        $iterator->next();

        $values = [];
        foreach ($current as $value) {
            $values[] = CypherObject::autoDetect($value);
        }

        return new RecordResponse($values);
    }
}
