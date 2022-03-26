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

namespace Laudis\Neo4j\Bolt;

use function in_array;

/**
 * @see https://7687.org/bolt/bolt-protocol-server-state-specification-4.html#version-43
 */
final class ServerStateRepository
{
    private const KEYS = ['State' => 0, 'Request Message' => 1, 'Triggers Signal' => 2, 'Server Response' => 3, 'New State' => 4];
    public function canSendMessage(string $state, string $message): bool
    {
        return in_array($message, $this->getMessagesForState($state), true);
    }

    public function isValidState(string $state): bool
    {
        return in_array($state, $this->getStates(), true);
    }

    private const TRANSITIONS = [
        ['CONNECTED', 'HELLO', null, 'SUCCESS', 'READY'],
        ['CONNECTED', 'HELLO', null, 'FAILURE', 'DEFUNCT'],

        ['READY', 'RUN', null, 'SUCCESS', 'STREAMING'],
        ['READY', 'RUN', null, 'FAILURE', 'FAILED'],
        ['READY', 'BEGIN', null, 'SUCCESS', 'TX_READY'],
        ['READY', 'BEGIN', null, 'FAILURE', 'FAILED'],
        ['READY', 'ROUTE', null, 'SUCCESS', 'READY'],
        ['READY', 'RESET', 'INTERRUPT', null, null],
        ['READY', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],

        ['STREAMING', 'PULL', null, 'SUCCESS', 'STREAMING'],
        ['STREAMING', 'PULL', null, 'SUCCESS', 'READY'],
        ['STREAMING', 'PULL', null, 'FAILURE', 'FAILED'],
        ['STREAMING', 'DISCARD', null, 'SUCCESS', 'STREAMING'],
        ['STREAMING', 'DISCARD', null, 'SUCCESS', 'READY'],
        ['STREAMING', 'DISCARD', null, 'FAILURE', 'FAILED'],
        ['STREAMING', 'RESET', 'INTERRUPT', null, null],
        ['STREAMING', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],

        ['TX_READY', 'RUN', null, 'SUCCESS', 'TX_STREAMING'],
        ['TX_READY', 'RUN', null, 'FAILURE', 'FAILED'],
        ['TX_READY', 'COMMIT', null, 'SUCCESS', 'READY'],
        ['TX_READY', 'COMMIT', null, 'FAILURE', 'FAILED'],
        ['TX_READY', 'ROLLBACK', null, 'SUCCESS', 'READY'],
        ['TX_READY', 'ROLLBACK', null, 'FAILURE', 'FAILED'],
        ['TX_READY', 'RESET', 'INTERRUPT', null, null],
        ['TX_READY', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],

        ['TX_STREAMING', 'RUN', null, 'SUCCESS', 'TX_STREAMING'],
        ['TX_STREAMING', 'RUN', null, 'FAILURE', 'FAILED'],
        ['TX_STREAMING', 'PULL', null, 'SUCCESS', 'TX_STREAMING'],
        ['TX_STREAMING', 'PULL', null, 'SUCCESS', 'TX_READY'],
        ['TX_STREAMING', 'PULL', null, 'FAILURE', 'FAILED'],
        ['TX_STREAMING', 'DISCARD', null, 'SUCCESS', 'TX_STREAMING'],
        ['TX_STREAMING', 'DISCARD', null, 'SUCCESS', 'TX_READY'],
        ['TX_STREAMING', 'DISCARD', null, 'FAILURE', 'FAILED'],
        ['TX_STREAMING', 'RESET', 'INTERRUPT', null, null],
        ['TX_STREAMING', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],

        ['FAILED', 'RUN', null, 'IGNORED', 'FAILED'],
        ['FAILED', 'PULL', null, 'IGNORED', 'FAILED'],
        ['FAILED', 'DISCARD', null, 'IGNORED', 'FAILED'],
        ['FAILED', 'RESET', 'INTERRUPT', null, null],
        ['FAILED', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],

        ['INTERRUPTED', 'RUN', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'PULL', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'DISCARD', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'BEGIN', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'COMMIT', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'ROLLBACK', null, 'IGNORED', 'INTERRUPTED'],
        ['INTERRUPTED', 'RESET', 'INTERRUPT', 'SUCCESS', 'READY'],
        ['INTERRUPTED', 'RESET', 'INTERRUPT', 'FAILURE', 'DEFUNCT'],
        ['INTERRUPTED', 'GOODBYE', 'DISCONNECT', null, 'DEFUNCT'],
    ];

    /**
     * @return list<string>
     */
    public function getMessagesForState(string $state): array
    {
        $messages = [];
        foreach (self::TRANSITIONS as $transition) {
            if ($transition[self::KEYS['State']] === $state) {
                $messages[] = $transition[self::KEYS['Request Message']];
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    public function getStates(): array
    {
        /** @var list<string> */
        return array_unique(array_map(static fn (array $x) => $x[self::KEYS['State']], self::TRANSITIONS));
    }
}
