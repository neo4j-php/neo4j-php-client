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

use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\Neo4jBookmarkManagerHooksInterface;
use Laudis\Neo4j\Enum\AccessMode;

use function parse_str;

use Psr\Http\Message\UriInterface;

/**
 * Configuration class for a Session.
 *
 * @psalm-immutable
 */
final class SessionConfiguration
{
    public const DEFAULT_DATABASE = 'neo4j';
    public const DEFAULT_FETCH_SIZE = 1000;
    public const DEFAULT_ACCESS_MODE = 'WRITE';
    public const DEFAULT_BOOKMARKS = '[]';

    /**
     * @param list<Bookmark>|null $bookmarks
     */
    public function __construct(
        private readonly ?string $database = null,
        private readonly ?int $fetchSize = null,
        private readonly ?AccessMode $accessMode = null,
        private readonly ?array $bookmarks = null,
        private readonly ?Neo4jLogger $logger = null,
        private readonly ?BookmarkHolder $bookmarkHolder = null,
        private readonly ?Neo4jBookmarkManagerHooksInterface $bookmarkManagerHooks = null,
    ) {
    }

    /**
     * @pure
     *
     * @param list<Bookmark>|null $bookmarks
     */
    public static function create(
        ?string $database = null,
        ?int $fetchSize = null,
        ?AccessMode $defaultAccessMode = null,
        ?array $bookmarks = null,
        ?Neo4jLogger $logger = null,
        ?BookmarkHolder $bookmarkHolder = null,
        ?Neo4jBookmarkManagerHooksInterface $bookmarkManagerHooks = null,
    ): self {
        return new self($database, $fetchSize, $defaultAccessMode, $bookmarks, $logger, $bookmarkHolder, $bookmarkManagerHooks);
    }

    /**
     * @pure
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Creates a new session with the provided database.
     */
    public function withDatabase(?string $database): self
    {
        return new self($database, $this->fetchSize, $this->accessMode, $this->bookmarks, $this->logger, $this->bookmarkHolder, $this->bookmarkManagerHooks);
    }

    /**
     * Creates a new session with the provided fetch size.
     */
    public function withFetchSize(?int $size): self
    {
        return new self($this->database, $size, $this->accessMode, $this->bookmarks, $this->logger, $this->bookmarkHolder, $this->bookmarkManagerHooks);
    }

    /**
     * Creates a new session with the provided access mode.
     */
    public function withAccessMode(?AccessMode $defaultAccessMode): self
    {
        return new self($this->database, $this->fetchSize, $defaultAccessMode, $this->bookmarks, $this->logger, $this->bookmarkHolder, $this->bookmarkManagerHooks);
    }

    /**
     * Creates a new session with the provided bookmarks.
     *
     * @param list<Bookmark>|null $bookmarks
     */
    public function withBookmarks(?array $bookmarks): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $bookmarks, $this->logger, $this->bookmarkHolder, $this->bookmarkManagerHooks);
    }

    /**
     * Creates a new session with the provided logger.
     */
    public function withLogger(?Neo4jLogger $logger): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $this->bookmarks, $logger, $this->bookmarkHolder, $this->bookmarkManagerHooks);
    }

    /**
     * Use a shared bookmark holder (e.g. Neo4j bookmark manager) instead of only static session bookmarks.
     */
    public function withBookmarkHolder(?BookmarkHolder $bookmarkHolder): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $this->bookmarks, $this->logger, $bookmarkHolder, $this->bookmarkManagerHooks);
    }

    public function withBookmarkManagerHooks(?Neo4jBookmarkManagerHooksInterface $bookmarkManagerHooks): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $this->bookmarks, $this->logger, $this->bookmarkHolder, $bookmarkManagerHooks);
    }

    public function getBookmarkHolder(): ?BookmarkHolder
    {
        return $this->bookmarkHolder;
    }

    public function getBookmarkManagerHooks(): ?Neo4jBookmarkManagerHooksInterface
    {
        return $this->bookmarkManagerHooks;
    }

    /**
     * Bookmarks from session configuration (initial list and holder) without supplier-hook extras.
     *
     * For the full wire bookmark set including supplier callbacks, use {@see SessionWireBookmarks::resolve()}.
     */
    public function getSessionBookmarksForWire(): Bookmark
    {
        /** @var list<string> $values */
        $values = [];

        foreach ($this->bookmarks ?? [] as $bookmark) {
            foreach ($bookmark->values() as $candidate) {
                if (!$this->bookmarkValuesContain($values, $candidate)) {
                    $values[] = $candidate;
                }
            }
        }

        if ($this->bookmarkHolder !== null) {
            $fromHolder = $this->bookmarkHolder->getBookmark();
            if (!$fromHolder->isEmpty()) {
                foreach ($fromHolder->values() as $candidate) {
                    if (!$this->bookmarkValuesContain($values, $candidate)) {
                        $values[] = $candidate;
                    }
                }
            }
        }

        return new Bookmark($values);
    }

    /**
     * @param list<string> $values
     *
     * @psalm-mutation-free
     */
    private function bookmarkValuesContain(array $values, string $candidate): bool
    {
        foreach ($values as $existing) {
            if ($existing === $candidate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the maximum amount of records to fetch at once.
     */
    public function getFetchSize(): int
    {
        return $this->fetchSize ?? self::DEFAULT_FETCH_SIZE;
    }

    /**
     * The type of access required by units of work in this session.
     *
     * @psalm-suppress ImpureMethodCall
     */
    public function getAccessMode(): ?AccessMode
    {
        return $this->accessMode;
    }

    /**
     * The database where the session is going to connect to.
     */
    public function getDatabase(): ?string
    {
        return $this->database;
    }

    /**
     * Returns the initial bookmarks.
     *
     * @return list<Bookmark>
     */
    public function getBookmarks(): array
    {
        return $this->bookmarks ?? [];
    }

    public function getLogger(): ?Neo4jLogger
    {
        return $this->logger;
    }

    /**
     * Creates a new configuration by merging the provided configuration with the current one.
     * The set values of the provided configuration will override the values of this configuration.
     */
    public function merge(SessionConfiguration $config): self
    {
        return new self(
            $this->database ?? $config->database,
            $this->fetchSize ?? $config->fetchSize,
            $this->accessMode ?? $config->accessMode,
            $this->bookmarks ?? $config->bookmarks,
            $this->logger ?? $config->logger,
            $this->bookmarkHolder ?? $config->bookmarkHolder,
            $this->bookmarkManagerHooks ?? $config->bookmarkManagerHooks
        );
    }

    /**
     * Creates a session configuration from the information found within the uri.
     *
     * @pure
     */
    public static function fromUri(UriInterface $uri, ?Neo4jLogger $logger): self
    {
        /**
         * @psalm-suppress ImpureMethodCall Uri is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        $uri = $uri->getQuery();
        /** @psalm-suppress ImpureFunctionCall */
        parse_str($uri, $query);
        $tbr = SessionConfiguration::default();

        if ($logger !== null) {
            $tbr = $tbr->withLogger($logger);
        }

        if (array_key_exists('database', $query)) {
            $database = (string) $query['database'];
            $tbr = $tbr->withDatabase($database);
        }

        return $tbr;
    }
}
