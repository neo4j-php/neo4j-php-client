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

use Laudis\Neo4j\Bolt\SslConfigurationFactory;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress PossiblyUndefinedArrayOffset
 */
final class SslConfigurationFactoryTest extends TestCase
{
    private SslConfigurationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SslConfigurationFactory();
    }

    public function testDisablePeerVerification(): void
    {
        $config = SslConfiguration::default()
                    ->withVerifyPeer(false)
                    ->withMode(SslMode::ENABLE());

        $uri = Uri::create('');
        $array = $this->factory->create($uri, $config);

        self::assertArrayHasKey('verify_peer', $array[1]);
        self::assertFalse($array[1]['verify_peer']);
        self::assertArrayNotHasKey('allow_self_signed', $array[1]);
        self::assertEquals('s', $array[0]);
    }

    public function testEnablePeerVerification(): void
    {
        $config = SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::ENABLE());

        $uri = Uri::create('');
        $array = $this->factory->create($uri, $config);

        self::assertNotEmpty($array[1]);
        self::assertArrayHasKey('verify_peer', $array[1]);
        self::assertArrayNotHasKey('allow_self_signed', $array[1]);
        self::assertTrue($array[1]['verify_peer']);
        self::assertEquals('s', $array[0]);
    }

    public function testSelfSigned(): void
    {
        $config = SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::ENABLE_WITH_SELF_SIGNED());

        $uri = Uri::create('');
        $array = $this->factory->create($uri, $config);

        self::assertNotEmpty($array[1]);
        self::assertArrayHasKey('allow_self_signed', $array[1]);
        self::assertTrue($array[1]['allow_self_signed']);
        self::assertEquals('ssc', $array[0]);
    }

    public function testFromUriNull(): void
    {
        $config = SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::FROM_URL());

        $uri = Uri::create('neo4j://localhost');
        $array = $this->factory->create($uri, $config);

        self::assertEmpty($array[1]);
        self::assertEquals('', $array[0]);
    }

    public function testFromUri(): void
    {
        $config = SslConfiguration::default()
                    ->withMode(SslMode::FROM_URL());

        $uri = Uri::create('neo4j+s://localhost');
        $array = $this->factory->create($uri, $config);

        self::assertNotEmpty($array[1]);
        self::assertArrayHasKey('verify_peer', $array[1]);
        self::assertArrayNotHasKey('allow_self_signed', $array[1]);
        self::assertTrue($array[1]['verify_peer']);
        self::assertEquals('s', $array[0]);
    }
}
