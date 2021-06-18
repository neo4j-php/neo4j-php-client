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
use function date_get_last_errors;
use DateInterval;
use DateTimeImmutable;
use Exception;
use function explode;
use Iterator;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;
use RuntimeException;
use function str_pad;
use function substr;

/**
 * @psalm-import-type MetaArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 */
final class HttpOGMStringTranslator
{
    /**
     * @param Iterator<MetaArray> $meta
     *
     * @throws Exception
     *
     * @return string|Date|DateTime|Duration|LocalDateTime|LocalTime|Time
     */
    public function translate(Iterator $meta, string $value)
    {
        $type = $meta->current()['type'] ?? null;

        switch ($type) {
            case 'duration':
                $meta->next();
                $tbr = $this->translateDuration($value);
                break;
            case 'datetime':
                $meta->next();
                $tbr = $this->translateDateTime($value);
                break;
            case 'date':
                $meta->next();
                $tbr = $this->translateDate($value);
                break;
            case 'time':
                $meta->next();
                $tbr = $this->translateTime($value);
                break;
            case 'localdatetime':
                $meta->next();
                $tbr = $this->translateLocalDateTime($value);
                break;
            case 'localtime':
                $meta->next();
                $tbr = $this->translateLocalTime($value);
                break;
            default:
                $tbr = $value;
                break;
        }

        return $tbr;
    }

    /**
     * @throws Exception
     */
    private function translateDuration(string $value): Duration
    {
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

    /**
     * @throws JsonException
     */
    private function translateDate(string $value): Date
    {
        $epoch = new DateTimeImmutable('@0');
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            throw new RuntimeException(json_encode(date_get_last_errors(), JSON_THROW_ON_ERROR));
        }
        $diff = $date->diff($epoch);

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
        if (str_contains($time, '+')) {
            [$time, $timezone] = explode('+', $time);
            [$tzHours, $tzMinutes] = explode(':', $timezone);
            $tz = (int) $tzHours * 60 * 60 + (int) $tzMinutes * 60;
        }
        [$time, $milliseconds] = explode('.', $time);

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($date === false) {
            throw new RuntimeException(json_encode(date_get_last_errors(), JSON_THROW_ON_ERROR));
        }

        if ($tz !== null) {
            return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, $tz);
        }

        return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, 0);
    }

    /**
     * @throws JsonException
     */
    private function translateLocalDateTime(string $value): LocalDateTime
    {
        [$date, $time] = explode('T', $value);
        [$time, $milliseconds] = explode('.', $time);

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($date === false) {
            throw new RuntimeException(json_encode(date_get_last_errors(), JSON_THROW_ON_ERROR));
        }

        return new LocalDateTime($date->getTimestamp(), (int) $milliseconds * 1000000);
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
