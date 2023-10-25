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
use Laudis\Neo4j\Authentication\KerberosAuth;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KerberosTest extends TestCase
{
    use TestsAuth {
        testAuthenticateHttp as disabledTestAuthenticateHttp;
    }

    public function testAuthenticateHttp(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot authenticate http requests with Kerberos, use bolt instead.');

        (new KerberosAuth('abc'))->authenticateHttp(new Request('GET', ''), 'abc');
    }

    public static function provideHttp(): array
    {
        return [];
    }

    public static function provideBolt(): array
    {
        return [
            ['abc', new KerberosAuth('Tabc'), Auth::kerberos('Tabc', 'abc'), Response::SIGNATURE_SUCCESS],
            ['abcd', new KerberosAuth('Tabcd'), Auth::kerberos('Tabcd', 'abcd'), Response::SIGNATURE_SUCCESS],
            ['abcd', new KerberosAuth('Tabcd'), Auth::kerberos('Tabcd', 'abcd'), Response::SIGNATURE_FAILURE],
            ['abcde', new KerberosAuth('Tabcde'), Auth::kerberos('Tabcde', 'abcde'), Response::SIGNATURE_FAILURE],
        ];
    }

    public static function provideToString(): array
    {
        return [
            ['Kerberos abc', new KerberosAuth('abc')],
            ['Kerberos abcd', new KerberosAuth('abcd')],
        ];
    }
}
