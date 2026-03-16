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

        // If no timeout hint is set, apply a default timeout to prevent hanging on disconnect.
        // 30 seconds balances CI stability with disconnect detection.
        if ($this->connection->getRecvTimeoutHint() === null && $this->connection->getOriginalTimeout() === null) {
            $currentTimeout = $this->connection->getTimeout();
            $this->connection->setOriginalTimeout($currentTimeout);
            $this->connection->setTimeout(30.0);
        }

        try {
            $response = $this->connection->protocol()->getResponse();
        } catch (Throwable $e) {
            if ($this->isTimeoutException($e) || $this->isSocketException($e)) {
                $this->connection->invalidate();
                // Rethrow original exception - Session retry logic inspects it via isConnectionError().
            }

            throw $e;
        }

        // Restore timeout after successful response retrieval
        $this->connection->restoreOriginalTimeout();

        $this->connection->assertNoFailure($response);

        return $response;
    }

    private function tryInvalidateConnection(): void
    {
        $this->connection->invalidate();
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
            || str_contains($message, 'connection closed')
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
            $this->connection->restoreOriginalTimeout();

            if ($this->isTimeoutException($e) || $this->isSocketException($e)) {
                $this->tryInvalidateConnection();
            }

            throw $e;
        }
    }
}
