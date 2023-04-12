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

namespace Laudis\Neo4j\Tests\Performance;

use function array_pop;
use function base64_encode;

use Bolt\error\ConnectException;

use function count;

use Laudis\Neo4j\Basic\UnmanagedTransaction;
use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Tests\Integration\EnvironmentAwareIntegrationTest;

use function random_bytes;

use RuntimeException;

use function sleep;

use Symfony\Component\Uid\Uuid;

final class PerformanceTest extends EnvironmentAwareIntegrationTest
{
    public function testBigRandomData(): void
    {
        $this->expectException(RuntimeException::class);

        $this->getSession()->transaction(static function (TSX $tsx) {
            $params = [
                'id' => 'xyz',
            ];

            for ($i = 0; $i < 50000; ++$i) {
                $params[base64_encode(random_bytes(32))] = base64_encode(random_bytes(128));
            }

            $tsx->run('MATCH (a :label {id:$id}) RETURN a', $params);

            throw new RuntimeException('Rollback please');
        });
    }

    /**
     * @throws ConnectException
     */
    public function testMultipleTransactions(): void
    {
        $tsxs = [];
        for ($i = 0; $i < 1000; ++$i) {
            $tsxs = $this->addTransactionOrRun($i, $tsxs);

            if (count($tsxs) >= 50) {
                shuffle($tsxs);
                for ($j = 0; $j < 25; ++$j) {
                    $tsxs = $this->testAndDestructTransaction($tsxs, $j);
                }
            }
        }
    }

    public function testMultipleTransactionsCorrectness(): void
    {
        $id = Uuid::v4();
        for ($i = 0; $i < 2; ++$i) {
            $tsxs = [];
            for ($j = 0; $j < 100; ++$j) {
                $tsxs[] = $this->getSession()->beginTransaction();
            }

            foreach ($tsxs as $tsx) {
                $tsx->run('CREATE (:X {id: $id})', compact('i', 'id'));
            }

            self::assertEquals(0 + $i * 100, $this->getSession()->run('MATCH (x:X {id: $id}) RETURN count(x) AS x', ['id' => $id])->first()->get('x'));

            foreach ($tsxs as $j => $tsx) {
                $tsx->commit();

                self::assertEquals($j + 1 + $i * 100, $this->getSession()->run('MATCH (x:X {id: $id}) RETURN count(x) AS x', ['id' => $id])->first()->get('x'));
            }

            self::assertEquals(($i + 1) * 100, $this->getSession()->run('MATCH (x:X {id: $id}) RETURN count(x) AS x', ['id' => $id])->first()->get('x'));
        }
    }

    /**
     * @param list<UnmanagedTransaction> $tsxs
     *
     * @return list<UnmanagedTransaction>
     */
    private function addTransactionOrRun(int $i, array $tsxs, int $retriesLeft = 10): array
    {
        try {
            if ($i % 2 === 0) {
                $tsx = $this->getSession()->beginTransaction();
                $x = $tsx->run('RETURN 1 AS x')->first()->get('x');
                $tsxs[] = $tsx;
            } else {
                $x = $this->getSession()->run('RETURN 1 AS x', [])->first()->get('x');
            }
            self::assertEquals(1, $x);
        } catch (ConnectException $e) {
            --$retriesLeft;
            if ($retriesLeft === 0) {
                throw $e;
            }

            sleep(5);

            return $this->addTransactionOrRun($i, $tsxs, $retriesLeft);
        }

        return $tsxs;
    }

    /**
     * @param list<UnmanagedTransaction> $tsxs
     *
     * @throws ConnectException
     *
     * @return list<UnmanagedTransaction>
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
