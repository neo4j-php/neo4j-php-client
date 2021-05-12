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

namespace Laudis\Neo4j;

use Bolt\connection\StreamSocket;
use Exception;
use function explode;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Client\ClientInterface;
use function str_starts_with;

/**
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class ConnectionManager
{
    private HttpPsrBindings $bindings;

    public function __construct(HttpPsrBindings $bindings)
    {
        $this->bindings = $bindings;
    }

    /**
     * @return ParsedUrl
     */
    public static function parseUrl(string $url): array
    {
        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'] ?? '', $query);
        /** @var array<string, string> */
        $query['database'] ??= 'neo4j';

        /** @var array<string, string> $query */
        $scheme = $parsedUrl['scheme'] ?? 'bolt';
        return [
            'scheme' => $scheme,
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'path' => $parsedUrl['path'] ?? '',
            'port' => $parsedUrl['port'] ?? ($scheme === 'http' ? 7474 : 7687),
            'query' => $query,
            'user' => $parsedUrl['user'] ?? null,
            'pass' => $parsedUrl['pass'] ?? null,
        ];
    }

    /**
     * @template U
     * @template T
     *
     * @param callable(TransactionInterface<T>):U         $tsxHandler
     * @param callable():UnmanagedTransactionInterface<T> $tsxFactory
     *
     * @return U
     */
    public static function retry(callable $tsxFactory, callable $tsxHandler, StaticTransactionConfiguration $config)
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

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @throws Exception
     */
    public function acquireBoltConnection(array $parsedUrl): StreamSocket
    {
        $host = $parsedUrl['host'];
        $socket = new StreamSocket($host, $parsedUrl['port']);

        $scheme = $parsedUrl['scheme'];
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $host)) {
            $this->enableSsl($host, $sslConfig, $socket);
        }

        return $socket;
    }

    public function acquireHttpConnection(): ClientInterface
    {
        return $this->bindings->getClient();
    }

    private function enableSsl(string $host, string $sslConfig, StreamSocket $sock): void
    {
        // Pass a standard option to enable ssl as there is no direct flag
        // and \Bolt\Bolt only turns on ssl if an option is passed.
        // TODO: make a pr for this
        $options = ['verify_peer' => true];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $options['SNI_enabled'] = true;
        }
        if ($sslConfig === 's') {
            $sock->setSslContextOptions($options);
        } elseif ($sslConfig === 'ssc') {
            $options['allow_self_signed'] = true;
            $sock->setSslContextOptions($options);
        }
    }
}
