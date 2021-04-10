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

use Exception;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\VersionDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

/**
 * @implements DriverInterface<ClientInterface>
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class HttpDriver implements DriverInterface
{
    /** @var ParsedUrl */
    private array $parsedUrl;
    public const DEFAULT_PORT = '7474';
    private HttpPsrBindings $bindings;
    private string $userAgent;
    private AuthenticateInterface $auth;
    private string $defaultDatabase;

    /**
     * @param ParsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, HttpPsrBindings $bindings, string $userAgent, AuthenticateInterface $auth, string $defaultDatabase = 'neo4j')
    {
        $this->parsedUrl = $parsedUrl;
        $this->bindings = $bindings;
        $this->userAgent = $userAgent;
        $this->auth = $auth;
        $this->defaultDatabase = $defaultDatabase;
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= SessionConfiguration::create($this->defaultDatabase);
        $url = sprintf('%s://%s:%s%s',
            $this->parsedUrl['scheme'] ?? 'http',
            $this->parsedUrl['host'] ?? '127.0.0.1',
            $this->parsedUrl['port'] ?? self::DEFAULT_PORT,
            $this->parsedUrl['path'] ?? ''
        );

        $factory = $this->bindings->getRequestFactory();

        $request = $factory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        $request = $this->auth->authenticateHttp($request, $this->parsedUrl);

        $tsx = (new VersionDiscovery($this->bindings->getClient()))->discoverTransactionUrl($request, $config->getDatabase());

        $request = $factory->createRequest('POST', $tsx)
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        $request = $this->auth->authenticateHttp($request, $this->parsedUrl);

        return new HttpSession(
            $request,
            new BasicFormatter(),
            $this,
            $config,
            $this->bindings->getStreamFactory()
        );
    }

    public function acquireConnection(SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig): ClientInterface
    {
        return $this->bindings->getClient();
    }
}
