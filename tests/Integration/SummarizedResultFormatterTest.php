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
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;

/**
 * @extends EnvironmentAwareIntegrationTest<SummarizedResult<BasicResults>>
 *
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 */
final class SummarizedResultFormatterTest extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        return new SummarizedResultFormatter(new BasicFormatter());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAcceptanceRead(string $alias): void
    {
        $result = $this->client->run('RETURN 1 AS one', [], $alias);
        self::assertInstanceOf(SummarizedResult::class, $result);
        self::assertEquals(1, $result->getResult()->first()->get('one'));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testAcceptanceWrite(string $alias): void
    {
        self::assertEquals(new SummaryCounters(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $this->client->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))], $alias)->getSummary()->getCounters());
    }
}
