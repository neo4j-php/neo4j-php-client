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
 * @psalm-import-type OGMResults from OGMFormatter
 */
final class HttpDriver implements DriverInterface
{
    private readonly string $key;

    /**
     * @psalm-mutation-free
     *
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        private readonly UriInterface $uri,
        private readonly DriverConfiguration $config,
        private readonly FormatterInterface $formatter,
        private readonly AuthenticateInterface $auth
    ) {
        /** @psalm-suppress ImpureFunctionCall */
        $this->key = uniqid();
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 4
     *           ? self<U>
     *           : self<OGMResults>
     *           )
     *
     * @pure
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?FormatterInterface $formatter = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        if ($formatter !== null) {
            return new self(
                $uri,
                $configuration,
                $formatter,
                $authenticate ?? Authenticate::fromUrl($uri, $configuration->getLogger())
            );
        }

        return new self(
            $uri,
            $configuration,
            OGMFormatter::create(),
            $authenticate ?? Authenticate::fromUrl($uri, $configuration->getLogger())
        );
    }

    /**
     * @psalm-external-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $factory = $this->resolvableFactory();
        $config ??= SessionConfiguration::default();
        $config = $config->merge(SessionConfiguration::fromUri($this->uri, null));
        $streamFactoryResolve = $this->streamFactory();

        $tsxUrl = $this->tsxUrl($config);

        return new HttpSession(
            $streamFactoryResolve,
            $this->getHttpConnectionPool($tsxUrl),
            $config,
            $this->formatter,
            $factory,
            $tsxUrl,
            $this->auth,
            $this->config->getUserAgent()
        );
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        $config ??= SessionConfiguration::default();

        return $this->getHttpConnectionPool($this->tsxUrl($config))
                    ->canConnect($this->uri, $this->auth);
    }

    /**
     * @param Resolvable<string> $tsxUrl
     *
     * @psalm-mutation-free
     */
    private function getHttpConnectionPool(Resolvable $tsxUrl): HttpConnectionPool
    {
        return new HttpConnectionPool(
            Resolvable::once($this->key.':client', fn () => $this->config->getHttpPsrBindings()->getClient()),
            $this->resolvableFactory(),
            $this->streamFactory(),
            $this->auth,
            $this->config->getUserAgent(),
            $tsxUrl
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

    /**
     * @return Resolvable<string>
     *
     * @psalm-mutation-free
     */
    private function tsxUrl(SessionConfiguration $config): Resolvable
    {
        return Resolvable::once($this->key.':tsxUrl', function () use ($config) {
            $database = $config->getDatabase() ?? 'neo4j';
            $request = $this->resolvableFactory()->resolve()->createRequest('GET', $this->uri);
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
        });
    }

    public function closeConnections(): void
    {
        // Nothing to close in the current HTTP Protocol implementation
    }
}
