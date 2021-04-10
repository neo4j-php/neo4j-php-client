<?php

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
use function str_starts_with;

/**
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class ConnectionManager
{
    public const DEFAULT_BOLT_PORT = 7687;

    /**
     * @param ParsedUrl $parsedUrl
     * @throws Exception
     */
    public function acquireConnection(array $parsedUrl): StreamSocket
    {
        $host = $parsedUrl['host'] ?? '127.0.0.1';
        $socket = new StreamSocket($host, $parsedUrl['port'] ?? self::DEFAULT_BOLT_PORT);

        $scheme = $parsedUrl['scheme'] ?? 'bolt';
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $host)) {
            $this->enableSsl($host, $sslConfig, $socket);
        }

        return $socket;
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
