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
use Psr\Http\Message\UriInterface;
use function str_replace;
use function uniqid;

/**
 * @template T
 *
 * @implements DriverInterface<T>
 *
 * @psalm-type DiscoveryResult = array{
 *      bolt_routing:string,
 *      transaction: string,
 *      bolt_direct: string,
 *      neo4j_version: string,
 *      neo4j_edition: string,
 *      db/cluster?: string,
 *      dbms/cluster?: string,
 *      data?: string
 * }
 * @psalm-type DiscoveryResultLegacy = array{
 *     extensions: array,
 *     node: string,
 *     relationship: string,
 *     node_index: string,
 *     relationship_index: string,
 *     extensions_info: string,
 *     relationship_types: string,
 *     batch: string,
 *     cypher: string,
 *     indexed: string,
 *     constraints: string,
 *     transaction: string,
 *     node_labels: string,
 *     neo4j_version: string
 * }
 *
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
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
                $authenticate ?? Authenticate::fromUrl()
            );
        }

        return new self(
            $uri,
            $configuration ?? DriverConfiguration::default(),
            OGMFormatter::create(),
            $authenticate ?? Authenticate::fromUrl()
        );
    }

    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $bindings = $this->config->getHttpPsrBindings();
        $factory = Resolvable::once($this->key.':requestFactory', function () use ($bindings) {
            return new RequestFactory($bindings->getRequestFactory(), $this->auth, $this->uri, $this->config->getUserAgent());
        });
        $config ??= SessionConfiguration::default();
        $config = $config->merge(SessionConfiguration::fromUri($this->uri));
        $streamFactoryResolve = Resolvable::once($this->key.':streamFactory', static fn () => $bindings->getStreamFactory());
        $clientResolve = Resolvable::once($this->key.':client', static fn () => $bindings->getClient());

        return new HttpSession(
            $streamFactoryResolve,
            new HttpConnectionPool($clientResolve, $factory, $streamFactoryResolve),
            $config,
            $this->formatter,
            $factory,
            Resolvable::once($this->key.':tsxUrl', function () use ($config, $factory) {
                $database = $config->getDatabase();
                $request = $factory->resolve()->createRequest('GET', $this->uri);
                $client = $this->config->getHttpPsrBindings()->getClient();

                $response = $client->sendRequest($request);

                /** @var DiscoveryResultLegacy|DiscoveryResult */
                $discovery = HttpHelper::interpretResponse($response);
                $version = $discovery['neo4j_version'] ?? null;

                if ($version === null) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    $request = $request->withUri(Uri::create($discovery['data']));
                    /** @var DiscoveryResultLegacy|DiscoveryResult */
                    $discovery = HttpHelper::interpretResponse($client->sendRequest($request));
                }

                $tsx = $discovery['transaction'];

                return str_replace('{databaseName}', $database, $tsx);
            }),
            $this->auth,
            $this->config->getUserAgent()
        );
    }
}
