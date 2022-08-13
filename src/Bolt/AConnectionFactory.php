<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use Bolt\connection\AConnection;
use Bolt\connection\Socket;
use Bolt\connection\StreamSocket;
use function explode;
use function extension_loaded;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Psr\Http\Message\UriInterface;

class AConnectionFactory
{
    private bool $socketsLoaded;
    private UriInterface $uri;

    public function __construct(UriInterface $uri)
    {
        $this->socketsLoaded = extension_loaded('sockets');
        $this->uri = $uri;
    }

    public function sameEncryptionLevel(string $level, UriInterface $uri, SslConfiguration $config): bool
    {
        return $level === $this->configure($uri, $config)[0];
    }

    /**
     * @param SslConfiguration $config
     *
     * @return array{0: AConnection, 1: ''|'s'|'ssc'}
     */
    public function create(SslConfiguration $config): array
    {
        [$encryptionLevel, $sslConfig] = $this->configure($this->uri, $config);
        $port = $this->uri->getPort() ?? 7687;
        if ($this->socketsLoaded && $sslConfig === null) {
            $connection = new Socket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
        } else {
            $connection = new StreamSocket($this->uri->getHost(), $port, TransactionConfiguration::DEFAULT_TIMEOUT);
            if ($sslConfig !== null) {
                $connection->setSslContextOptions($sslConfig);
            }
        }

        return [$connection, $encryptionLevel];
    }

    /**
     * @return array{0: ''|'s'|'ssc', 1: array|null}
     */
    private function configure(UriInterface $uri, SslConfiguration $config): array
    {
        $mode = $config->getMode();
        $sslConfig = '';
        if ($mode === SslMode::FROM_URL()) {
            $scheme = $uri->getScheme();
            $explosion = explode('+', $scheme, 2);
            $sslConfig = $explosion[1] ?? '';
        } elseif ($mode === SslMode::ENABLE()) {
            $sslConfig = 's';
        } elseif ($mode === SslMode::ENABLE_WITH_SELF_SIGNED()) {
            $sslConfig = 'ssc';
        }

        if (str_starts_with($sslConfig, 's')) {
            return [$sslConfig, $this->enableSsl($uri->getHost(), $sslConfig, $config)];
        }

        return [$sslConfig, null];
    }

    private function enableSsl(string $host, string $sslConfig, SslConfiguration $config): ?array
    {
        $options = [
            'verify_peer' => $config->isVerifyPeer(),
            'peer_name' => $host,
        ];
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $options['SNI_enabled'] = true;
        }
        if ($sslConfig === 's') {
            return $options;
        }

        if ($sslConfig === 'ssc') {
            $options['allow_self_signed'] = true;

            return $options;
        }

        return null;
    }
}
