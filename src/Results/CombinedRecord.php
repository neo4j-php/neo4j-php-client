<?php

namespace Laudis\Neo4j\Results;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Bolt\protocol\IStructure;
use IteratorAggregate;
use Laudis\Neo4j\Bolt\Responses\Record;
use Laudis\Neo4j\Bolt\Responses\RunResponse;
use RectorPrefix202407\Illuminate\Contracts\Support\Arrayable;
use Traversable;

/**
 * @implements ArrayAccess<string, null|int|float|bool|string|array|IStructure>
 * @implements Arrayable<string, null|int|float|bool|string|array|IStructure>
 * @implements IteratorAggregate<string, null|int|float|bool|string|array|IStructure>
 */
class CombinedRecord implements ArrayAccess, Arrayable, IteratorAggregate {

    /** @var array<string, null|int|float|bool|string|array|IStructure>|null  */
    private array|null $combinedCache = null;

    public function __construct(private readonly RunResponse $response, private readonly Record $record)
    {

    }

    public function toArray(): array
    {
        if ($this->combinedCache === null) {
            $this->combinedCache = array_combine($this->response->fields, $this->record->values);
        }

        return $this->combinedCache;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, $this->response->fields);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? throw new \InvalidArgumentException('Offset ' . $offset . ' does not exist.');
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Cannot modify a record');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Cannot modify a record');
    }

    public function single(): null|int|float|bool|string|array|IStructure
    {
        return $this->response->fields[0];
    }
}
