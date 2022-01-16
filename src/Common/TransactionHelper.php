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
use Laudis\Neo4j\Exception\Neo4jException;

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
    public static function retry(callable $tsxFactory, callable $tsxHandler)
    {
        while (true) {
            try {
                $transaction = $tsxFactory();
                $tbr = $tsxHandler($transaction);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                if (!str_contains($e->getNeo4jCode(), 'Neo.ClientError.Cluster.NotALeader')) {
                    throw $e;
                }
            }
        }
    }
}
