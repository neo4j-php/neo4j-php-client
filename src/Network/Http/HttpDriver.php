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

use JsonException;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Formatter\HttpCypherFormatter;
use Laudis\Neo4j\HttpDriver\RequestFactory;
use Laudis\Neo4j\Network\AutoRoutedSession;
use Laudis\Neo4j\Network\VersionDiscovery;
use Psr\Http\Client\ClientExceptionInterface;

final class HttpDriver implements DriverInterface
{
    /** @var array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} */
    private array $parsedUrl;
    private ?SessionInterface $session = null;
    private VersionDiscovery $decorator;
    public const DEFAULT_PORT = '7474';
    private HttpInjections $injections;

    /**
     * HttpConnection constructor.
     *
     * @param array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} $parsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, VersionDiscovery $decorator, HttpInjections $injector)
    {
        $this->parsedUrl = $parsedUrl;
        $this->decorator = $decorator;
        $this->injections = $injector;
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function aquireSession(): SessionInterface
    {
        if ($this->session) {
            return $this->session;
        }

        $url = sprintf('%s://%s:%s%s',
            $this->parsedUrl['scheme'] ?? 'http',
            $this->parsedUrl['host'],
            $this->parsedUrl['port'] ?? self::DEFAULT_PORT,
            $this->parsedUrl['path'] ?? ''
        );

        $requestData = new RequestData(
            $url,
            $this->parsedUrl['user'],
            $this->parsedUrl['pass'],
            false
        );
        $tsx = $this->decorator->discoverTransactionUrl($requestData, $this->injections->database());
        $requestData = $requestData->withEndpoint($tsx);

        $formatter = new HttpCypherFormatter();
        $this->session = new HttpSession(
            new RequestFactory($this->injections->requestFactory(), $this->injections->streamFactory(), $formatter),
            $this->injections->client(),
            $formatter,
            $requestData
        );
        if ($this->injections->hasAutoRouting()) {
            $this->session = new AutoRoutedSession($this->session, $this->injections, $this->parsedUrl);
        }

        return $this->session;
    }
}
