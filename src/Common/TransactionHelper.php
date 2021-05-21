<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Common;


use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use function microtime;

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
                if (microtime(true) > $limit) {
                    throw $e;
                }
            }
        }
    }
}
