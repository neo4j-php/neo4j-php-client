<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Bolt\Bolt;
use Exception;
use Psr\Http\Message\RequestInterface;

/**
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
interface AuthenticateInterface
{
    /**
     * @param ParsedUrl $parsedUrl
     *
     * @throws Exception
     */
    public function authenticateHttp(RequestInterface $request, array $parsedUrl): RequestInterface;

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @throws Exception
     */
    public function authenticateBolt(Bolt $bolt, array $parsedUrl, string $userAgent): void;
}
