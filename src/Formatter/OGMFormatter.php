<?php


namespace Laudis\Neo4j\Formatter;


use Bolt\Bolt;
use Ds\Vector;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use const JSON_THROW_ON_ERROR;

/**
 * @implements FormatterInterface<Vector<mixed>>
 */
final class OGMFormatter implements FormatterInterface
{
    /**
     * @param array $meta
     * @param iterable $results
     * @param Bolt $bolt
     * @return mixed|void
     */
    public function formatBoltResult(array $meta, iterable $results, Bolt $bolt)
    {
        return $results;
    }

    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        $results = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return new Vector([]);
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW']
        ];
    }
}
