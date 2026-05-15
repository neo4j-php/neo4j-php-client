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
 * @psalm-immutable
 */
final class Bookmark
{
    /** @var list<string> */
    private readonly array $values;

    /**
     * @param list<string> $bookmarks
     *
     * @psalm-mutation-free
     */
    public function __construct(?array $bookmarks = null)
    {
        $this->values = $bookmarks ?? [];
    }

    /**
     * @psalm-mutation-free
     */
    public function isEmpty(): bool
    {
        return count($this->values) === 0;
    }

    /**
     * @return list<string>
     *
     * @psalm-mutation-free
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @param iterable<Bookmark>|null $bookmarks
     *
     * @psalm-mutation-free
     */
    public static function from(?iterable $bookmarks): self
    {
        $bookmarks ??= [];
        $values = [];

        foreach ($bookmarks as $bookmark) {
            foreach ($bookmark->values() as $candidate) {
                $duplicate = false;
                foreach ($values as $existing) {
                    if ($existing === $candidate) {
                        $duplicate = true;
                        break;
                    }
                }
                if (!$duplicate) {
                    $values[] = $candidate;
                }
            }
        }

        return new self($values);
    }
}
