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

use function base64_encode;
use Bolt\Bolt;
use Exception;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;

final class BasicAuth implements AuthenticateInterface
{
    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function authenticateHttp(RequestInterface $request, array $parsedUrl): RequestInterface
    {
        $combo = base64_encode($this->username.':'.$this->password);

        return $request->withHeader('Authorization', 'Basic '.$combo);
    }

    /**
     * @throws Exception
     */
    public function authenticateBolt(Bolt $bolt, array $parsedUrl, string $userAgent): void
    {
        $bolt->setScheme('basic');
        $bolt->init($userAgent, $this->username, $this->password);
    }
}
