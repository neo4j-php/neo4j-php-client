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

namespace Laudis\Neo4j\Tests\Integration;

use function bin2hex;

use DateTimeImmutable;

use function dump;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\DateTimeZoneId;

use function random_bytes;
use function serialize;
use function unserialize;

final class SummarizedResultFormatterTest extends EnvironmentAwareIntegrationTest
{
    public function testAcceptanceRead(): void
    {
        $result = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS one'));
        self::assertInstanceOf(SummarizedResult::class, $result);
        self::assertEquals(1, $result->first()->get('one'));
    }

    public function testAcceptanceWrite(): void
    {
        $counters = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))]))->getSummary()->getCounters();
        self::assertEquals(new SummaryCounters(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $counters);
    }

    public function testGetResults(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', [])->getResults();

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

    public function testSerialize(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', []);

        $serialise = serialize($results);
        $resultHasBeenSerialized = unserialize($serialise);

        self::assertInstanceOf(SummarizedResult::class, $resultHasBeenSerialized);
        self::assertEquals($results->toRecursiveArray(), $resultHasBeenSerialized->toRecursiveArray());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDump(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', []);

        dump($results);
    }

    public function testConsumedPositive(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one');

        self::assertInstanceOf(SummarizedResult::class, $results);

        self::assertGreaterThan(0, $results->getSummary()->getResultConsumedAfter());
    }

    public function testAvailableAfter(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one');

        self::assertInstanceOf(SummarizedResult::class, $results);

        self::assertGreaterThan(0, $results->getSummary()->getResultAvailableAfter());
    }

    public function testDateTime(): void
    {
        $dt = new DateTimeImmutable();
        $ls = $this->getSession()->run('RETURN $x AS x', ['x' => $dt])->first()->get('x');

        $this->assertInstanceOf(DateTimeZoneId::class, $ls);
        $this->assertEquals($dt, $ls->toDateTime());
    }
}
