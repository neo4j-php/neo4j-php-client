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

namespace Laudis\Neo4j\Bolt;

use Exception;
use Laudis\Neo4j\Common\SingleThreadedSemaphore;
use Laudis\Neo4j\Common\SysVSemaphore;
use function extension_loaded;
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
use Throwable;

/**
 * Drives a singular bolt connections.
 *
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type OGMResults from OGMFormatter
 */
final class BoltDriver implements DriverInterface
{
    private UriInterface $parsedUrl;
    private ConnectionPool $pool;
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T> $formatter
     *
     * @psalm-mutation-free
     */
    public function __construct(
        UriInterface $parsedUrl,
        ConnectionPool $pool,
        FormatterInterface $formatter
    ) {
        $this->parsedUrl = $parsedUrl;
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
     */
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, FormatterInterface $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        $authenticate ??= Authenticate::fromUrl($uri);

        // Because interprocess switching of connections between PHP sessions is impossible,
        // we have to build a key to limit the amount of open connections, potentially between ALL sessions.
        // because of this we have to settle on a configuration basis to limit the connection pool,
        // not on an object basis.
        // The combination is between the server and the user agent as it most closely resembles an "application"
        // connecting to a server. The application thus supports multiple authentication methods, but they have
        // to be shared between the same connection pool.
        $key = $uri->getHost().':'.$uri->getPort().':'.$config->getUserAgent();
        if (extension_loaded('ext-sysvsem')) {
            $semaphore = SysVSemaphore::create($key, $config->getMaxPoolSize());
        } else {
            $semaphore = SingleThreadedSemaphore::create($key, $config->getMaxPoolSize());
        }

        if ($formatter !== null) {
            return new self(
                $uri,
                new ConnectionPool($uri, $configuration, $authenticate),
                $formatter
            );
        }

        return new self(
            $uri,
            new ConnectionPool($uri, $configuration, $authenticate),
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
            $this->parsedUrl
        );
    }

    public function verifyConnectivity(): bool
    {
        try {
            $this->pool->acquire(SessionConfiguration::default());
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
