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
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;

use function sprintf;

use Stringable;

/**
 * @internal
 */
final class OpenIDConnectAuth implements AuthenticateInterface, Stringable
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private readonly string $token
    ) {}

    /**
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticate(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array
    {
        if (method_exists($protocol, 'logon')) {
            $protocol->logon([
                'scheme' => 'bearer',
                'credentials' => $this->token,
            ]);
            /** @var array{server: string, connection_id: string, hints: list} */
            return ResponseHelper::getResponse($protocol)->content;
        } else {
            $protocol->hello([
                'user_agent' => $userAgent,
                'scheme' => 'bearer',
                'credentials' => $this->token,
            ]);

            /** @var array{server: string, connection_id: string, hints: list} */
            return ResponseHelper::getResponse($protocol)->content;
        }
    }

    public function __toString(): string
    {
        return sprintf('OpenId %s', $this->token);
    }
}
