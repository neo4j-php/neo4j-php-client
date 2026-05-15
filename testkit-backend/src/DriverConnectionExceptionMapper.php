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

namespace Laudis\Neo4j\TestkitBackend;

use Bolt\error\ConnectException as BoltConnectException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use RuntimeException;
use Throwable;

/**
 * Maps low-level socket / pool failures to Neo4jException so NutKit receives DriverError, not BackendError.
 */
final class DriverConnectionExceptionMapper
{
    public static function isLikelyConnectionFailureMessage(string $message): bool
    {
        return str_contains($message, 'Cannot connect to host')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'Connection reset')
            || str_contains($message, 'Connection timed out')
            || str_contains($message, 'Failed to read');
    }

    /**
     * @throws Throwable when $e is not a connection failure (caller should rethrow or handle)
     */
    public static function wrapConnectionFailureAsNeo4jException(Throwable $e): Neo4jException
    {
        if ($e instanceof Neo4jException) {
            return $e;
        }
        if ($e instanceof TransactionException) {
            throw $e;
        }
        if ($e instanceof BoltConnectException || $e instanceof ConnectionPoolException) {
            return self::toConnectionNeo4jException($e);
        }
        if ($e instanceof RuntimeException && self::isLikelyConnectionFailureMessage($e->getMessage())) {
            return self::toConnectionNeo4jException($e);
        }

        throw $e;
    }

    private static function toConnectionNeo4jException(Throwable $e): Neo4jException
    {
        return new Neo4jException([
            Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage()),
        ], $e);
    }
}
