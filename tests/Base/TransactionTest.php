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

namespace Laudis\Neo4j\Tests\Base;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

abstract class TransactionTest extends TestCase
{
    /** @var iterable<TransactionInterface> */
    private iterable $transactions;

    /**
     * @return iterable<TransactionInterface>
     */
    abstract protected function makeTransactions(): iterable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactions = $this->makeTransactions();
    }

    public function testValidRun(): void
    {
        foreach ($this->transactions as $transaction) {
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
    }

    public function testInvalidRun(): void
    {
        foreach ($this->transactions as $transaction) {
            $exception = false;
            try {
                $transaction->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testValidStatement(): void
    {
        foreach ($this->transactions as $transaction) {
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
    }

    public function testInvalidStatement(): void
    {
        foreach ($this->transactions as $transaction) {
            $exception = false;
            try {
                $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
                $transaction->runStatement($statement);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testStatements(): void
    {
        foreach ($this->transactions as $transaction) {
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
    }

    public function testInvalidStatements(): void
    {
        foreach ($this->transactions as $transaction) {
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
    }

    public function testCommitValidEmpty(): void
    {
        foreach ($this->transactions as $transaction) {
            $result = $transaction->commit();
            self::assertEquals(0, $result->count());
        }
    }

    public function testCommitValidFilled(): void
    {
        foreach ($this->transactions as $transaction) {
            $result = $transaction->commit([Statement::create(<<<'CYPHER'
UNWIND [1, 2, 3] AS x
RETURN x
CYPHER
            )]);
            self::assertEquals(1, $result->count());
            self::assertEquals(3, $result->first()->count());
        }
    }

    public function testCommitValidFilledWithInvalidStatement(): void
    {
        foreach ($this->transactions as $transaction) {
            $exception = false;
            try {
                $transaction->commit([Statement::create('adkjbehqjk')]);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testCommitInvalid(): void
    {
        foreach ($this->transactions as $transaction) {
            $transaction->commit();
            $exception = false;
            try {
                $transaction->commit();
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testRollbackValid(): void
    {
        foreach ($this->transactions as $transaction) {
            $transaction->rollback();
            self::assertTrue(true);
        }
    }

    public function testRollbackInvalid(): void
    {
        foreach ($this->transactions as $transaction) {
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
}
