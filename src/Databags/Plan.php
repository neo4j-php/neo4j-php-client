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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * This describes the plan that the database planner produced and used (or will use) to execute your query.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/execution-plans/
 *
 * @psalm-immutable
 */
final class Plan extends AbstractCypherObject
{
    /** @var CypherMap<mixed> */
    private CypherMap $arguments;
    /** @var CypherList<Plan> */
    private CypherList $list;
    /** @var CypherList<string> */
    private CypherList $identifiers;
    private string $operator;

    /**
     * @param CypherMap<mixed>   $arguments
     * @param CypherList<Plan>   $list
     * @param CypherList<string> $identifiers
     */
    public function __construct(
        CypherMap $arguments,
        CypherList $list,
        CypherList $identifiers,
        string $operator
    ) {
        $this->arguments = $arguments;
        $this->list = $list;
        $this->identifiers = $identifiers;
        $this->operator = $operator;
    }

    /**
     * Returns the arguments for the operator.
     *
     * @return CypherMap<mixed>
     */
    public function getArguments(): CypherMap
    {
        return $this->arguments;
    }

    /**
     * Returns the sub-plans.
     *
     * @return CypherList<Plan>
     */
    public function getList(): CypherList
    {
        return $this->list;
    }

    /**
     * Identifiers used by this part of the plan.
     *
     * @return CypherList<string>
     */
    public function getIdentifiers(): CypherList
    {
        return $this->identifiers;
    }

    /**
     * The operation this plan is performing.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    public function toArray(): array
    {
        return [
            'arguments' => $this->arguments,
            'list' => $this->list,
            'identifiers' => $this->identifiers,
            'operator' => $this->operator,
        ];
    }
}
