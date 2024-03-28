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

use function array_unique;

final class Bookmark
{
    /** @var list<string> */
    private readonly array $values;

    /**
     * @param list<string> $bookmarks
     */
    public function __construct(?array $bookmarks = null)
    {
        $this->values = $bookmarks ?? [];
    }

    public function isEmpty(): bool
    {
        return count($this->values) === 0;
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @param iterable<Bookmark>|null $bookmarks
     */
    public static function from(?iterable $bookmarks): self
    {
        $bookmarks ??= [];
        $values = [];

        foreach ($bookmarks as $bookmark) {
            array_push($values, ...$bookmark->values());
            $values = array_values(array_unique($values));
        }

        return new self($values);
    }
}
