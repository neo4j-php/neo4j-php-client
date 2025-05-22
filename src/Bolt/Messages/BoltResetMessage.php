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

namespace Laudis\Neo4j\Bolt\Messages;

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Psr\Log\LogLevel;

final class BoltResetMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly ?Neo4jLogger $logger,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltResetMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'RESET');
        $this->connection->protocol()->reset();

        return $this;
    }
}
