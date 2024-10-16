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
        private readonly int|null $fetchSize = null,
        private readonly AccessMode|null $accessMode = null,
        private readonly array|null $bookmarks = null,
        private readonly ?Neo4jLogger $logger = null,
    ) {}

    /**
     * @pure
     *
     * @param list<Bookmark>|null $bookmarks
     */
    public static function create(string|null $database = null, int|null $fetchSize = null, AccessMode|null $defaultAccessMode = null, array|null $bookmarks = null, ?Neo4jLogger $logger = null): self
    {
        return new self($database, $fetchSize, $defaultAccessMode, $bookmarks, $logger);
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
        return new self($database, $this->fetchSize, $this->accessMode, $this->bookmarks, $this->logger);
    }

    /**
     * Creates a new session with the provided fetch size.
     */
    public function withFetchSize(?int $size): self
    {
        return new self($this->database, $size, $this->accessMode, $this->bookmarks, $this->logger);
    }

    /**
     * Creates a new session with the provided access mode.
     */
    public function withAccessMode(?AccessMode $defaultAccessMode): self
    {
        return new self($this->database, $this->fetchSize, $defaultAccessMode, $this->bookmarks, $this->logger);
    }

    /**
     * Creates a new session with the provided bookmarks.
     *
     * @param list<Bookmark>|null $bookmarks
     */
    public function withBookmarks(?array $bookmarks): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $bookmarks, $this->logger);
    }

    /**
     * Creates a new session with the provided logger.
     */
    public function withLogger(?Neo4jLogger $logger): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $this->bookmarks, $logger);
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
    public function getAccessMode(): AccessMode
    {
        return $this->accessMode ?? AccessMode::WRITE();
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
            $this->bookmarks ?? $config->bookmarks
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
