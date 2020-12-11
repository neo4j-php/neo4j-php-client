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

use BadMethodCallException;
use InvalidArgumentException;
use Laudis\Neo4j\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    public function testEmpty(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with an empty connectionpool');
        ClientBuilder::create()->build();
    }

    public function testBadDefault(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with a default connection "error" that is not in the connection pool');

        ClientBuilder::create()
            ->addHttpConnection('temp', 'http://neoj:test@localhost')
            ->setDefaultConnection('error')
            ->build();
    }

    public function testBadHttpUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided url must have a parsed host, user and pass value');

        ClientBuilder::create()
            ->addHttpConnection('temp', 'neoj:test');
    }

    public function testBadBoltUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided url must have a parsed host, user and pass value');

        ClientBuilder::create()
            ->addBoltConnection('temp', 'neoj:test');
    }
}
