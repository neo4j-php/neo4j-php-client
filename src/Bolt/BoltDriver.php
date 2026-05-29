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

use Bolt\error\ConnectException;
use Exception;

use function is_string;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

/**
 * Drives a singular bolt connections.
 *
 * @psalm-import-type OGMResults from SummarizedResultFormatter
 */
final class BoltDriver implements DriverInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly UriInterface $parsedUrl,
        private readonly ConnectionPool $pool,
        private readonly SummarizedResultFormatter $formatter,
    ) {
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?SummarizedResultFormatter $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        $authenticate ??= Authenticate::fromUrl($uri, $configuration->getLogger());
        $semaphore = $configuration->getSemaphoreFactory()->create($uri, $configuration);

        /** @psalm-suppress InvalidArgument */
        return new self(
            $uri,
            ConnectionPool::create($uri, $authenticate, $configuration, $semaphore),
            $formatter ?? SummarizedResultFormatter::create(),
        );
    }

    /**
     * @throws Exception
     *
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        /** @psalm-suppress ImpureMethodCall */
        return new Session(
            SessionConfiguration::resolveForDriver($this->parsedUrl, $config, $this->pool->getLogger()),
            $this->pool,
            $this->formatter
        );
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        $config = SessionConfiguration::resolveForDriver($this->parsedUrl, $config, $this->pool->getLogger());
        try {
            $connection = GeneratorHelper::getConnectionFromGenerator($this->pool->acquire($config));
            $this->pool->release($connection);
        } catch (ConnectException $e) {
            $this->pool->getLogger()?->log(LogLevel::WARNING, 'Could not connect to server on URI '.$this->parsedUrl->__toString(), ['error' => $e]);

            return false;
        }

        return true;
    }

    public function getServerInfo(?SessionConfiguration $config = null): ServerInfo
    {
        $config = SessionConfiguration::resolveForDriver($this->parsedUrl, $config, $this->pool->getLogger());

        $connection = GeneratorHelper::getConnectionFromGenerator($this->pool->acquire($config));

        $serverInfo = new ServerInfo(
            $connection->getServerAddress(),
            $connection->getProtocol(),
            $connection->getServerAgent()
        );

        $this->pool->release($connection);

        return $serverInfo;
    }

    public function closeConnections(): void
    {
        $this->pool->close();
    }
}
