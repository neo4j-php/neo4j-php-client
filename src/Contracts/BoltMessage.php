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

use Bolt\error\BoltException;
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
        // Temporarily apply recv_timeout hint for this response retrieval
        $this->connection->applyRecvTimeoutTemporarily();
        
        // If no timeout hint is set, apply a shorter default timeout to prevent hanging on disconnect
        // This is especially important for disconnect tests where the server closes the connection
        if ($this->connection->getRecvTimeoutHint() === null && $this->connection->getOriginalTimeout() === null) {
            $currentTimeout = $this->connection->getTimeout();
            // Store original timeout and apply a shorter timeout (5 seconds) for disconnect detection
            $this->connection->setOriginalTimeout($currentTimeout);
            $this->connection->setTimeout(5.0);
        }
        
        try {
            $response = $this->connection->protocol()->getResponse();
        } catch (Throwable $e) {
            // Always restore timeout before handling exception
            $this->connection->restoreOriginalTimeout();
            
            if ($this->isTimeoutException($e)) {
                $timeoutMsg = 'Connection timeout reached';
                if (preg_match('/(\d+)\s*(?:milliseconds?|ms|seconds?|s)/', $e->getMessage(), $matches) && array_key_exists(1, $matches)) {
                    $timeoutMsg = 'Connection timeout reached after '.$matches[1].' seconds';
                }
                try {
                    // Use invalidate() instead of close() to avoid sending GOODBYE on timeout
                    $this->connection->invalidate();
                } catch (Throwable) {
                }
                // Use DriverError so the driver treats this as a failure
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', $timeoutMsg)], $e);
            } elseif ($this->isSocketException($e)) {
                try {
                    // Use invalidate() instead of close() to avoid sending GOODBYE on socket errors
                    $this->connection->invalidate();
                } catch (Throwable) {
                }
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
            }

            // For any other exception, always restore timeout before re-throwing
            throw $e;
        }

        // Restore timeout after successful response retrieval
        $this->connection->restoreOriginalTimeout();

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
        // Check if it's a Bolt socket/connection error
        if ($e instanceof BoltException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'interrupted system call')
            || str_contains($message, 'i/o error')
            || str_contains($message, 'network read incomplete')
            || str_contains($message, 'network write incomplete')
            || str_contains($message, 'broken');
    }

    /**
     * @return Iterator<Response>
     */
    public function getResponses(): Iterator
    {
        /**
         * @var Iterator<Response>
         */
        $innerIterator = $this->connection->protocol()->getResponses();
        
        // Wrap the iterator to catch exceptions during iteration
        return $this->wrapResponseIterator($innerIterator);
    }

    /**
     * Wraps a response iterator to catch and handle exceptions.
     *
     * @param Iterator<Response> $iterator
     *
     * @return Iterator<Response>
     */
    private function wrapResponseIterator(Iterator $iterator): Iterator
    {
        try {
            foreach ($iterator as $response) {
                yield $response;
            }
        } catch (Throwable $e) {
            // Always restore timeout before handling exception
            $this->connection->restoreOriginalTimeout();
            
            if ($this->isTimeoutException($e)) {
                $timeoutMsg = 'Connection timeout reached';
                if (preg_match('/(\d+)\s*(?:milliseconds?|ms|seconds?|s)/', $e->getMessage(), $matches) && array_key_exists(1, $matches)) {
                    $timeoutMsg = 'Connection timeout reached after '.$matches[1].' seconds';
                }
                try {
                    // Use invalidate() instead of close() to avoid sending GOODBYE on timeout
                    $this->connection->invalidate();
                } catch (Throwable) {
                }
                // Use DriverError so the driver treats this as a failure
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', $timeoutMsg)], $e);
            } elseif ($this->isSocketException($e)) {
                try {
                    // Use invalidate() instead of close() to avoid sending GOODBYE on socket errors
                    $this->connection->invalidate();
                } catch (Throwable) {
                }
                throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
            }

            throw $e;
        }
    }
}
