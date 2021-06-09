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

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

final class TransactionIntegrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        putenv('RES_OPTIONS=retrans:1 retry:1 timeout:1 attempts:1');
    }

    /**
     * @return iterable<array-key, array>
     */
    public function makeTransactions(): iterable
    {
        $client = ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->build();

        $tbr = [];
        $tbr[] = [$client->openTransaction(null, 'bolt')];
        $tbr[] = [$client->openTransaction(null, 'http')];

        /** @var iterable<array-key, array> */
        return $tbr;
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testValidRun(UnmanagedTransactionInterface $transaction): void
    {
        $response = $transaction->run(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b']);

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->get('x'));
        self::assertEquals(['test' => 'b'], $map->get('y'));
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->get('map'));
        self::assertEquals([1, 2, 3], $map->get('list'));
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testInvalidRun(UnmanagedTransactionInterface $transaction): void
    {
        $exception = false;
        try {
            $transaction->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testValidStatement(UnmanagedTransactionInterface $transaction): void
    {
        $response = $transaction->runStatement(
            Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b'])
        );

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->get('x'));
        self::assertEquals(['test' => 'b'], $map->get('y'));
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->get('map'));
        self::assertEquals([1, 2, 3], $map->get('list'));
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testInvalidStatement(UnmanagedTransactionInterface $transaction): void
    {
        $exception = false;
        try {
            $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            $transaction->runStatement($statement);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testStatements(UnmanagedTransactionInterface $transaction): void
    {
        $params = ['test' => 'a', 'otherTest' => 'b'];
        $response = $transaction->runStatements([
            Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
CYPHER,
                $params
            ),
            Statement::create(<<<'CYPHER'
MERGE (x:OtherTestNode {test: $otherTest})
CYPHER,
                $params
            ),
            Statement::create(<<<'CYPHER'
RETURN 1 AS x
CYPHER,
                []
            ),
        ]);

        self::assertEquals(3, $response->count());
        self::assertEquals(0, $response->get(0)->count());
        self::assertEquals(0, $response->get(1)->count());
        self::assertEquals(1, $response->get(2)->count());
        self::assertEquals(1, $response->get(2)->first()->get('x'));
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testInvalidStatements(UnmanagedTransactionInterface $transaction): void
    {
        $exception = false;
        try {
            $params = ['test' => 'a', 'otherTest' => 'b'];
            $transaction->runStatements([
                Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
CYPHER,
                    $params
                ),
                Statement::create(<<<'CYPHER'
MERGE (x:OtherTestNode {test: $otherTest})
CYPHER,
                    $params
                ),
                Statement::create('1 AS x;erns', []),
            ]);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testCommitValidEmpty(UnmanagedTransactionInterface $transaction): void
    {
        $result = $transaction->commit();
        self::assertEquals(0, $result->count());
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testCommitValidFilled(UnmanagedTransactionInterface $transaction): void
    {
        $result = $transaction->commit([Statement::create(<<<'CYPHER'
UNWIND [1, 2, 3] AS x
RETURN x
CYPHER
        )]);
        self::assertEquals(1, $result->count());
        self::assertEquals(3, $result->first()->count());
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testCommitValidFilledWithInvalidStatement(UnmanagedTransactionInterface $transaction): void
    {
        $exception = false;
        try {
            $transaction->commit([Statement::create('adkjbehqjk')]);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testCommitInvalid(UnmanagedTransactionInterface $transaction): void
    {
        $transaction->commit();
        $exception = false;
        try {
            $transaction->commit();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testRollbackValid(UnmanagedTransactionInterface $transaction): void
    {
        $transaction->rollback();
        self::assertTrue(true);
    }

    /**
     * @dataProvider makeTransactions
     *
     * @param UnmanagedTransactionInterface<Vector<Map<string, scalar|array|null>>> $transaction
     */
    public function testRollbackInvalid(UnmanagedTransactionInterface $transaction): void
    {
        $transaction->rollback();
        $exception = false;
        try {
            $transaction->rollback();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }
}
