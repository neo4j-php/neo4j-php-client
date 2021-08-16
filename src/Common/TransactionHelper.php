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

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
use function microtime;
use function preg_match;
use const PREG_OFFSET_CAPTURE;
use Psr\Http\Message\UriInterface;
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

    public static function enableSsl(string $host, string $sslConfig, StreamSocket $sock): void
    {
        $options = [
            'verify_peer' => true,
            'peer_name' => $host,
        ];
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $options['SNI_enabled'] = true;
        }
        if ($sslConfig === 's') {
            $sock->setSslContextOptions($options);
        } elseif ($sslConfig === 'ssc') {
            $options['allow_self_signed'] = true;
            $sock->setSslContextOptions($options);
        }
    }

    /**
     * @return ConnectionInterface<Bolt>
     */
    public static function connectionFromSocket(
        StreamSocket $socket,
        UriInterface $uri,
        string $userAgent,
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): ConnectionInterface {
        $bolt = new Bolt($socket);
        $authenticate->authenticateBolt($bolt, $uri, $userAgent);

        /**
         * @var array{'name': 0, 'version': 1, 'edition': 2}
         * @psalm-suppress all
         */
        $fields = array_flip($bolt->run(<<<'CYPHER'
CALL dbms.components()
YIELD name, versions, edition
UNWIND versions AS version
RETURN name, version, edition
CYPHER)['fields']);

        /** @var array{0: array{0: string, 1: string, 2: string}} $results */
        $results = $bolt->pullAll();

        return new Connection(
            $bolt,
            $results[0][$fields['name']].'-'.$results[0][$fields['edition']].'/'.$results[0][$fields['version']],
            $uri,
            $results[0][$fields['version']],
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase())
        );
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
