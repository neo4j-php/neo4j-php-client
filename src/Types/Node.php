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

namespace Laudis\Neo4j\Types;

use BadMethodCallException;
use Ds\Map;
use Ds\Vector;
use function get_class;
use Laudis\Neo4j\Exception\PropertyDoesNotExistException;
use function sprintf;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class Node extends AbstractCypherContainer
{
    private int $id;
    /** @var CypherList<string> */
    private CypherList $labels;
    /** @var CypherMap<OGMTypes> */
    private CypherMap $properties;

    /**
     * @param CypherList<string>  $labels
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(int $id, CypherList $labels, CypherMap $properties)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->properties = $properties;
    }

    public static function makeFromHttpNode(array $node): self
    {
        /**
         * @psalm-suppress PossiblyUndefinedStringArrayOffset
         * @psalm-suppress MixedArgumentTypeCoercion
         * @psalm-suppress MixedArgument
         */
        return new self(
            $node['id'],
            new CypherList(new Vector($node['labels'])),
            new CypherMap(new Map($node['properties']))
        );
    }

    /**
     * @return CypherList<string>
     */
    public function labels(): CypherList
    {
        return $this->labels;
    }

    /**
     * @return CypherMap<OGMTypes>
     */
    public function properties(): CypherMap
    {
        return $this->properties;
    }

    public function id(): int
    {
        return $this->id;
    }

    /**
     * @return OGMTypes
     */
    public function getProperty(string $key)
    {
        if (!$this->properties->hasKey($key)) {
            throw new PropertyDoesNotExistException(sprintf('Property "%s" does not exist on node', $key));
        }

        return $this->properties->get($key);
    }

    public function getIterator()
    {
        yield 'id' => $this->id;
        yield 'labels' => $this->labels;
        yield 'properties' => $this->properties;
    }

    /**
     * @return OGMTypes
     */
    public function __get(string $key)
    {
        return $this->getProperty($key);
    }

    /**
     * @param OGMTypes $value
     */
    public function __set(string $key, $value)
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }

    public function __isset(string $key)
    {
        return $this->properties->offsetExists($key);
    }
}
