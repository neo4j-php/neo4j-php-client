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

return [
    'stub' => [
        'disconnects' => [
            'test_disconnects' => [
                'TestDisconnects' => [
                    'test_disconnect_session_on_tx_pull_after_record' => 'Connection drops after RECORD causes commit to fail with Connection is closed',
                    'test_disconnect_session_on_pull_after_record' => 'Driver reports success instead of after first next when server exits after RECORD',
                ],
            ],
        ],
        'configuration_hints' => [
            'test_connection_recv_timeout_seconds' => [
                'TestRoutingConnectionRecvTimeout' => [
                    'test_in_time_managed_tx_retry' => 'HANGUP count mismatch - expected 1, got 0',
                ],
            ],
        ],
    ],
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
