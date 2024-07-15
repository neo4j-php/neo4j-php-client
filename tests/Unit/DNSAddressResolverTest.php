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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Common\DNSAddressResolver;
use PHPUnit\Framework\TestCase;

class DNSAddressResolverTest extends TestCase
{
    private DNSAddressResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DNSAddressResolver();
    }

    public function testResolverGhlenDotCom(): void
    {
        $records = iterator_to_array($this->resolver->getAddresses('www.cloudflare.com'), false);

        $this->assertEqualsCanonicalizing(['www.cloudflare.com', '104.16.123.96', '104.16.124.96'], $records);
        $this->assertNotEmpty($records);
        $this->assertEquals('www.cloudflare.com', $records[0] ?? '');
    }

    public function testResolverGoogleDotComReverse(): void
    {
        $records = iterator_to_array($this->resolver->getAddresses('8.8.8.8'), false);

        $this->assertNotEmpty($records);
        $this->assertContains('8.8.8.8', $records);
    }

    public function testBogus(): void
    {
        $addresses = iterator_to_array($this->resolver->getAddresses('bogus'), false);
        $this->assertEquals(['bogus'], $addresses);
    }
}
