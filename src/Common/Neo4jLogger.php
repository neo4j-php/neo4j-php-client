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

namespace Laudis\Neo4j\Common;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Neo4jLogger
{
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    public function __construct(
        private readonly string $level,
        private readonly ?LoggerInterface $logger,
    ) {}

    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null || !$this->shouldLog($level)) {
            return;
        }

        match ($level) {
            LogLevel::EMERGENCY => $this->logger->emergency($message, $context),
            LogLevel::ALERT => $this->logger->alert($message, $context),
            LogLevel::CRITICAL => $this->logger->critical($message, $context),
            LogLevel::ERROR => $this->logger->error($message, $context),
            LogLevel::WARNING => $this->logger->warning($message, $context),
            LogLevel::NOTICE => $this->logger->notice($message, $context),
            LogLevel::INFO => $this->logger->info($message, $context),
            LogLevel::DEBUG => $this->logger->debug($message, $context),
            default => throw new InvalidArgumentException("Invalid log level: $level"),
        };
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    private function shouldLog(string $level): bool
    {
        return array_search($level, self::LOG_LEVELS) <= array_search($this->level, self::LOG_LEVELS);
    }
}
