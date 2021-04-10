<?php

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

final class NoAuth implements AuthenticateInterface
{
    public function authenticateHttp(RequestInterface $request, array $parsedUrl): RequestInterface
    {
        return $request;
    }

    public function authenticateBolt(Bolt $bolt, array $parsedUrl, string $userAgent): void
    {
        $bolt->setScheme('none');
        $bolt->init($userAgent, '', '');
    }
}
