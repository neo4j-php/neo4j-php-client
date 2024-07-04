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

namespace Laudis\Neo4j\Tests\Unit\Authentication;

use Bolt\helpers\Auth;
use Bolt\protocol\Response;
use Laudis\Neo4j\Authentication\OpenIDConnectAuth;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OIDCTest extends TestCase
{
    use TestsAuth {
        testAuthenticateHttp as disabledTestAuthenticateHttp;
    }

    public function testAuthenticateHttp(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot authenticate http requests with OpenID Connect, use bolt instead.');

        (new OpenIDConnectAuth('abc'))->authenticateHttp(new Request('GET', ''), 'abc');
    }

    public static function provideHttp(): array
    {
        return [];
    }

    public static function provideBolt(): array
    {
        return [
            ['abc', new OpenIDConnectAuth('Tabc'), Auth::bearer('Tabc', 'abc'), Response::SIGNATURE_SUCCESS],
            ['abcd', new OpenIDConnectAuth('Tabcd'), Auth::bearer('Tabcd', 'abcd'), Response::SIGNATURE_SUCCESS],
            ['abcd', new OpenIDConnectAuth('Tabcd'), Auth::bearer('Tabcd', 'abcd'), Response::SIGNATURE_FAILURE],
            ['abcde', new OpenIDConnectAuth('Tabcde'), Auth::bearer('Tabcde', 'abcde'), Response::SIGNATURE_FAILURE],
        ];
    }

    public static function provideToString(): array
    {
        return [
            ['OpenId abc', new OpenIDConnectAuth('abc')],
            ['OpenId abcd', new OpenIDConnectAuth('abcd')],
        ];
    }
}
