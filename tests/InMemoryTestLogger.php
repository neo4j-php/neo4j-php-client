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

use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

/**
 * Collects every PSR-3 log call into an in-memory list so integration tests
 * can dump the full wire log on failure without printing anything on success.
 *
 * Used by {@see EnvironmentAwareIntegrationTest} via {@see TeeLogger} so the
 * existing mock-based assertions keep working unchanged.
 */
final class InMemoryTestLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    private array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function reset(): void
    {
        $this->records = [];
    }

    /**
     * Render captured records as a single multi-line string suitable for STDERR.
     */
    public function dump(): string
    {
        if ($this->records === []) {
            return '(no log records captured)';
        }

        $lines = [];
        foreach ($this->records as $i => $record) {
            $context = $this->formatContext($record['context']);
            $lines[] = sprintf(
                '%4d  [%s] %s%s',
                $i,
                strtoupper($record['level']),
                $record['message'],
                $context === '' ? '' : ' '.$context,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        try {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($encoded)) {
                return $encoded;
            }
        } catch (Throwable) {
        }

        return '<unserializable context>';
    }
}
