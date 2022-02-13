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

use function is_iterable;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Exception\Neo4jException;

final class TransactionHelper
{
    public const ROLLBACK_CLASSIFICATIONS = ['ClientError', 'TransientError', 'DatabaseError'];

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

    /**
     * @param mixed $tbr
     */
    private static function triggerLazyResult($tbr): void
    {
        if (is_iterable($tbr)) {
            /** @var mixed $x */
            foreach ($tbr as $x) {
                self::triggerLazyResult($x);
            }
        }
    }
}
