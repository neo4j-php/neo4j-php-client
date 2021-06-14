<?php
/** @noinspection PhpUndefinedMethodInspection */
declare(strict_types=1);


namespace Laudis\Neo4j\Tests\Helpers;


use Laudis\Neo4j\Bolt\BoltDriver;
use PHPUnit\Framework\TestCase;

final class TestHelper
{
    /**
     * @param class-string<TestCase> $class
     *
     * @throws \Exception
     */
    public static function skipIfUnsupportedVersion(string $alias, string $class): void
    {
        /** @var string $version */
        $version = BoltDriver::create('bolt://neo4j:test@neo4j')
            ->createSession()
            ->run('CALL dbms.components() yield versions UNWIND versions as version return version')
            ->first()
            ->get('version');

        if ($alias === 'cluster' && str_starts_with($version, '3')) {
            $class::markTestSkipped('Cannot test cluster operations on version 3.5');
        }
    }
}
