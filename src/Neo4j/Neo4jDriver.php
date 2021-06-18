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

use Bolt\connection\StreamSocket;
use Exception;
use function is_string;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConnectionPool;
use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 *
 * @implements DriverInterface<T>
 */
final class Neo4jDriver implements DriverInterface
{
    private UriInterface $parsedUrl;
    private AuthenticateInterface $auth;
    /** @var ConnectionPoolInterface<StreamSocket> */
    private ConnectionPoolInterface $pool;
    private DriverConfiguration $config;
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T>                 $formatter
     * @param ConnectionPoolInterface<StreamSocket> $pool
     */
    public function __construct(
        UriInterface $parsedUrl,
        AuthenticateInterface $auth,
        ConnectionPoolInterface $pool,
        DriverConfiguration $config,
        FormatterInterface $formatter
    ) {
        $this->parsedUrl = $parsedUrl;
        $this->auth = $auth;
        $this->pool = $pool;
        $this->config = $config;
        $this->formatter = $formatter;
    }

    /**
     * @param string|UriInterface $uri
     */
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null): self
    {
        return self::createWithFormatter($uri, new BasicFormatter(), $configuration, $authenticate);
    }

    /**
     * @param string|UriInterface $uri
     *
     * @throws Exception
     */
    public static function createWithFormatter($uri, FormatterInterface $formatter, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        return new self(
            $uri,
            $authenticate ?? Authenticate::fromUrl(),
            new Neo4jConnectionPool(new BoltConnectionPool()),
            $configuration ?? DriverConfiguration::default(),
            $formatter
        );
    }

    /**
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
            $this->config->getUserAgent(),
            $this->parsedUrl,
            $this->auth
        );
    }
}
