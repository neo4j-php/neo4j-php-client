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

use function array_merge;
use function count;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Helper functions for the http protocol.
 *
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class HttpHelper
{
    /**
     * Checks the response and interprets it. Throws if an error is detected.
     *
     * @throws JsonException
     *
     * @return CypherResponseSet
     */
    public static function interpretResponse(ResponseInterface $response): array
    {
        $contents = $response->getBody()->getContents();
        if ($response->getStatusCode() >= 400) {
            throw new Neo4jException([new Neo4jError((string) $response->getStatusCode(), $contents)]);
        }

        /** @var CypherResponseSet $body */
        $body = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $errors = [];
        foreach (($body['errors'] ?? []) as $error) {
            $errors[] = new Neo4jError($error['code'], $error['message']);
        }

        if (count($errors) !== 0) {
            throw new Neo4jException($errors);
        }

        return $body;
    }

    /**
     * Prepares the statements to json.
     *
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public static function statementsToJson(FormatterInterface $formatter, iterable $statements): string
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $st = [
                'statement' => $statement->getText(),
                'resultDataContents' => [],
                'includeStats' => false,
            ];
            $st = array_merge($st, $formatter->statementConfigOverride());
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            $st['parameters'] = $parameters->count() === 0 ? new stdClass() : $parameters->toArray();
            $tbr[] = $st;
        }

        return json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);
    }
}
