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
use RuntimeException;
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
     */
    public static function interpretResponse(ResponseInterface $response): stdClass
    {
        $contents = $response->getBody()->getContents();

        /** @var stdClass $body */
        $body = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

        $errors = [];
        /** @var list<stdClass> $bodyErrors */
        $bodyErrors = $body->errors ?? [];
        foreach ($bodyErrors as $error) {
            /** @var string */
            $code = $error->code;
            /** @var string */
            $message = $error->message;
            $errors[] = Neo4jError::fromMessageAndCode($code, $message);
        }

        if (count($errors) !== 0) {
            throw new Neo4jException($errors);
        }

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('HTTP Error: '.$response->getReasonPhrase());
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
