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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\StatementStatistics;
use Laudis\Neo4j\Formatter\StatisticsFormatter;

final class StatisticsFormatterIntegrationTest extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        return new StatisticsFormatter();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAcceptanceRead(string $alias): void
    {
        self::assertEquals(new StatementStatistics(), $this->client->run('RETURN 1', [], $alias));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testAcceptanceWrite(string $alias): void
    {
        self::assertEquals(new StatementStatistics(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $this->client->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))], $alias));
    }
}
