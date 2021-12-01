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
use function count;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Tests\Integration\EnvironmentAwareIntegrationTest;
use function random_bytes;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class PerfomanceTest extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        /** @psalm-suppress InvalidReturnStatement */
        return BasicFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testBigRandomData(string $alias): void
    {
        $tsx = $this->client->getDriver($alias)
            ->createSession()
            ->beginTransaction();

        $params = [
            'id' => 'xyz',
        ];

        for ($i = 0; $i < 100000; ++$i) {
            $params[base64_encode(random_bytes(32))] = base64_encode(random_bytes(128));
        }

        $tsx->run('MATCH (a :label {id:$id}) RETURN a', $params);

        $tsx->rollback();

        self::assertTrue(true);
    }

    public function testMultipleTransactions(): void
    {
        $aliases = $this->connectionAliases();
        $tsxs = [];
        for ($i = 0; $i < 1000; ++$i) {
            $alias = $aliases[$i % count($aliases)][0];
            if ($i % 2 === 0) {
                $tsx = $this->client->beginTransaction(null, $alias);
                $x = $tsx->run('RETURN 1 AS x')->first()->get('x');
                $tsxs[] = $tsx;
            } else {
                $x = $this->client->run('RETURN 1 AS x', [], $alias)->first()->get('x');
            }

            self::assertEquals(1, $x);
            if ($i % 200 === 49) {
                self::assertEquals(1, $x);
                for ($j = 0; $j < 19; ++$j) {
                    $tsx = array_pop($tsxs);
                    $x = $tsx->run('RETURN 1 AS x')->first()->get('x');

                    self::assertEquals(1, $x);

                    if ($j % 2 === 0) {
                        $tsx->commit();
                    }
                }
            }
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMultipleTransactionsCorrectness(string $alias): void
    {
        $this->client->run('MATCH (x) DETACH DELETE (x)', [], $alias);

        for ($i = 0; $i < 2; ++$i) {
            $tsxs = [];
            for ($j = 0; $j < 100; ++$j) {
                $tsxs[] = $this->client->beginTransaction(null, $alias);
            }

            foreach ($tsxs as $tsx) {
                $tsx->run('CREATE (:X {y: "z"})');
            }

            self::assertEquals(0 + $i * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));

            foreach ($tsxs as $j => $tsx) {
                $tsx->commit();

                self::assertEquals($j + 1 + $i * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
            }

            self::assertEquals(($i + 1) * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
        }
    }
}
