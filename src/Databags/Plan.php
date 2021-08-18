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

use Exception;
use Laudis\Neo4j\Types\AbstractCypherContainer;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Traversable;

final class Plan extends AbstractCypherContainer
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
     * @return CypherMap<mixed>
     */
    public function getArguments(): CypherMap
    {
        return $this->arguments;
    }

    /**
     * @return CypherList<Plan>
     */
    public function getList(): CypherList
    {
        return $this->list;
    }

    /**
     * @return CypherList<String>
     */
    public function getIdentifiers(): CypherList
    {
        return $this->identifiers;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getIterator()
    {
        yield 'arguments' => $this->arguments;
        yield 'list' => $this->list;
        yield 'identifiers' => $this->identifiers;
        yield 'operator' => $this->operator;
    }
}
