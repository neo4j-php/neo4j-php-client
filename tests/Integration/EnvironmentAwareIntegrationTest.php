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

namespace Laudis\Neo4j\Tests\Integration;

use Dotenv\Dotenv;
use function explode;
use function is_string;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @template T
 */
abstract class EnvironmentAwareIntegrationTest extends TestCase
{
    /** @var ClientInterface<mixed> */
    protected static ClientInterface $client;

    /**
     * @return ClientInterface<T>
     */
    protected function getClient(): ClientInterface
    {
        return self::$client;
    }

    /**
     * @psalm-suppress InternalMethod
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$client = self::createClient();
    }

    abstract protected static function formatter(): FormatterInterface;

    protected static function createClient(): ClientInterface
    {
        $connections = self::buildConnections();

        $builder = ClientBuilder::create();
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $builder = $builder->withDriver($alias, $connection);
        }

        return $builder->withFormatter(static::formatter())->build();
    }

    /**
     * @return non-empty-array<array-key, array{0: string}>
     */
    public static function connectionAliases(): iterable
    {
        Dotenv::createImmutable(__DIR__.'/../../')->safeLoad();
        $connections = static::getConnections();

        $tbr = [];
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $tbr[$alias] = [$alias];
        }

        /** @var non-empty-array<array-key, array{0: string}> */
        return $tbr;
    }

    /**
     * @return list<string>
     */
    protected static function buildConnections(): array
    {
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            Dotenv::createImmutable(__DIR__.'/../../')->load();
            /** @var string|mixed $connections */
            $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
            if (!is_string($connections)) {
                return ['bolt://neo4j:test@neo4j', 'neo4j://neo4j:test@core1', 'http://neo4j:test@neo4j'];
            }
        }

        return explode(',', $connections);
    }

    /**
     * @return list<string>
     */
    protected static function getConnections(): array
    {
        return self::buildConnections();
    }
}
