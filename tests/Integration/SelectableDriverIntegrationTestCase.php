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

use Exception;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;

abstract class SelectableDriverIntegrationTestCase extends EnvironmentAwareIntegrationTest
{
    /** @var array<string,ClientInterface> */
    protected static array $drivers = [];

    abstract protected static function formatter(): FormatterInterface;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$client = self::createClient();
    }

    protected static function createClient(): ClientInterface
    {
        $connections = self::buildConnections();

        foreach ($connections as $i => $connection) {
            $builder = ClientBuilder::create();
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $builder = $builder->withDriver($alias, $connection);
            $client = $builder->withFormatter(static::formatter())->build();
            self::$drivers[$alias] = $client;
        }

        return \array_values(self::$drivers)[0];
    }

    protected function getClientForScheme(string $scheme): ClientInterface
    {
        $firstAliasForScheme = current(array_filter(array_keys(self::$drivers), fn($alias) => str_starts_with($alias, $scheme)));
        if (false === $firstAliasForScheme) {
            throw new Exception(\sprintf("No client for scheme %s found", $scheme));
        }
        return self::$drivers[$firstAliasForScheme];
    }
}
