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

use Ds\Vector;
use JsonException;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\HttpCypherFormatter;
use Laudis\Neo4j\HttpDriver\RequestFactory;
use Laudis\Neo4j\HttpDriver\Transaction;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Formatter\HttpCypherFormatter
 */
final class HttpSession implements SessionInterface
{
    private ClientInterface $client;
    private HttpCypherFormatter $formatter;
    private RequestFactory $factory;
    private RequestData $data;

    public function __construct(RequestFactory $factory, ClientInterface $client, HttpCypherFormatter $formatter, RequestData $data)
    {
        $this->factory = $factory;
        $this->client = $client;
        $this->formatter = $formatter;
        $this->data = $data;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function run(iterable $statements): Vector
    {
        $request = $this->factory->post($this->data, $statements);
        $uri = $request->getUri();
        $request = $request->withUri($uri->withPath($uri->getPath().'/commit'));
        $response = $this->client->sendRequest($request);
        $data = $this->interpretResponse($response);

        return $this->formatter->formatResponse($data);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function openTransaction(iterable $statements = null): TransactionInterface
    {
        $request = $this->factory->openTransaction($this->data, $statements ?? []);
        $response = $this->client->sendRequest($request);
        /** @var array{commit: string} $data */
        $data = $this->interpretResponse($response);

        return new Transaction($this, preg_replace('/\/commit/u', '', $data['commit']));
    }

    /**
     * @throws JsonException
     * @throws Neo4jException
     * @throws ClientExceptionInterface
     */
    public function commitTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        $commit = $transaction->getDomainIdentifier().'/commit';
        $request = $this->factory->post($this->data->withEndpoint($commit), $statements);
        $response = $this->client->sendRequest($request);
        $data = $this->interpretResponse($response);

        return $this->formatter->formatResponse($data);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Neo4jException
     * @throws JsonException
     */
    public function rollbackTransaction(TransactionInterface $transaction): void
    {
        $request = $this->factory->delete($this->data->withEndpoint($transaction->getDomainIdentifier()));
        $response = $this->client->sendRequest($request);
        $this->interpretResponse($response);
    }

    /**
     * @throws Neo4jException
     * @throws JsonException
     *
     * @return CypherResponseSet
     */
    private function interpretResponse(ResponseInterface $response): array
    {
        $contents = $response->getBody()->getContents();
        if ($response->getStatusCode() >= 400) {
            throw new Neo4jException(new Vector([new Neo4jError((string) $response->getStatusCode(), $contents)]));
        }
        /** @var CypherResponseSet $body */
        $body = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $errors = $this->formatter->filterError($body);
        if (!$errors->isEmpty()) {
            throw new Neo4jException($errors);
        }

        return $body;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function runOverTransaction(TransactionInterface $transaction, iterable $statements): Vector
    {
        $data = $this->data->withEndpoint($transaction->getDomainIdentifier());
        $request = $this->factory->post($data, $statements);
        $response = $this->client->sendRequest($request);
        $data = $this->interpretResponse($response);

        return $this->formatter->formatResponse($data);
    }
}
