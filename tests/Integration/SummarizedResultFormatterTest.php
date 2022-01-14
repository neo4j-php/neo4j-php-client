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

use function bin2hex;
use Exception;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use function random_bytes;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<SummarizedResult<CypherMap<OGMTypes>>>
 */
final class SummarizedResultFormatterTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return SummarizedResultFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAcceptanceRead(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN 1 AS one');
        }, $alias);
        self::assertInstanceOf(SummarizedResult::class, $result);
        self::assertEquals(1, $result->first()->get('one'));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testAcceptanceWrite(string $alias): void
    {
        $counters = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))]);
        }, $alias)->getSummary()->getCounters();
        self::assertEquals(new SummaryCounters(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $counters);
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testGetResults(string $alias): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one', [], $alias)->getResults();

        self::assertNotInstanceOf(SummarizedResult::class, $results);
        self::assertInstanceOf(CypherList::class, $results);

        $jsonSerialize = $results->jsonSerialize();
        self::assertIsArray($jsonSerialize);
        self::assertArrayNotHasKey('summary', $jsonSerialize);
        self::assertArrayNotHasKey('result', $jsonSerialize);

        $first = $results->first();
        self::assertInstanceOf(CypherMap::class, $first);
        self::assertEquals(1, $first->get('one'));
    }
}
