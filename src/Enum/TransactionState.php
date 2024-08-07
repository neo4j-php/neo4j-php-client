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

namespace Laudis\Neo4j\Enum;

/**
 * The state of a transaction.
 */
enum TransactionState
{
    /**
     * The transaction is running with no explicit success or failure marked.
     */
    case ACTIVE;

    /**
     * This transaction has been terminated because of a fatal connection error.
     */
    case TERMINATED;

    /**
     * This transaction has successfully committed.
     */
    case COMMITTED;

    /**
     * This transaction has been rolled back.
     */
    case ROLLED_BACK;
}
