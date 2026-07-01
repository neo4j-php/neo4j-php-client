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

use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;

/**
 * Maps driver exceptions to TestKit errorType strings, aligned with other drivers.
 */
final class DriverErrorTypeMapper
{
    public static function map(Neo4jException|TransactionException $exception): string
    {
        if ($exception instanceof TransactionException) {
            return TransactionException::class;
        }

        if ($exception->getNeo4jCode() === 'Neo.ClientError.General.ConnectionError') {
            return 'Laudis\\Neo4j\\Exception\\ServiceUnavailableException';
        }

        return match ($exception->getClassification()) {
            'TransientError' => 'Laudis\\Neo4j\\Exception\\TransientException',
            'ClientError' => 'Laudis\\Neo4j\\Exception\\ClientException',
            'DatabaseError' => 'Laudis\\Neo4j\\Exception\\DatabaseException',
            default => Neo4jException::class,
        };
    }

    public static function isRetryable(Neo4jException|TransactionException $exception): ?bool
    {
        if (!$exception instanceof Neo4jException) {
            return null;
        }

        return $exception->getClassification() === 'TransientError';
    }
}
