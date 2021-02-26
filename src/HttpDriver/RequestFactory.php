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

namespace Laudis\Neo4j\HttpDriver;

use function array_merge_recursive;
use JsonException;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

final class RequestFactory
{
    private RequestFactoryInterface $factory;
    private StreamFactoryInterface $streamFactory;
    private FormatterInterface $formatter;
    private string $userAgent;

    public function __construct(RequestFactoryInterface $factory, StreamFactoryInterface $streamFactory, FormatterInterface $formatter, string $userAgent)
    {
        $this->factory = $factory;
        $this->streamFactory = $streamFactory;
        $this->formatter = $formatter;
        $this->userAgent = $userAgent;
    }

    public function openTransaction(RequestData $data): RequestInterface
    {
        return $this->createRequest($data, 'POST');
    }

    public function createRequest(RequestData $data, string $method, string $body = ''): RequestInterface
    {
        $combo = base64_encode($data->getUser().':'.$data->getPassword());

        $tbr = $this->factory->createRequest($method, $data->getEndpoint())
            ->withBody($this->streamFactory->createStream($body))
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Authorization', 'Basic '.$combo);

        return $this->formatter->decorateRequest($tbr);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public function post(RequestData $data, iterable $statements): RequestInterface
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

        $body = json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);

        return $this->createRequest($data, 'POST', $body);
    }

    public function delete(RequestData $data): RequestInterface
    {
        return $this->createRequest($data, 'DELETE');
    }
}
