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

namespace Laudis\Neo4j\Network\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Laudis\Neo4j\Contracts\Injections;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpInjections implements Injections
{
    /** @var ClientInterface|callable():ClientInterface */
    private $client;
    /** @var StreamFactoryInterface|callable():StreamFactoryInterface */
    private $streamFactory;
    /** @var RequestFactoryInterface|callable():RequestFactoryInterface */
    private $requestFactory;
    /** @var string|callable():string */
    private $database;
    /** @var bool|callable():bool */
    private $autoRouting;

    /**
     * Injector constructor.
     *
     * @param string|callable():string|null                                   $database
     * @param ClientInterface|callable():ClientInterface|null                 $client
     * @param StreamFactoryInterface|callable():StreamFactoryInterface|null   $streamFactory
     * @param RequestFactoryInterface|callable():RequestFactoryInterface|null $requestFactory
     * @param bool|callable():bool                                            $autoRouting
     */
    public function __construct($database = null, $client = null, $streamFactory = null, $requestFactory = null, $autoRouting = null)
    {
        $this->database = $database ?? static function (): string {
            return 'neo4j';
        };
        $this->client = $client ?? static function (): ClientInterface {
            return Psr18ClientDiscovery::find();
        };
        $this->streamFactory = $streamFactory ?? static function (): StreamFactoryInterface {
            return Psr17FactoryDiscovery::findStreamFactory();
        };
        $this->requestFactory = $requestFactory ?? static function (): RequestFactoryInterface {
            return Psr17FactoryDiscovery::findRequestFactory();
        };
        $this->autoRouting = $autoRouting ?? false;
    }

    public static function create(): self
    {
        return new self();
    }

    public function client(): ClientInterface
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
        return new self($this->database, $client, $this->streamFactory, $this->requestFactory, $this->autoRouting);
    }

    /**
     * @param StreamFactoryInterface|callable():StreamFactoryInterface $factory
     */
    public function withStreamFactory($factory): self
    {
        return new self($this->database, $this->client, $factory, $this->requestFactory, $this->autoRouting);
    }

    /**
     * @param RequestFactoryInterface|callable():RequestFactoryInterface $factory
     */
    public function withRequestFactory($factory): self
    {
        return new self($this->database, $this->client, $this->streamFactory, $factory, $this->autoRouting);
    }

    /**
     * @param string|callable():string $database
     */
    public function withDatabase($database): self
    {
        return new self($database, $this->client, $this->streamFactory, $this->requestFactory, $this->autoRouting);
    }

    public function streamFactory(): StreamFactoryInterface
    {
        if (is_callable($this->streamFactory)) {
            $this->streamFactory = call_user_func($this->streamFactory);
        }

        return $this->streamFactory;
    }

    public function requestFactory(): RequestFactoryInterface
    {
        if (is_callable($this->requestFactory)) {
            $this->requestFactory = call_user_func($this->requestFactory);
        }

        return $this->requestFactory;
    }

    public function database(): string
    {
        if (is_string($this->database)) {
            return $this->database;
        }

        /** @var string */
        $this->database = call_user_func($this->database);

        return $this->database;
    }

    public function withAutoRouting($routing): Injections
    {
        return new self(
            $this->database,
            $this->client,
            $this->streamFactory,
            $this->requestFactory,
            $routing
        );
    }

    public function hasAutoRouting(): bool
    {
        if (is_callable($this->autoRouting)) {
            $this->autoRouting = call_user_func($this->autoRouting);
        }

        return $this->autoRouting;
    }
}
