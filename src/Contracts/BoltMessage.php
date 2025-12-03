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

use Bolt\protocol\Response;
use Iterator;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Throwable;

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
        } catch (Throwable $e) {
            if ($this->isTimeoutException($e)) {
                $timeoutMsg = 'Connection timeout reached';
                if (preg_match('/(\d+)\s*(?:milliseconds?|ms|seconds?|s)/', $e->getMessage(), $matches) && array_key_exists(1, $matches)) {
                    $timeoutMsg = 'Connection timeout reached after '.$matches[1].' seconds';
                }
                try {
                    $this->connection->close();
                } catch (Throwable) {
                }
                // Use DriverError so the driver treats this as a failure
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', $timeoutMsg)], $e);
            } elseif ($this->isSocketException($e)) {
                try {
                    $this->connection->close();
                } catch (Throwable) {
                }
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
            }

            throw $e;
        }

        $this->connection->assertNoFailure($response);

        return $response;
    }

    private function isTimeoutException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timeout') || str_contains($message, 'time out');
    }

    private function isSocketException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'interrupted system call')
            || str_contains($message, 'i/o error');
    }

    /**
     * @return Iterator<Response>
     */
    public function getResponses(): Iterator
    {
        /**
         * @var Iterator<Response>
         */
        return $this->connection->protocol()->getResponses();
    }
}
