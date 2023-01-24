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

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\AbstractCypherSequence;

final class TransactionHelper
{
    public const ROLLBACK_CLASSIFICATIONS = ['ClientError', 'TransientError', 'DatabaseError'];

    /**
     * @template U
     * @template T
     *
     * @param callable():UnmanagedTransactionInterface<T> $tsxFactory
     * @param callable(TransactionInterface<T>):U         $tsxHandler
     *
     * @return U
     */
    public static function retry(callable $tsxFactory, callable $tsxHandler)
    {
        while (true) {
            $transaction = null;
            try {
                $transaction = $tsxFactory();
                $tbr = $tsxHandler($transaction);
                self::triggerLazyResult($tbr);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                if ($transaction && !in_array($e->getClassification(), self::ROLLBACK_CLASSIFICATIONS)) {
                    $transaction->rollback();
                }

                if ($e->getClassification() !== 'TransientError') {
                    throw $e;
                }
            }
        }
    }

    private static function triggerLazyResult(mixed $tbr): void
    {
        if ($tbr instanceof AbstractCypherSequence) {
            $tbr->preload();
        }
    }
}
