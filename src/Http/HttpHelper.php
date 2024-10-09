<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Http;

use function array_key_first;
use function array_merge;
use function count;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

use JsonException;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

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
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public static function interpretResponse(ResponseInterface $response): stdClass
    {
        if ($response->getStatusCode() >= 500) {
            throw new RuntimeException('HTTP Error: '.$response->getReasonPhrase());
        }

        $contents = $response->getBody()->getContents();

        /** @var stdClass $body */
        // Jolt is a Json sequence (rfc 7464), so it starts with a RS control character "\036"
        if ($contents[0] === "\036") {
            $body = self::getJoltBody($contents);
        } else {
            // If not Jolt, assume it is Json
            $body = self::getJsonBody($contents);
        }

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

        return $body;
    }

    /**
     * @throws JsonException
     */
    public static function getJsonBody(string $contents): stdClass
    {
        /** @var stdClass */
        return json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Converts a Jolt input (with JSON sequence separators) into a stdClass that contains the data of all jsons of the sequence.
     *
     * @throws JsonException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     *
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress MixedPropertyFetch
     * @psalm-suppress MixedArgument
     */
    public static function getJoltBody(string $contents): stdClass
    {
        // Split json sequence in single jsons, split on json sequence separators.
        $contents = explode("\036", $contents);

        // Drop first (empty) string.
        array_shift($contents);

        // stdClass to capture all the jsons
        $rtr = new stdClass();
        $rtr->results = [];

        // stdClass to capture the jsons of the results of a single statement that has been sent.
        $data = new stdClass();
        $data->data = [];

        foreach ($contents as $content) {
            $content = self::getJsonBody($content);
            [$key, $value] = self::splitJoltSingleton($content);

            switch ($key) {
                case 'header':
                    if (isset($data->header)) {
                        throw new UnexpectedValueException('Jolt response with second header before summary received');
                    }
                    $data->header = $value;
                    break;
                case 'data':
                    if (!isset($data->header)) {
                        throw new UnexpectedValueException('Jolt response with data before new header received');
                    }
                    $data->data[] = $value;
                    break;
                case 'summary':
                    if (!isset($data->header)) {
                        throw new UnexpectedValueException('Jolt response with summary before new header received');
                    }
                    $data->summary = $value;
                    $rtr->results[] = $data;
                    $data = new stdClass();
                    $data->data = [];
                    break;

                case 'info':
                    if (isset($rtr->info)) {
                        throw new UnexpectedValueException('Jolt response with multiple info rows received');
                    }
                    $rtr->info = $value;
                    break;
                case 'error':
                    if (isset($rtr->errors)) {
                        throw new UnexpectedValueException('Jolt response with multiple error rows received');
                    }
                    $rtr->errors = [];
                    foreach ($value->errors as $error) {
                        $rtr->errors[] = (object) [
                            'code' => self::splitJoltSingleton($error->code)[1],
                            'message' => self::splitJoltSingleton($error->message)[1],
                        ];
                    }
                    break;
                default:
                    throw new UnexpectedValueException('Jolt response with unknown key received: '.$key);
            }
        }

        return $rtr;
    }

    /**
     * @pure
     *
     * @return array{0: string, 1: mixed}
     */
    public static function splitJoltSingleton(stdClass $joltSingleton): array
    {
        /** @var array<string, mixed> $joltSingleton */
        $joltSingleton = (array) $joltSingleton;

        if (count($joltSingleton) !== 1) {
            throw new UnexpectedValueException('stdClass with '.count($joltSingleton).' elements is not a Jolt singleton.');
        }

        $key = array_key_first($joltSingleton);

        return [$key, $joltSingleton[$key]];
    }

    /**
     * Prepares the statements to json.
     *
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public static function statementsToJson(ConnectionInterface $connection, FormatterInterface $formatter, iterable $statements): string
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $st = [
                'statement' => $statement->getText(),
                'resultDataContents' => [],
                'includeStats' => false,
            ];
            $st = array_merge($st, $formatter->statementConfigOverride($connection));
            $parameters = ParameterHelper::formatParameters($statement->getParameters(), $connection->getProtocol());
            $st['parameters'] = $parameters->count() === 0 ? new stdClass() : $parameters->toArray();
            $tbr[] = $st;
        }

        return json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);
    }
}
