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
        'authentication' => [
            'TestAuthenticationBasic' => [
                'testSuccessOnProvideRealmWithBasicToken' => true,
                'testSuccessOnBasicToken' => true,
                'testErrorOnIncorrectCredentials' => true,
            ],
        ],
        'datatypes' => [
            'TestDataTypes' => [
                'test_should_echo_back' => true,
                'test_should_echo_very_long_list' => true,
                'test_should_echo_very_long_string' => true,
                'test_should_echo_node' => true,
                'test_should_echo_list_of_maps' => true,
                'test_should_echo_map_of_lists' => true,
                'test_should_echo_nested_lists' => true,
                'test_should_echo_nested_map' => true,
                'test_should_echo_very_long_map' => 'Work in progress on testkit frontend',
            ],
        ],
    ],
];
