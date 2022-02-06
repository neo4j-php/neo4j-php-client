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

namespace Laudis\Neo4j\Http;

use function is_string;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\Resolvable;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use function str_replace;
use function uniqid;

/**
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class HttpDriver implements DriverInterface
{
    private UriInterface $uri;
    private AuthenticateInterface $auth;
    private DriverConfiguration $config;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private string $key;

    /**
     * @psalm-mutation-free
     *
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        UriInterface $uri,
        DriverConfiguration $config,
        FormatterInterface $formatter,
        AuthenticateInterface $auth
    ) {
        $this->uri = $uri;
        $this->config = $config;
        $this->formatter = $formatter;
        $this->auth = $auth;
        /** @psalm-suppress ImpureFunctionCall */
        $this->key = uniqid();
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     * @param string|UriInterface   $uri
     *
     * @return (
     *           func_num_args() is 4
     *           ? self<U>
     *           : self<OGMResults>
     *           )
     *
     * @pure
     */
    public static function create($uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, FormatterInterface $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        if ($formatter !== null) {
            return new self(
                $uri,
                $configuration ?? DriverConfiguration::default(),
                $formatter,
                $authenticate ?? Authenticate::fromUrl($uri)
            );
        }

        return new self(
            $uri,
            $configuration ?? DriverConfiguration::default(),
            OGMFormatter::create(),
            $authenticate ?? Authenticate::fromUrl($uri)
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $factory = $this->resolvableFactory();
        $config ??= SessionConfiguration::default();
        $config = $config->merge(SessionConfiguration::fromUri($this->uri));
        $streamFactoryResolve = $this->streamFactory();

        return new HttpSession(
            $streamFactoryResolve,
            $this->getHttpConnectionPool(),
            $config,
            $this->formatter,
            $factory,
            Resolvable::once($this->key.':tsxUrl', function () use ($config, $factory) {
                $database = $config->getDatabase();
                $request = $factory->resolve()->createRequest('GET', $this->uri);
                $client = $this->config->getHttpPsrBindings()->getClient();

                $response = $client->sendRequest($request);

                $discovery = HttpHelper::interpretResponse($response);
                /** @var string|null */
                $version = $discovery->neo4j_version ?? null;

                if ($version === null) {
                    /** @var string */
                    $uri = $discovery->data;
                    $request = $request->withUri(Uri::create($uri));
                    $discovery = HttpHelper::interpretResponse($client->sendRequest($request));
                }

                /** @var string */
                $tsx = $discovery->transaction;

                return str_replace('{databaseName}', $database, $tsx);
            }),
            $this->auth,
            $this->config->getUserAgent()
        );
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        return $this->getHttpConnectionPool()->canConnect($this->uri, $this->auth);
    }

    /**
     * @psalm-mutation-free
     */
    private function getHttpConnectionPool(): HttpConnectionPool
    {
        return new HttpConnectionPool(
            Resolvable::once($this->key.':client', fn () => $this->config->getHttpPsrBindings()->getClient()),
            $this->resolvableFactory(),
            $this->streamFactory()
        );
    }

    /**
     * @return Resolvable<RequestFactory>
     *
     * @psalm-mutation-free
     */
    private function resolvableFactory(): Resolvable
    {
        return Resolvable::once($this->key.':requestFactory', function () {
            $bindings = $this->config->getHttpPsrBindings();

            return new RequestFactory($bindings->getRequestFactory(), $this->auth, $this->uri, $this->config->getUserAgent());
        });
    }

    /**
     * @return Resolvable<StreamFactoryInterface>
     *
     * @psalm-mutation-free
     */
    private function streamFactory(): Resolvable
    {
        return Resolvable::once($this->key.':streamFactory', fn () => $this->config->getHttpPsrBindings()->getStreamFactory());
    }
}
