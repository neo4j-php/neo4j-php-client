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

namespace Laudis\Neo4j\Contracts;

use Bolt\error\ConnectionTimeoutException;
use Bolt\protocol\Response;
use Iterator;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Exception\TimeoutException;

abstract class BoltMessage
{
    public function __construct(
        protected readonly BoltConnection $connection,
    ) {
    }

    /**
     * Sends the Bolt message.
     */
    abstract public function send(): BoltMessage;

    public function getResponse(): Response
    {
        try {
            $response = $this->connection->protocol()->getResponse();
        } catch (ConnectionTimeoutException $e) {
            throw new TimeoutException(previous: $e);
        }

        $this->connection->assertNoFailure($response);

        return $response;
    }

    /**
     * @return Iterator<Response>
     */
    public function getResponses(): Iterator
    {
        try {
            /**
             * @var Iterator<Response>
             */
            return $this->connection->protocol()->getResponses();
        } catch (ConnectionTimeoutException $e) {
            throw new TimeoutException(previous: $e);
        }
    }
}
