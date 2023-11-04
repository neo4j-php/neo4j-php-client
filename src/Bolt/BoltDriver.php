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

use function is_string;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Drives a singular bolt connections.
 *
 * @psalm-import-type OGMResults from OGMFormatter
 */
final class BoltDriver implements DriverInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private UriInterface $parsedUrl,
        private ConnectionPool $pool,
        private SummarizedResultFormatter $formatter
    ) {}

    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        $authenticate ??= Authenticate::fromUrl($uri);
        $semaphore = $configuration->getSemaphoreFactory()->create($uri, $configuration);

        return new self(
            $uri,
            ConnectionPool::create($uri, $authenticate, $configuration, $semaphore),
            SummarizedResultFormatter::create(),
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

        return new Session($sessionConfig, $this->pool, $this->formatter);
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        $config ??= SessionConfiguration::default();
        try {
            GeneratorHelper::getReturnFromGenerator($this->pool->acquire($config));
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
}
