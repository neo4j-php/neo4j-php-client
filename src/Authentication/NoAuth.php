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

namespace Laudis\Neo4j\Authentication;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Stringable;

/**
 * Doesn't authenticate connections.
 *
 * @internal
 */
final class NoAuth implements AuthenticateInterface, Stringable
{
    public function authenticate(V4_4|V5|V5_1|V5_2|V5_3 $bolt, string $userAgent): array
    {
        if (method_exists($protocol, 'logon')) {
            $protocol->hello(['user_agent' => $userAgent]);
            $response = ResponseHelper::getResponse($protocol);
            $protocol->logon([
                'scheme' => 'none',
            ]);
            ResponseHelper::getResponse($protocol);

            /** @var array{server: string, connection_id: string, hints: list} */
            return $response->content;
        } else {
            $protocol->hello([
                'user_agent' => $userAgent,
                'scheme' => 'none',
            ]);

            /** @var array{server: string, connection_id: string, hints: list} */
            return ResponseHelper::getResponse($protocol)->content;
        }
    }

    public function __toString(): string
    {
        return 'No Auth';
    }
}
