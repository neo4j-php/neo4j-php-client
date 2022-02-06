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

use function call_user_func;
use function is_callable;
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

    /** @var string|null */
    private $database;
    /** @var pure-callable():(int|null)|int|null */
    private $fetchSize;
    /** @var pure-callable():(AccessMode|null)|AccessMode|null */
    private $accessMode;
    /** @var pure-callable():(iterable<string>|null)|iterable<string>|null */
    private $bookmarks;

    /**
     * @param string|null $database
     * @param pure-callable():(int|null)|int|null                           $fetchSize
     * @param pure-callable():(AccessMode|null)|AccessMode|null             $defaultAccessMode
     * @param pure-callable():(iterable<string>|null)|iterable<string>|null $bookmarks
     */
    public function __construct(
        $database = null,
        $fetchSize = null,
        $defaultAccessMode = null,
        $bookmarks = null
    ) {
        $this->database = $database;
        $this->fetchSize = $fetchSize;
        $this->accessMode = $defaultAccessMode;
        $this->bookmarks = $bookmarks;
    }

    /**
     * @pure
     *
     * @param string|null $database
     * @param pure-callable():(int|null)|int|null                      $fetchSize
     * @param pure-callable():(AccessMode|null)|AccessMode|null        $defaultAccessMode
     * @param pure-callable():(iterable<string>|null)|iterable<string> $bookmarks
     */
    public static function create($database = null, $fetchSize = null, $defaultAccessMode = null, $bookmarks = null): self
    {
        return new self($database, $fetchSize, $defaultAccessMode, $bookmarks);
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
     *
     * @param string|null $database
     */
    public function withDatabase($database): self
    {
        return new self($database, $this->fetchSize, $this->accessMode, $this->bookmarks);
    }

    /**
     * Creates a new session with the provided fetch size.
     *
     * @param pure-callable():(int|null)|int|null $size
     */
    public function withFetchSize($size): self
    {
        return new self($this->database, $size, $this->accessMode, $this->bookmarks);
    }

    /**
     * Creates a new session with the provided access mode.
     *
     * @param pure-callable():(AccessMode|null)|AccessMode|null $defaultAccessMode
     */
    public function withAccessMode($defaultAccessMode): self
    {
        return new self($this->database, $this->fetchSize, $defaultAccessMode, $this->bookmarks);
    }

    /**
     * Creates a new session with the provided bookmarks.
     *
     * @param pure-callable():(iterable<string>|null)|iterable<string>|null $bookmarks
     */
    public function withBookmarks($bookmarks): self
    {
        return new self($this->database, $this->fetchSize, $this->accessMode, $bookmarks);
    }

    /**
     * Returns the maximum amount of records to fetch at once.
     */
    public function getFetchSize(): int
    {
        $fetchSize = is_callable($this->fetchSize) ? call_user_func($this->fetchSize) : $this->fetchSize;

        return $fetchSize ?? self::DEFAULT_FETCH_SIZE;
    }

    /**
     * The type of access required by units of work in this session.
     *
     * @psalm-suppress ImpureMethodCall
     */
    public function getAccessMode(): AccessMode
    {
        $accessMode = is_callable($this->accessMode) ? call_user_func($this->accessMode) : $this->accessMode;

        /** @psalm-suppress ImpureMethodCall */
        return $accessMode ?? AccessMode::WRITE();
    }

    /**
     * The database where the session is going to connect to.
     */
    public function getDatabase(): string
    {
        return $this->database ?? self::DEFAULT_DATABASE;
    }

    /**
     * Returns the initial bookmarks.
     */
    public function getBookmarks(): iterable
    {
        $bookmarks = is_callable($this->bookmarks) ? call_user_func($this->bookmarks) : $this->bookmarks;

        return $bookmarks ?? [];
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
    public static function fromUri(UriInterface $uri): self
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
        if (isset($query['database'])) {
            $database = (string) $query['database'];
            $tbr = $tbr->withDatabase($database);
        }

        return $tbr;
    }
}
