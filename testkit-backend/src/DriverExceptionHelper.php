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
use Laudis\Neo4j\Exception\Neo4jException;
use Throwable;

final class DriverExceptionHelper
{
    public static function isRetryableConnectionFailure(Throwable $e): bool
    {
        if ($e instanceof BoltConnectException) {
            return true;
        }

        if ($e instanceof Neo4jException) {
            return self::isConnectionNeo4jException($e);
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'timeout')
            || str_contains($message, 'time out')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'cannot connect')
            || str_contains($message, 'cannot create socket')
            || str_contains($message, 'interrupted system call')
            || str_contains($message, 'i/o error')
            || str_contains($message, 'network read incomplete')
            || str_contains($message, 'network write incomplete')
            || str_contains($message, 'broken')
            || str_contains($message, '-9999');
    }

    public static function isConnectionNeo4jException(Neo4jException $exception): bool
    {
        if ($exception->getClassification() === 'TransientError') {
            return false;
        }

        $code = $exception->getNeo4jCode();
        if ($code === 'Neo.ClientError.General.ConnectionError') {
            return true;
        }

        if (str_contains($code, '-9999')) {
            return true;
        }

        $message = strtolower($exception->getNeo4jMessage() ?? $exception->getMessage());

        return str_contains($message, 'connection')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'cannot connect')
            || str_contains($message, 'cannot create socket')
            || str_contains($message, '-9999')
            || $message === '0';
    }

    public static function shouldRetryManagedTransaction(Neo4jException $exception): bool
    {
        if ($exception->getClassification() === 'TransientError') {
            return true;
        }

        return self::isConnectionNeo4jException($exception);
    }

    public static function toConnectionNeo4jException(Throwable $e): Neo4jException
    {
        if ($e instanceof Neo4jException && self::isConnectionNeo4jException($e)) {
            return new Neo4jException(
                [Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getNeo4jMessage() ?? $e->getMessage())],
                $e
            );
        }

        return new Neo4jException(
            [Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage())],
            $e
        );
    }
}
