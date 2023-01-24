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
        $records = [...$this->resolver->getAddresses('test.ghlen.com')];

        $this->assertEqualsCanonicalizing(['test.ghlen.com', '123.123.123.123', '123.123.123.124'], $records);
        $this->assertNotEmpty($records);
        $this->assertEquals('test.ghlen.com', $records[0]);
    }

    public function testResolverGoogleDotComReverse(): void
    {
        $records = [...$this->resolver->getAddresses('8.8.8.8')];

        $this->assertNotEmpty($records);
        $this->assertContains('8.8.8.8', $records);
    }

    public function testBogus(): void
    {
        $this->assertEquals(['bogus'], [...$this->resolver->getAddresses('bogus')]);
    }
}
