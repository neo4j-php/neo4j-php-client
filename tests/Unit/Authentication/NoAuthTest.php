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
use Laudis\Neo4j\Authentication\NoAuth;
use PHPUnit\Framework\TestCase;

class NoAuthTest extends TestCase
{
    use TestsAuth;

    public static function provideHttp(): array
    {
        return [
            ['', 'abc', new NoAuth()],
            ['', 'abcd', new NoAuth()],
            ['', 'acerq', new NoAuth()],
        ];
    }

    public static function provideBolt(): array
    {
        return [
            ['abc', new NoAuth(), Auth::none('abc'), Response::SIGNATURE_SUCCESS],
            ['abcd', new NoAuth(), Auth::none('abcd'), Response::SIGNATURE_SUCCESS],
            ['abcd', new NoAuth(), Auth::none('abcd'), Response::SIGNATURE_FAILURE],
            ['abcde', new NoAuth(), Auth::none('abcde'), Response::SIGNATURE_FAILURE],
        ];
    }

    public static function provideToString(): array
    {
        return [
            ['No Auth', new NoAuth()],
        ];
    }
}
