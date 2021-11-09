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

namespace Laudis\Neo4j\Common;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use function microtime;
use function preg_match;
use const PREG_OFFSET_CAPTURE;
use Throwable;

final class TransactionHelper
{
    /**
     * @template U
     * @template T
     *
     * @param callable(TransactionInterface<T>):U         $tsxHandler
     * @param callable():UnmanagedTransactionInterface<T> $tsxFactory
     *
     * @return U
     */
    public static function retry(callable $tsxFactory, callable $tsxHandler, TransactionConfiguration $config)
    {
        $timeout = $config->getTimeout();
        if ($timeout) {
            $limit = microtime(true) + $timeout;
        } else {
            $limit = PHP_FLOAT_MAX;
        }
        while (true) {
            try {
                $transaction = $tsxFactory();
                $tbr = $tsxHandler($transaction);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                if (microtime(true) > $limit || !str_contains($e->getMessage(), '(Neo.ClientError.Cluster.NotALeader)')) {
                    throw $e;
                }
            }
        }
    }

    public static function extractCode(Throwable $throwable): ?string
    {
        $message = $throwable->getMessage();
        $matches = [];
        preg_match('/\(Neo\.([\w]+\.?)+\)/', $message, $matches, PREG_OFFSET_CAPTURE);
        /** @var list<array{0: string, 1: int}> $matches */
        if (isset($matches[0])) {
            $code = $matches[0][0];

            return str_replace(['(', ')'], '', $code);
        }

        return null;
    }
}
