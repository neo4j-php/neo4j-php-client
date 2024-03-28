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

namespace Laudis\Neo4j\Bolt;

use function explode;

use const FILTER_VALIDATE_IP;

use function filter_var;

use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Psr\Http\Message\UriInterface;

class SslConfigurationFactory
{
    /**
     * @return array{0: 's'|'ssc'|'', 1: array{verify_peer?: bool, peer_name?: string, SNI_enabled?: bool, allow_self_signed?: bool}}
     */
    public function create(UriInterface $uri, SslConfiguration $config): array
    {
        $mode = $config->getMode();
        /** @var ''|'s'|'ssc' $sslConfig */
        $sslConfig = '';
        if ($mode === SslMode::FROM_URL()) {
            $scheme = $uri->getScheme();
            $explosion = explode('+', $scheme, 2);
            /** @var ''|'s'|'ssc' $sslConfig */
            $sslConfig = $explosion[1] ?? '';
        } elseif ($mode === SslMode::ENABLE()) {
            $sslConfig = 's';
        } elseif ($mode === SslMode::ENABLE_WITH_SELF_SIGNED()) {
            $sslConfig = 'ssc';
        }

        if (str_starts_with($sslConfig, 's')) {
            return [$sslConfig, $this->enableSsl($uri->getHost(), $sslConfig, $config)];
        }

        return ['', []];
    }

    /**
     * @return array{verify_peer?: bool, peer_name?: string, SNI_enabled?: bool, allow_self_signed?: bool}
     */
    private function enableSsl(string $host, string $sslConfig, SslConfiguration $config): array
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

        return [];
    }
}
