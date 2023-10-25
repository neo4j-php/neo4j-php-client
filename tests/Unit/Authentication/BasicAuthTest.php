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
use Laudis\Neo4j\Authentication\BasicAuth;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use PHPUnit\Framework\TestCase;

class BasicAuthTest extends TestCase
{
    use TestsAuth;

    public static function provideHttp(): array
    {
        return [
            ['Basic test:test', 'abc', new BasicAuth('test', 'test')],
            ['Basic test1:test2', 'abcd', new BasicAuth('test1', 'test2')],
            ['Basic test:', 'acerq', new BasicAuth('test', '')],
        ];
    }

    public static function provideBolt(): array
    {
        return [
            self::createBasics('abc', 'test', 'test', Response::SIGNATURE_SUCCESS),
            self::createBasics('abcd', 'test1', 'test2', Response::SIGNATURE_SUCCESS),
            self::createBasics('abcd', 'test1', 'test2', Response::SIGNATURE_FAILURE),
            self::createBasics('abcd', 'test1', '', Response::SIGNATURE_FAILURE),
        ];
    }

    /**
     * @return array{0: string, 1: AuthenticateInterface, 2: array, 3: int}
     */
    private static function createBasics(string $userAgent, string $user, string $pass, int $code): array
    {
        return [$userAgent, new BasicAuth($user, $pass), Auth::basic($user, $pass, $userAgent), $code, $response];
    }

    public static function provideToString(): array
    {
        return [
            ['Basic test:######', new BasicAuth('test', 'test')],
            ['Basic test:######', new BasicAuth('test', 'test2')],
            ['Basic test1:######', new BasicAuth('test1', 'test2')],
            ['Basic test1:######', new BasicAuth('test1', '')],
        ];
    }
}
