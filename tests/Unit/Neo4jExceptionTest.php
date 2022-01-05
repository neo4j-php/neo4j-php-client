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

namespace Laudis\Neo4j\Tests\Unit;

use Error;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

final class Neo4jExceptionTest extends TestCase
{
    private array $errors;
    private Error $previous;
    private Neo4jException $exception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errors = [new Neo4jError('0', 'abc', '', '', ''), new Neo4jError('1', 'efg', '', '', '')];
        $this->previous = new Error();
        $this->exception = new Neo4jException($this->errors, $this->previous);
    }

    public function testErrors(): void
    {
        self::assertEquals($this->errors, $this->exception->getErrors());
    }

    public function testMessage(): void
    {
        self::assertEquals('Neo4j errors detected. First one with code "0" and message "abc"', $this->exception->getMessage());
    }

    public function testPrevious(): void
    {
        self::assertSame($this->previous, $this->exception->getPrevious());
    }
}
