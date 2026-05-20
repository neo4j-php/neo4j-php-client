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

namespace Laudis\Neo4j\Tests;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Forwards each PSR-3 call to every delegate logger.
 *
 * We call the *specific* level method (info(), debug(), ...) on every delegate
 * rather than routing through log(), because tests assert on PHPUnit mock
 * expectations like `->method('info')` and `->method('debug')`. Routing through
 * log() would bypass those expectations.
 */
final class TeeLogger implements LoggerInterface
{
    /**
     * @param list<LoggerInterface> $loggers
     */
    public function __construct(private readonly array $loggers)
    {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->emergency($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->alert($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->critical($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->error($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->warning($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->notice($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->info($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->debug($message, $context);
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
