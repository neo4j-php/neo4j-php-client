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

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Authentication\BasicAuth;
use Laudis\Neo4j\Authentication\KerberosAuth;
use Laudis\Neo4j\Authentication\NoAuth;
use Laudis\Neo4j\Authentication\OpenIDConnectAuth;
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class AuthenticateTest extends TestCase
{
    public function testBasic(): void
    {
        $auth = Authenticate::basic('username', 'password');

        $this->assertEquals(new BasicAuth('username', 'password'), $auth);
    }

    public function testKerberos(): void
    {
        $auth = Authenticate::kerberos('token');

        $this->assertEquals(new KerberosAuth('token'), $auth);
    }

    public function testOIDC(): void
    {
        $auth = Authenticate::oidc('oidc');

        $this->assertEquals(new OpenIDConnectAuth('oidc'), $auth);
    }

    public function testDisabled(): void
    {
        $auth = Authenticate::disabled();

        $this->assertEquals(new NoAuth(), $auth);
    }

    /**
     * @dataProvider generateUriCombinations
     */
    public function testFromUrlNoAuth(UriInterface $uri, BasicAuth|NoAuth $expected): void
    {
        $this->assertEquals($expected, Authenticate::fromUrl($uri));
    }

    public static function generateUriCombinations(): array
    {
        return [
            [Uri::create('https://test:teste@localhost'), new BasicAuth('test', 'teste')],
            [Uri::create('bolt://test:teste@localhost'), new BasicAuth('test', 'teste')],
            [Uri::create('bolt+s://test:teste@localhost'), new BasicAuth('test', 'teste')],
            [Uri::create('bolt+ssc://test:teste@localhost'), new BasicAuth('test', 'teste')],
            [Uri::create('wrong://test:teste@localhost'), new BasicAuth('test', 'teste')],
            [Uri::create('wrong://test:@localhost'), new BasicAuth('test', '')],
            [Uri::create('https://localhost'), new NoAuth()],
            [Uri::create('http://localhost'), new NoAuth()],
            [Uri::create('bolt://localhost'), new NoAuth()],
            [Uri::create('bolt+ssc://localhost'), new NoAuth()],
            [Uri::create('bolt+s://localhost'), new NoAuth()],
            [Uri::create('neo4j://localhost'), new NoAuth()],
            [Uri::create('neo4j+ssc://localhost'), new NoAuth()],
            [Uri::create('neo4j+s://localhost'), new NoAuth()],
        ];
    }
}
