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

use Laudis\Neo4j\ClientBuilder;

include __DIR__.'/vendor/autoload.php';

$retriesLeft = 10;

while ($retriesLeft >= 0) {
    try {
        $client = ClientBuilder::create()
            ->addHttpConnection('default', 'http://neo4j:test@neo4j')
            ->build();
        $client->openTransaction();

        return;
    } catch (Throwable $e) {
        error_log($e->getMessage()."\n");
        --$retriesLeft;
        sleep(5);
    }
}

/** @noinspection ForgottenDebugOutputInspection */
error_log('Could not connect to database');
exit(1);
