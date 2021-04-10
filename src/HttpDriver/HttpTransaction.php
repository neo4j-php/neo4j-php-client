<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\HttpDriver;

use function array_merge_recursive;
use Ds\Vector;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Network\Http\HttpDriver;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * @template T
 *
 * @implements TransactionInterface<T>
 */
final class HttpTransaction implements TransactionInterface
{
    private RequestInterface $request;
    private FormatterInterface $formatter;
    private HttpDriver $driver;
    private StreamFactoryInterface $factory;
    private SessionConfiguration $sessionConfig;
    private TransactionConfig $tsxConfig;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(RequestInterface $request, FormatterInterface $formatter, HttpDriver $driver, StreamFactoryInterface $factory, SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig)
    {
        $this->request = $request;
        $this->formatter = $formatter;
        $this->driver = $driver;
        $this->factory = $factory;
        $this->sessionConfig = $sessionConfig;
        $this->tsxConfig = $tsxConfig;
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function run(string $statement, iterable $parameters = [])
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function runStatement(Statement $statement)
    {
        return $this->runStatements([$statement])->first();
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function runStatements(iterable $statements): Vector
    {
        $request = $this->request->withMethod('POST');

        $body = $this->statementsToString($statements);

        $request = $request->withBody($this->factory->createStream($body));
        $response = $this->driver->acquireConnection($this->sessionConfig, $this->tsxConfig)->sendRequest($request);
        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function commit(iterable $statements = []): Vector
    {
        $uri = $this->request->getUri();
        $request = $this->request->withUri($uri->withPath($uri->getPath().'/commit'))->withMethod('POST');
        $request = $request->withBody($this->factory->createStream($this->statementsToString($statements)));

        $response = $this->driver->acquireConnection($this->sessionConfig, $this->tsxConfig)
            ->sendRequest($request);

        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function rollback(): void
    {
        $request = $this->request->withMethod('DELETE');
        $response = $this->driver->acquireConnection($this->sessionConfig, $this->tsxConfig)
            ->sendRequest($request);

        HttpHelper::interpretResponse($response);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    private function statementsToString(iterable $statements): string
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $st = [
                'statement' => $statement->getText(),
                'resultDataContents' => [],
                'includeStats' => false,
            ];
            $st = array_merge_recursive($st, $this->formatter->statementConfigOverride());
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            $st['parameters'] = $parameters->count() === 0 ? new stdClass() : $parameters->toArray();
            $tbr[] = $st;
        }

        return json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);
    }
}
