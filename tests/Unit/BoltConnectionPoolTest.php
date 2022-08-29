<?php

declare(strict_types=1);

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Bolt\ConnectionPool;
use Monolog\Test\TestCase;

class BoltConnectionPoolTest extends TestCase
{
    private ConnectionPool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = new ConnectionPool()
    }
}
