<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Contracts;


use JsonSerializable;

interface TestkitResponseInterface extends JsonSerializable
{
    /**
     * @return array{name:string, data?:iterable}
     */
    public function jsonSerialize(): array;
}
