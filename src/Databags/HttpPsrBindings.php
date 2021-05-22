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
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use function is_callable;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class HttpPsrBindings
{
    /** @var ClientInterface|callable():ClientInterface */
    private $client;
    /** @var StreamFactoryInterface|callable():StreamFactoryInterface */
    private $streamFactory;
    /** @var RequestFactoryInterface|callable():RequestFactoryInterface */
    private $requestFactory;

    /**
     * @param ClientInterface|callable():ClientInterface|null                 $client
     * @param StreamFactoryInterface|callable():StreamFactoryInterface|null   $streamFactory
     * @param RequestFactoryInterface|callable():RequestFactoryInterface|null $requestFactory
     */
    public function __construct($client = null, $streamFactory = null, $requestFactory = null)
    {
        $this->client = $client ?? static function (): ClientInterface {
            return Psr18ClientDiscovery::find();
        };
        $this->streamFactory = $streamFactory ?? static function (): StreamFactoryInterface {
            return Psr17FactoryDiscovery::findStreamFactory();
        };
        $this->requestFactory = $requestFactory ?? static function (): RequestFactoryInterface {
            return Psr17FactoryDiscovery::findRequestFactory();
        };
    }

    /**
     * @param ClientInterface|callable():ClientInterface|null                 $client
     * @param StreamFactoryInterface|callable():StreamFactoryInterface|null   $streamFactory
     * @param RequestFactoryInterface|callable():RequestFactoryInterface|null $requestFactory
     * @param UriFactoryInterface|callable():UriFactoryInterface|null         $uriFactory
     */
    public static function create($client = null, $streamFactory = null, $requestFactory = null): self
    {
        return new self($client, $streamFactory, $requestFactory);
    }

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
     * @param ClientInterface|callable():ClientInterface $client
     */
    public function withClient($client): self
    {
        return new self($client, $this->streamFactory, $this->requestFactory);
    }

    /**
     * @param StreamFactoryInterface|callable():StreamFactoryInterface $factory
     */
    public function withStreamFactory($factory): self
    {
        return new self($this->client, $factory, $this->requestFactory);
    }

    /**
     * @param RequestFactoryInterface|callable():RequestFactoryInterface $factory
     */
    public function withRequestFactory($factory): self
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
