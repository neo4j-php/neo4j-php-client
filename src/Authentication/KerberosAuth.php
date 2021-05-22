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

namespace Laudis\Neo4j\Authentication;

use Bolt\Bolt;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class KerberosAuth implements AuthenticateInterface
{
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        return $request->withHeader('Authorization', 'Kerberos '.$this->token)
            ->withHeader('User-Agent', $userAgent);
    }

    public function authenticateBolt(Bolt $bolt, UriInterface $uri, string $userAgent): void
    {
        $bolt->setScheme('kerberos');
        $bolt->init($userAgent, $this->token, $this->token);
    }
}
