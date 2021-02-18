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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\HttpDriver\RequestFactory;
use Laudis\Neo4j\Network\AutoRoutedSession;
use Laudis\Neo4j\Network\VersionDiscovery;
use Psr\Http\Client\ClientExceptionInterface;

final class HttpDriver implements DriverInterface
{
    /** @var array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} */
    private array $parsedUrl;
    private ?SessionInterface $session = null;
    public const DEFAULT_PORT = '7474';
    private HttpInjections $injections;

    /**
     * HttpConnection constructor.
     *
     * @param array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string} $parsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, HttpInjections $injector)
    {
        $this->parsedUrl = $parsedUrl;
        $this->injections = $injector;
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function aquireSession(FormatterInterface $formatter): SessionInterface
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
            $this->parsedUrl['pass']
        );
        $tsx = (new VersionDiscovery(
            new RequestFactory(
                $this->injections->requestFactory(),
                $this->injections->streamFactory(),
                new BasicFormatter()
            ),
            $this->injections->client())
        )->discoverTransactionUrl($requestData, $this->injections->database());
        $requestData = $requestData->withEndpoint($tsx);
        $streamFactory = $this->injections->streamFactory();

        if ($this->injections->hasAutoRouting()) {
            $basicFormatter = new BasicFormatter();
            $requestFactory = new RequestFactory($this->injections->requestFactory(), $streamFactory, $basicFormatter);
            $basicSession = new HttpSession($requestFactory, $this->injections->client(), $basicFormatter, $requestData);
            $this->session = new AutoRoutedSession($formatter, $basicSession, $this->injections, $this->parsedUrl);
        } else {
            $this->session = new HttpSession(
                new RequestFactory($this->injections->requestFactory(), $streamFactory, $formatter),
                $this->injections->client(),
                $formatter,
                $requestData
            );
        }

        return $this->session;
    }
}
