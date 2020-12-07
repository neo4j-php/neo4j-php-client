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

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Tests\Base\TransactionTest;

final class TransactionIntegrationTest extends TransactionTest
{
    protected function makeTransactions(): iterable
    {
        $client = ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->build();

        return [$client->openTransaction(null, 'http'), $client->openTransaction(null, 'bolt')];
    }
}
