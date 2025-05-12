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

namespace Laudis\Neo4j\Databags;

/**
 * This describes the plan that the database planner produced and used (or will use) to execute your query.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/execution-plans/
 */
final class Plan
{
    /**
     * @param list<Plan>   $children
     * @param list<string> $identifiers
     */
    public function __construct(
        private readonly PlanArguments $args,
        private readonly array $children,
        private readonly array $identifiers,
        private readonly string $operator,
    ) {
    }

    /**
     * Returns the arguments for the operator.
     */
    public function getArgs(): PlanArguments
    {
        return $this->args;
    }

    /**
     * Returns the sub-plans.
     *
     * @return list<Plan>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Identifiers used by this part of the plan.
     *
     * @return list<string>
     */
    public function getIdentifiers(): array
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
            'arguments' => $this->args,
            'list' => $this->children,
            'identifiers' => $this->identifiers,
            'operator' => $this->operator,
        ];
    }
}
