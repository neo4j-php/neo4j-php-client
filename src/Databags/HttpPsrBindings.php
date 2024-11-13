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

use function call_user_func;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

use function is_callable;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class containing all relevant implementation of the PSR-18 and PSR-17.
 *
 * @see https://www.php-fig.org/psr/psr-18/
 * @see https://www.php-fig.org/psr/psr-17/
 * @see https://www.php-fig.org/psr/psr-7/
 */
final class HttpPsrBindings
{
    /** @var ClientInterface|callable():ClientInterface */
    private $client;
    /** @var StreamFactoryInterface|callable():StreamFactoryInterface */
    private $streamFactory;
    /** @var RequestFactoryInterface|callable():RequestFactoryInterface */
    private $requestFactory;

    /**
     * @psalm-mutation-free
     *
     * @param callable():ClientInterface|ClientInterface|null                 $client
     * @param callable():StreamFactoryInterface|StreamFactoryInterface|null   $streamFactory
     * @param callable():RequestFactoryInterface|RequestFactoryInterface|null $requestFactory
     */
    public function __construct(callable|ClientInterface|null $client = null, callable|StreamFactoryInterface|null $streamFactory = null, callable|RequestFactoryInterface|null $requestFactory = null)
    {
        $this->client = $client ?? static fn (): ClientInterface => Psr18ClientDiscovery::find();
        $this->streamFactory = $streamFactory ?? static fn (): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory();
        $this->requestFactory = $requestFactory ?? static fn (): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * @pure
     *
     * @param callable():ClientInterface|ClientInterface|null                 $client
     * @param callable():StreamFactoryInterface|StreamFactoryInterface|null   $streamFactory
     * @param callable():RequestFactoryInterface|RequestFactoryInterface|null $requestFactory
     */
    public static function create(callable|ClientInterface|null $client = null, callable|StreamFactoryInterface|null $streamFactory = null, callable|RequestFactoryInterface|null $requestFactory = null): self
    {
        return new self($client, $streamFactory, $requestFactory);
    }

    /**
     * @pure
     */
    public static function default(): self
    {
        return new self();
    }

    public function getClient(): ClientInterface
    {
        if (is_callable($this->client)) {
            $this->client = call_user_func($this->client);
        }

        return $this->client;
    }

    /**
     * Creates new bindings with the provided client.
     *
     * @param ClientInterface|callable():ClientInterface $client
     */
    public function withClient(ClientInterface|callable $client): self
    {
        return new self($client, $this->streamFactory, $this->requestFactory);
    }

    /**
     * Creates new bindings with the provided stream factory.
     *
     * @param StreamFactoryInterface|callable():StreamFactoryInterface $factory
     */
    public function withStreamFactory(StreamFactoryInterface|callable $factory): self
    {
        return new self($this->client, $factory, $this->requestFactory);
    }

    /**
     * Creates new bindings with the request factory.
     *
     * @param RequestFactoryInterface|callable():RequestFactoryInterface $factory
     */
    public function withRequestFactory(RequestFactoryInterface|callable $factory): self
    {
        return new self($this->client, $this->streamFactory, $factory);
    }

    public function getStreamFactory(): StreamFactoryInterface
    {
        if (is_callable($this->streamFactory)) {
            $this->streamFactory = call_user_func($this->streamFactory);
        }

        return $this->streamFactory;
    }

    public function getRequestFactory(): RequestFactoryInterface
    {
        if (is_callable($this->requestFactory)) {
            $this->requestFactory = call_user_func($this->requestFactory);
        }

        return $this->requestFactory;
    }
}
