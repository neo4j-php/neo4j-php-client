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

return [
    'neo4j' => [
        'datatypes' => [
            'TestDataTypes' => [
                'test_should_echo_very_long_map' => 'Work in progress on testkit frontend',
            ],
        ],
        'sessionrun' => [
            'TestSessionRun' => [
                'test_autocommit_transactions_should_support_metadata' => 'Meta data isn\'t supported yet',
                'test_autocommit_transactions_should_support_timeout' => 'Waiting on bookmarks isn\'t supported yet',
            ],
        ],
        'test_direct_driver' => [
            'TestDirectDriver' => [
                'test_custom_resolver' => 'No custom resolver implemented',
                'test_fail_nicely_when_using_http_port' => 'Not implemented yet',
            ],
        ],
        'test_summary' => [
            'TestDirectDriver' => [
                'test_agent_string' => 'This is not an official driver yet',
            ],
        ],
        'txrun' => [
            'TestTxRun' => [
                'test_should_fail_to_run_query_for_invalid_bookmark' => 'Waiting on bookmarks isn\'t supported yet',
            ],
        ],
        'txfuncrun' => [
            'TestTxFuncRun' => [
                'test_iteration_nested' => 'Buffers not supported yet',
                'test_updates_last_bookmark_on_commit' => 'Waiting on bookmarks isn\'t supported yet',
            ],
        ],
    ],
];
