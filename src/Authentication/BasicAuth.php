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

use Bolt\helpers\Auth;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Exception;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\RequestInterface;
use Stringable;

/**
 * Authenticates connections using a basic username and password.
 *
 * @internal
 */
final class BasicAuth implements AuthenticateInterface, Stringable
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private string $username,
        private string $password
    ) {}

    /**
     * @throws Exception
     */
    public function authenticate(V4_4|V5|V5_1|V5_2|V5_3 $bolt, string $userAgent): array
    {
        $response = $bolt->hello(Auth::basic($this->username, $this->password, $userAgent));
        if ($response->getSignature() === Response::SIGNATURE_FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }

        /** @var array{server: string, connection_id: string, hints: list} */
        return $response->getContent();
    }

    public function __toString(): string
    {
        return sprintf('Basic %s:%s', $this->username, '######');
    }
}
