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

namespace Laudis\Neo4j\Common;

use Bolt\error\ConnectException;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\BoltTelemetryApi;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Message\UriInterface;
use Throwable;

final class ExecuteQueryExecutor
{
    private const MAX_ATTEMPTS = 3;

    /**
     * @param array<string, mixed>                      $parameters
     * @param array<string, mixed>|null                 $config
     * @param callable(SessionConfiguration): void|null $onConnectionFailure
     */
    public static function run(
        UriInterface $parsedUrl,
        ConnectionPoolInterface $pool,
        SummarizedResultFormatter $formatter,
        ?Neo4jLogger $logger,
        string $cypher,
        array $parameters = [],
        ?array $config = null,
        ?callable $onConnectionFailure = null,
    ): SummarizedResult {
        $sessionConfig = SessionConfiguration::fromUri($parsedUrl, $logger);
        $config ??= [];

        if (array_key_exists('database', $config) && is_string($config['database'])) {
            $sessionConfig = $sessionConfig->withDatabase($config['database']);
        }

        $accessMode = AccessMode::READ();
        if (array_key_exists('routing', $config) && $config['routing'] === 'w') {
            $accessMode = AccessMode::WRITE();
        }
        $sessionConfig = $sessionConfig->withAccessMode($accessMode);

        $tsxConfig = TransactionConfiguration::default();
        if (array_key_exists('timeout', $config) && $config['timeout'] !== null) {
            $tsxConfig = $tsxConfig->withTimeout((float) $config['timeout']);
        }

        $txMeta = self::parseTxMeta($config['txMeta'] ?? null);
        $connectionAttempt = 0;
        $lastException = null;

        while ($connectionAttempt < self::MAX_ATTEMPTS) {
            /** @var BoltConnection $connection */
            $connection = GeneratorHelper::getReturnFromGenerator($pool->acquire($sessionConfig));
            $messageFactory = new BoltMessageFactory($connection, $logger);
            $transientAttempts = 0;

            try {
                while ($transientAttempts < self::MAX_ATTEMPTS) {
                    try {
                        $connection->sendTelemetryIfNeeded(BoltTelemetryApi::DRIVER_EXECUTE_QUERY);

                        $formattedParameters = ParameterHelper::formatParameters($parameters, $connection->getProtocol())->toArray();
                        $bookmarkHolder = new BookmarkHolder(Bookmark::from([]));

                        $connection->begin(
                            $sessionConfig->getDatabase(),
                            $tsxConfig->getTimeout(),
                            $bookmarkHolder,
                            $txMeta,
                        );

                        $meta = $connection->run(
                            $cypher,
                            $formattedParameters,
                            $sessionConfig->getDatabase(),
                            $tsxConfig->getTimeout(),
                            $bookmarkHolder,
                            $sessionConfig->getAccessMode(),
                            $txMeta,
                        );

                        $result = $formatter->formatBoltResult(
                            $meta,
                            new BoltResult($connection, $sessionConfig->getFetchSize(), $meta['qid'] ?? -1),
                            $connection,
                            microtime(true),
                            0.0,
                            new Statement($cypher, $parameters),
                            $bookmarkHolder,
                        );
                        $result->preload();

                        $messageFactory->createCommitMessage($bookmarkHolder)->send()->getResponse();

                        return $result;
                    } catch (Throwable $e) {
                        $lastException = $e;
                        if ($e instanceof Neo4jException && $e->getClassification() === 'TransientError') {
                            ++$transientAttempts;
                            $connection->reset();

                            continue;
                        }

                        throw $e;
                    }
                }

                throw $lastException ?? new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.General', 'executeQuery failed after transient retries')]);
            } catch (Throwable $e) {
                $lastException = $e;
                ++$connectionAttempt;
                if (!self::isConnectionError($e) || $connectionAttempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                if ($onConnectionFailure !== null) {
                    $onConnectionFailure($sessionConfig);
                }
                $pool->close();
            } finally {
                $pool->release($connection);
            }
        }

        throw $lastException ?? new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.General', 'executeQuery failed after maximum retries')]);
    }

    /**
     * @return iterable<string, scalar|array|null>|null
     */
    private static function parseTxMeta(mixed $txMeta): ?iterable
    {
        if (!is_array($txMeta)) {
            return null;
        }

        $normalized = [];
        foreach ($txMeta as $key => $value) {
            if (!is_string($key) || !self::isSupportedTxMetaValue($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @psalm-assert-if-true bool|float|int|string|array|null $value
     */
    private static function isSupportedTxMetaValue(mixed $value): bool
    {
        return is_scalar($value) || $value === null || is_array($value);
    }

    private static function isConnectionError(Throwable $e): bool
    {
        if ($e instanceof ConnectException) {
            return true;
        }

        if ($e instanceof Neo4jException) {
            if ($e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader' || $e->getTitle() === 'NotALeader') {
                return true;
            }
        }

        $message = strtolower($e instanceof Neo4jException ? ($e->getNeo4jMessage() ?? '') : $e->getMessage());

        return str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection timeout');
    }
}
