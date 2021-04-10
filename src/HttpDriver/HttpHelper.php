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

use Ds\Vector;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\ResponseInterface;

/**
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 */
class HttpHelper
{
    /**
     * @throws JsonException
     *
     * @return CypherResponseSet
     */
    public static function interpretResponse(ResponseInterface $response): array
    {
        $contents = $response->getBody()->getContents();
        if ($response->getStatusCode() >= 400) {
            throw new Neo4jException(new Vector([new Neo4jError((string) $response->getStatusCode(), $contents)]));
        }

        /** @var CypherResponseSet $body */
        $body = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        var_dump($body);

        $errors = new Vector();
        foreach ($body['errors'] as $error) {
            $errors->push(new Neo4jError($error['code'], $error['message']));
        }

        if (!$errors->isEmpty()) {
            throw new Neo4jException($errors);
        }

        return $body;
    }
}
