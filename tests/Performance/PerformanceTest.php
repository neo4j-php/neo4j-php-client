<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Performance;

use function array_pop;
use function base64_encode;
use Bolt\error\ConnectException;
use function count;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Tests\Integration\EnvironmentAwareIntegrationTest;
use function random_bytes;
use RuntimeException;
use function sleep;
use function str_starts_with;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class PerformanceTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return BasicFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testBigRandomData(string $alias): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectErrorMessage('Rollback please');

        $this->getClient()->transaction(static function (TSX $tsx) {
            $params = [
                'id' => 'xyz',
            ];

            for ($i = 0; $i < 100000; ++$i) {
                $params[base64_encode(random_bytes(32))] = base64_encode(random_bytes(128));
            }

            $tsx->run('MATCH (a :label {id:$id}) RETURN a', $params);

            throw new RuntimeException('Rollback please');
        }, $alias);
    }

    /**
     * @throws ConnectException
     */
    public function testMultipleTransactions(): void
    {
        $aliases = array_values(self::connectionAliases());
        $tsxs = [];
        for ($i = 0; $i < 1000; ++$i) {
            $alias = $aliases[$i % count($aliases)][0];

            $tsxs = $this->addTransactionOrRun($i, $alias, $tsxs);

            if (count($tsxs) >= 50) {
                shuffle($tsxs);
                for ($j = 0; $j < 25; ++$j) {
                    $tsxs = $this->testAndDestructTransaction($tsxs, $j);
                }
            }
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMultipleTransactionsCorrectness(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $this->getClient()->transaction(static fn (TSX $tsx) => $tsx->run('MATCH (x) DETACH DELETE (x)'), $alias);

        for ($i = 0; $i < 2; ++$i) {
            $tsxs = [];
            for ($j = 0; $j < 100; ++$j) {
                $tsxs[] = $this->getClient()->beginTransaction(null, $alias);
            }

            foreach ($tsxs as $tsx) {
                $tsx->run('CREATE (:X {y: "z"})');
            }

            self::assertEquals(0 + $i * 100, $this->getClient()->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));

            foreach ($tsxs as $j => $tsx) {
                $tsx->commit();

                self::assertEquals($j + 1 + $i * 100, $this->getClient()->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
            }

            self::assertEquals(($i + 1) * 100, $this->getClient()->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
        }
    }

    /**
     * @param list<UnmanagedTransactionInterface<BasicResults>> $tsxs
     *
     * @throws ConnectException
     *
     * @return list<UnmanagedTransactionInterface<BasicResults>>
     */
    private function addTransactionOrRun(int $i, string $alias, array $tsxs, int $retriesLeft = 10): array
    {
        try {
            if ($i % 2 === 0) {
                $tsx = $this->getClient()->beginTransaction(null, $alias);
                $x = $tsx->run('RETURN 1 AS x')->first()->get('x');
                $tsxs[] = $tsx;
            } else {
                $x = $this->getClient()->run('RETURN 1 AS x', [], $alias)->first()->get('x');
            }
            self::assertEquals(1, $x);
        } catch (ConnectException $e) {
            --$retriesLeft;
            if ($retriesLeft === 0) {
                throw $e;
            }

            sleep(5);

            return $this->addTransactionOrRun($i, $alias, $tsxs, $retriesLeft);
        }

        return $tsxs;
    }

    /**
     * @param list<UnmanagedTransactionInterface<BasicResults>> $tsxs
     *
     * @throws ConnectException
     *
     * @return list<UnmanagedTransactionInterface<BasicResults>>
     */
    private function testAndDestructTransaction(array $tsxs, int $j, int $retriesLeft = 10): array
    {
        $tsx = array_pop($tsxs);
        try {
            $x = $tsx->run('RETURN 1 AS x')->first()->get('x');

            self::assertEquals(1, $x);

            if ($j % 2 === 0) {
                $tsx->commit();
            }
        } catch (ConnectException $e) {
            --$retriesLeft;
            if ($retriesLeft === 0) {
                throw $e;
            }

            sleep(5);

            return $this->testAndDestructTransaction($tsxs, $j, $retriesLeft);
        }

        return $tsxs;
    }
}
