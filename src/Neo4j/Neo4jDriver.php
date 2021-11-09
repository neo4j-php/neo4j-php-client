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

use Bolt\Bolt;
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
use Laudis\Neo4j\Databags\TransactionConfiguration;
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
 *
 * @psalm-immutable
 */
final class Neo4jDriver implements DriverInterface
{
    private UriInterface $parsedUrl;
    private AuthenticateInterface $auth;
    /** @var ConnectionPoolInterface<Bolt> */
    private ConnectionPoolInterface $pool;
    private DriverConfiguration $config;
    private FormatterInterface $formatter;
    private float $socketTimeout;

    /**
     * @param FormatterInterface<T>         $formatter
     * @param ConnectionPoolInterface<Bolt> $pool
     */
    public function __construct(
        UriInterface $parsedUrl,
        AuthenticateInterface $auth,
        ConnectionPoolInterface $pool,
        DriverConfiguration $config,
        FormatterInterface $formatter,
        float $socketTimeout
    ) {
        $this->parsedUrl = $parsedUrl;
        $this->auth = $auth;
        $this->pool = $pool;
        $this->config = $config;
        $this->formatter = $formatter;
        $this->socketTimeout = $socketTimeout;
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
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?float $socketTimeout = null, FormatterInterface $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $socketTimeout ??= TransactionConfiguration::DEFAULT_TIMEOUT;

        if ($formatter !== null) {
            return new self(
                $uri,
                $authenticate ?? Authenticate::fromUrl(),
                new Neo4jConnectionPool(new BoltConnectionPool()),
                $configuration ?? DriverConfiguration::default(),
                $formatter,
                $socketTimeout
            );
        }

        return new self(
            $uri,
            $authenticate ?? Authenticate::fromUrl(),
            new Neo4jConnectionPool(new BoltConnectionPool()),
            $configuration ?? DriverConfiguration::default(),
            OGMFormatter::create(),
            $socketTimeout
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
            $this->auth,
            $this->socketTimeout
        );
    }
}
