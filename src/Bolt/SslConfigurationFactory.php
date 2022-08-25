<?php

declare(strict_types=1);

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Psr\Http\Message\UriInterface;

use function explode;
use function filter_var;

use const FILTER_VALIDATE_IP;

class SslConfigurationFactory
{
    /**
     * @param UriInterface $uri
     * @param SslConfiguration $config
     *
     * @return array{0: 's'|'ssc'|'s', 1: array}
     */
    public function create(UriInterface $uri, SslConfiguration $config): array
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
