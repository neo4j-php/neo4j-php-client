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

use InvalidArgumentException;
use Laudis\Neo4j\Types\AbstractCypherObject;

/**
 * @psalm-immutable
 *
 * @template-extends AbstractCypherObject<string, string|Position>
 */
final class Notification extends AbstractCypherObject
{
    public function __construct(
        private readonly string $severity,
        private readonly string $description,
        private readonly string $code,
        private readonly Position $position,
        private readonly string $title,
        private readonly string $category,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return array{classification: string, category: string, title: string}
     */
    private function splitCode(): array
    {
        $parts = explode('.', $this->code, 4);
        if (count($parts) < 4) {
            throw new InvalidArgumentException('Invalid message exception code');
        }

        return [
            'classification' => $parts[1],
            'category' => $parts[2],
            'title' => $parts[3],
        ];
    }

    public function getCodeClassification(): string
    {
        return $this->splitCode()['classification'];
    }

    public function getCodeCategory(): string
    {
        return $this->splitCode()['category'];
    }

    public function getCodeTitle(): string
    {
        return $this->splitCode()['title'];
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Matches inherited return type: array<string, string|Position>.
     *
     * @psalm-external-mutation-free
     *
     * @return array<string, string|Position>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'description' => $this->description,
            'code' => $this->code,
            'position' => $this->position,
            'title' => $this->title,
            'category' => $this->category,
        ];
    }

    /**
     * If you still want a version with the position converted to array,
     * use this custom method instead of overriding toArray().
     *
     * @return array{
     *     severity: string,
     *     description: string,
     *     code: string,
     *     position: array<string, float|int|null|string>,
     *     title: string,
     *     category: string
     * }
     */
    public function toSerializedArray(): array
    {
        return [
            'severity' => $this->severity,
            'description' => $this->description,
            'code' => $this->code,
            'position' => $this->position->toArray(),
            'title' => $this->title,
            'category' => $this->category,
        ];
    }
}
