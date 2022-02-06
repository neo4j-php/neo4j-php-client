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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Bolt\SslConfigurator;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress PossiblyUndefinedStringArrayOffset
 */
final class SslConfiguratorTest extends TestCase
{
    private SslConfigurator $configurator;

    public function setUp(): void
    {
        $this->configurator = new SslConfigurator();
    }

    public function testDisablePeerVerification(): void
    {
        $config = DriverConfiguration::default()
            ->withSslConfiguration(
                SslConfiguration::default()
                    ->withVerifyPeer(false)
                    ->withMode(SslMode::ENABLE())
            );

        $uri = Uri::create('');
        $array = $this->configurator->configure($uri, $config);

        self::assertNotNull($array);
        self::assertArrayHasKey('verify_peer', $array);
        self::assertArrayNotHasKey('allow_self_signed', $array);
        self::assertFalse($array['verify_peer']);
    }

    public function testEnablePeerVerification(): void
    {
        $config = DriverConfiguration::default()
            ->withSslConfiguration(
                SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::ENABLE())
            );

        $uri = Uri::create('');
        $array = $this->configurator->configure($uri, $config);

        self::assertNotNull($array);
        self::assertArrayHasKey('verify_peer', $array);
        self::assertArrayNotHasKey('allow_self_signed', $array);
        self::assertTrue($array['verify_peer']);
    }

    public function testSelfSigned(): void
    {
        $config = DriverConfiguration::default()
            ->withSslConfiguration(
                SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::ENABLE_WITH_SELF_SIGNED())
            );

        $uri = Uri::create('');
        $array = $this->configurator->configure($uri, $config);

        self::assertNotNull($array);
        self::assertArrayHasKey('allow_self_signed', $array);
        self::assertTrue($array['allow_self_signed']);
    }

    public function testFromUriNull(): void
    {
        $config = DriverConfiguration::default()
            ->withSslConfiguration(
                SslConfiguration::default()
                    ->withVerifyPeer(true)
                    ->withMode(SslMode::FROM_URL())
            );

        $uri = Uri::create('neo4j://localhost');
        $array = $this->configurator->configure($uri, $config);

        self::assertNull($array);
    }

    public function testFromUri(): void
    {
        $config = DriverConfiguration::default()
            ->withSslConfiguration(
                SslConfiguration::default()
                    ->withMode(SslMode::FROM_URL())
            );

        $uri = Uri::create('neo4j+s://localhost');
        $array = $this->configurator->configure($uri, $config);

        self::assertNotNull($array);
        self::assertArrayHasKey('verify_peer', $array);
        self::assertArrayNotHasKey('allow_self_signed', $array);
        self::assertTrue($array['verify_peer']);
    }
}
