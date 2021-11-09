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
    /** @var ClientInterface<T> */
    protected ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createClient();
    }

    /**
     * @return FormatterInterface<T>
     */
    abstract protected function formatter(): FormatterInterface;

    /**
     * @return ClientInterface<T>
     */
    protected function createClient(): ClientInterface
    {
        $connections = $this->getConnections();

        $builder = ClientBuilder::create();
        $aliases = [];
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $aliases[] = $alias;
            $builder = $builder->withDriver($alias, $connection);
        }

        $client = $builder->withFormatter($this->formatter())->build();

        foreach ($aliases as $alias) {
            $client->run('MATCH (x) DETACH DELETE x', [], $alias);
        }

        return $client;
    }

    /**
     * @return non-empty-array<array-key, array{0: string}>
     */
    public function connectionAliases(): iterable
    {
        Dotenv::createImmutable(__DIR__.'/../../')->safeLoad();
        $connections = $this->getConnections();

        $tbr = [];
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $tbr[] = [$uri->getScheme().'_'.$i];
        }

        /** @var non-empty-array<array-key, array{0: string}> */
        return $tbr;
    }

    /**
     * @return list<string>
     */
    protected function getConnections(): array
    {
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            return [];
        }

        return explode(',', $connections);
    }
}
