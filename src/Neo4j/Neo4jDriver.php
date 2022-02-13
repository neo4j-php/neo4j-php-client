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

namespace Laudis\Neo4j\Neo4j;

use Exception;
use function is_string;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConnectionPool;
use Laudis\Neo4j\Bolt\Session;
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
 * Driver for auto client-side routing.
 *
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class Neo4jDriver implements DriverInterface
{
    private UriInterface $parsedUrl;
    private AuthenticateInterface $auth;
    private Neo4jConnectionPool $pool;
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T> $formatter
     *
     * @psalm-mutation-free
     */
    public function __construct(
        UriInterface $parsedUrl,
        AuthenticateInterface $auth,
        Neo4jConnectionPool $pool,
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
                new Neo4jConnectionPool(new BoltConnectionPool($configuration)),
                $formatter,
            );
        }

        return new self(
            $uri,
            $authenticate ?? Authenticate::fromUrl($uri),
            new Neo4jConnectionPool(new BoltConnectionPool($configuration)),
            OGMFormatter::create(),
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= SessionConfiguration::default();
        $config = $config->merge(SessionConfiguration::fromUri($this->parsedUrl));

        return new Session(
            $config,
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
