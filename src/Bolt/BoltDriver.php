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

namespace Laudis\Neo4j\Bolt;

use Bolt\Bolt;
use Exception;
use function is_string;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Psr\Http\Message\UriInterface;

/**
 * Drives a singular bolt connections.
 *
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class BoltDriver implements DriverInterface
{
    private UriInterface $parsedUrl;
    private AuthenticateInterface $auth;
    private BoltConnectionPool $pool;
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T> $formatter
     *
     * @psalm-mutation-free
     */
    public function __construct(
        UriInterface $parsedUrl,
        AuthenticateInterface $auth,
        BoltConnectionPool $pool,
        FormatterInterface $formatter
    ) {
        $this->parsedUrl = $parsedUrl;
        $this->auth = $auth;
        $this->pool = $pool;
        $this->formatter = $formatter;
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     * @param string|UriInterface   $uri
     *
     * @return (
     *           func_num_args() is 5
     *           ? self<U>
     *           : self<OGMResults>
     *           )
     *
     * @pure
     */
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, FormatterInterface $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();

        if ($formatter !== null) {
            return new self(
                $uri,
                $authenticate ?? Authenticate::fromUrl($uri),
                new BoltConnectionPool($configuration),
                $formatter
            );
        }

        return new self(
            $uri,
            $authenticate ?? Authenticate::fromUrl($uri),
            new BoltConnectionPool($configuration),
            OGMFormatter::create(),
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $sessionConfig = SessionConfiguration::fromUri($this->parsedUrl);
        if ($config !== null) {
            $sessionConfig = $sessionConfig->merge($config);
        }

        return new Session(
            $sessionConfig,
            $this->pool,
            $this->formatter,
            $this->parsedUrl,
            $this->auth
        );
    }

    public function verifyConnectivity(): bool
    {
        return $this->pool->canConnect($this->parsedUrl, $this->auth);
    }
}
