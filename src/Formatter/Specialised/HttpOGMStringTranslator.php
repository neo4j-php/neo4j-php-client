<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Formatter\Specialised;

use function count;
use DateInterval;
use DateTimeImmutable;
use Exception;
use function explode;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;
use RuntimeException;
use function sprintf;
use function str_pad;
use function substr;

/**
 * Maps the strings to their respective values.
 *
 * @psalm-import-type MetaArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 *
 * @psalm-immutable
 */
final class HttpOGMStringTranslator
{
    /**
     * @param MetaArray $meta
     *
     * @throws Exception
     *
     * @return array{0: int, 1: string|Date|DateTime|Duration|LocalDateTime|LocalTime|Time}
     */
    public function translate(int $metaIndex, array $meta, string $value)
    {
        $type = $meta[$metaIndex]['type'] ?? null;

        switch ($type) {
            case 'duration':
                $tbr = [1, $this->translateDuration($value)];
                break;
            case 'datetime':
                $tbr = [1, $this->translateDateTime($value)];
                break;
            case 'date':
                $tbr = [1, $this->translateDate($value)];
                break;
            case 'time':
                $tbr = [1, $this->translateTime($value)];
                break;
            case 'localdatetime':
                $tbr = [1, $this->translateLocalDateTime($value)];
                break;
            case 'localtime':
                $tbr = [1, $this->translateLocalTime($value)];
                break;
            default:
                $tbr = [0, $value];
                break;
        }

        return $tbr;
    }

    /**
     * @throws Exception
     */
    private function translateDuration(string $value): Duration
    {
        /** @psalm-suppress ImpureFunctionCall false positive in version php 7.4 */
        if (str_contains($value, '.')) {
            [$format, $secondsFraction] = explode('.', $value);
            $nanoseconds = (int) substr($secondsFraction, 6);
            $microseconds = (int) str_pad((string) ((int) substr($secondsFraction, 0, 6)), 6, '0');
            $interval = new DateInterval($format.'S');
            $x = new DateTimeImmutable();
            $interval = $x->add($interval)->modify('+'.$microseconds.' microseconds')->diff($x);
        } else {
            $nanoseconds = 0;
            $interval = new DateInterval($value);
        }

        $months = $interval->y * 12 + $interval->m;
        $days = $interval->d;
        $seconds = $interval->h * 60 * 60 + $interval->i * 60 + $interval->s;
        $nanoseconds = (int) ($interval->f * 1000000000) + $nanoseconds;

        return new Duration($months, $days, $seconds, $nanoseconds);
    }

    private function translateDate(string $value): Date
    {
        $epoch = new DateTimeImmutable('@0');
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d" and %s', $value));
        }

        $diff = $dateTime->diff($epoch);

        /** @psalm-suppress ImpureMethodCall */
        return new Date((int) $diff->format('%a'));
    }

    private function translateTime(string $value): Time
    {
        $value = substr($value, 0, 5);
        $values = explode(':', $value);

        /** @psalm-suppress PossiblyUndefinedIntArrayOffset */
        return new Time(((int) $values[0]) * 60 * 60 + ((int) $values[1]) * 60);
    }

    /**
     * @throws Exception
     */
    private function translateDateTime(string $value): DateTime
    {
        [$date, $time] = explode('T', $value);
        $tz = null;
        /** @psalm-suppress ImpureFunctionCall false positive in version php 7.4 */
        if (str_contains($time, '+')) {
            [$time, $timezone] = explode('+', $time);
            [$tzHours, $tzMinutes] = explode(':', $timezone);
            $tz = (int) $tzHours * 60 * 60 + (int) $tzMinutes * 60;
        }
        [$time, $milliseconds] = explode('.', $time);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d H:i:s" and %s', $date.' '.$time));
        }

        if ($tz !== null) {
            return new DateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000, $tz);
        }

        return new DateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000, 0);
    }

    private function translateLocalDateTime(string $value): LocalDateTime
    {
        [$date, $time] = explode('T', $value);
        [$time, $milliseconds] = explode('.', $time);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d H:i:s" and %s', $date.' '.$time));
        }

        return new LocalDateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000);
    }

    /**
     * @psalm-suppress all
     *
     * @throws Exception
     */
    private function translateLocalTime(string $value): LocalTime
    {
        $timestamp = (new DateTimeImmutable($value))->getTimestamp();

        $hours = (int) date('H', $timestamp);
        $minutes = (int) date('i', $timestamp);
        $seconds = (int) date('s', $timestamp);
        $milliseconds = 0;

        $values = explode('.', $value);
        if (count($values) > 1) {
            $milliseconds = $values[1];
        }

        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($milliseconds / 1000);

        return new LocalTime((int) $totalSeconds * 1000000000);
    }
}
