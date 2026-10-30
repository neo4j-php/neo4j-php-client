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

use Bolt\connection\IConnection;
use Bolt\protocol\V5;
use Bolt\protocol\V6_1;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class BoltDriverCompatibilityTest extends TestCase
{
    public function testV5ProtocolUsesTheInstalledConstructor(): void
    {
        $protocol = $this->createBoltV5($this->createMock(IConnection::class));

        self::assertSame('5', $protocol->getVersion());
    }

    public function testSupportedBolt5ProtocolStaysOnPackStream1(): void
    {
        $protocol = $this->createBoltV5($this->createMock(IConnection::class));
        self::assertSame('5', $protocol->getVersion());

        if (!method_exists(V5::class, 'getPackStreamVersion')) {
            $parameters = (new ReflectionMethod(V5::class, '__construct'))->getParameters();
            self::assertSame('int', $this->parameterTypeName($parameters[0] ?? null));

            return;
        }

        $method = new ReflectionMethod(V5::class, 'getPackStreamVersion');
        $method->setAccessible(true);

        self::assertSame('1', $method->invoke($protocol));
    }

    public function testBolt61IsCoupledToPackStream11WhenTheDriverProvidesIt(): void
    {
        if (!class_exists(V6_1::class) || !method_exists(V6_1::class, 'getPackStreamVersion')) {
            $parameters = (new ReflectionMethod(V5::class, '__construct'))->getParameters();
            self::assertGreaterThanOrEqual(2, count($parameters));
            self::assertSame('int', $this->parameterTypeName($parameters[0] ?? null));

            return;
        }

        $protocol = $this->createProtocol(V6_1::class, $this->createMock(IConnection::class));
        $method = new ReflectionMethod(V6_1::class, 'getPackStreamVersion');
        $method->setAccessible(true);

        self::assertSame('6.1', $protocol->getVersion());
        self::assertSame('1_1', $method->invoke($protocol));
    }

    private function parameterTypeName(?ReflectionParameter $parameter): string
    {
        $type = $parameter?->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : '';
    }

    private function createBoltV5(IConnection $connection): V5
    {
        $protocol = $this->createProtocol(V5::class, $connection);
        if (!$protocol instanceof V5) {
            throw new RuntimeException('Bolt V5 protocol could not be constructed');
        }

        return $protocol;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $protocolClass
     *
     * @return T
     */
    private function createProtocol(string $protocolClass, IConnection $connection): object
    {
        $arguments = [];
        foreach ((new ReflectionMethod($protocolClass, '__construct'))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'int') {
                $arguments[] = 1;
                continue;
            }

            $arguments[] = $connection;
        }

        $protocol = (new ReflectionClass($protocolClass))->newInstanceArgs($arguments);
        self::assertInstanceOf($protocolClass, $protocol);

        return $protocol;
    }
}
