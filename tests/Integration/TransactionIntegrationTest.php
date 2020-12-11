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
        $versions = ['42', '41', '40', '35'];
        $builder = ClientBuilder::create();
        foreach ($versions as $version) {
            $hostname = 'neo4j-'.$version;
            if (gethostbyname($hostname) !== $hostname) {
                $builder->addBoltConnection('bolt-'.$version, 'bolt://neo4j:test@'.$hostname);
                $builder->addHttpConnection('http-'.$version, 'http://neo4j:test@'.$hostname);
            }
        }
        $client = $builder->build();

        $tbr = [];
        foreach ($versions as $version) {
            $hostname = 'neo4j-'.$version;
            if (gethostbyname($hostname) !== $hostname) {
                $tbr[] = $client->openTransaction(null, 'bolt-'.$version);
                $tbr[] = $client->openTransaction(null, 'http-'.$version);
            }
        }

        return $tbr;
    }
}
