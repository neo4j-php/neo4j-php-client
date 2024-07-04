<?php

namespace Laudis\Neo4j\Bolt\Messages;

use Laudis\Neo4j\Contracts\MessageInterface;

abstract class AbstractMessage implements MessageInterface {
    public function jsonSerialize(): string
    {
        return json_encode($this->toArray());
    }

    public function __toString()
    {
        return strtoupper(__CLASS__) . ' => ' . $this->jsonSerialize();
    }

    public function toArray(): array
    {
        return [
            'message' => strtoupper(__CLASS__),
            'extra' => $this->getProperties(),
        ];
    }

    private function getProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        $tbr = [];
        foreach ($properties as $property) {
            /** @var string|int|float|list<string|int|float>|null */
            $value = $property->getValue($this);
            if ($value !== null) {
                $tbr[$property->getName()] = $value;
            }
        }

        return $tbr;
    }
}
