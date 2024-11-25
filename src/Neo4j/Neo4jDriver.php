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

namespace Laudis\Neo4j\Neo4j;

use Bolt\error\ConnectException;
use Exception;

use function is_string;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Common\DNSAddressResolver;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AddressResolverInterface;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

/**
 * Driver for auto client-side routing.
 *
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type OGMResults from OGMFormatter
 */
final class Neo4jDriver implements DriverInterface
{
    /**
     * @param FormatterInterface<T> $formatter
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly UriInterface $parsedUrl,
        private readonly Neo4jConnectionPool $pool,
        private readonly FormatterInterface $formatter
    ) {}

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 5
     *           ? self<U>
     *           : self<OGMResults>
     *           )
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?FormatterInterface $formatter = null, ?AddressResolverInterface $resolver = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        $authenticate ??= Authenticate::fromUrl($uri, $configuration->getLogger());
        $resolver ??= new DNSAddressResolver();
        $semaphore = $configuration->getSemaphoreFactory()->create($uri, $configuration);

        /** @psalm-suppress InvalidArgument */
        return new self(
            $uri,
            Neo4jConnectionPool::create($uri, $authenticate, $configuration, $resolver, $semaphore),
            $formatter ?? OGMFormatter::create(),
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
        $config = $config->merge(SessionConfiguration::fromUri($this->parsedUrl, $this->pool->getLogger()));

        return new Session($config, $this->pool, $this->formatter);
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        $config ??= SessionConfiguration::default();
        try {
            GeneratorHelper::getReturnFromGenerator($this->pool->acquire($config));
        } catch (ConnectException $e) {
            $this->pool->getLogger()?->log(LogLevel::WARNING, 'Could not connect to server on URI '.$this->parsedUrl->__toString(), ['error' => $e]);

            return false;
        }

        return true;
    }

    public function closeConnections(): void
    {
        $this->pool->close();
    }
}
