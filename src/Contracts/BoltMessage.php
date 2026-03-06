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
            if ($this->isTimeoutException($e) || $this->isSocketException($e)) {
                try {
                    $this->connection->invalidate();
                } catch (Throwable $invalidateException) {
                }
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
