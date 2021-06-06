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

use BadMethodCallException;
use DateInterval;
use DateTimeImmutable;
use Exception;
use function explode;
use Iterator;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;
use function str_pad;
use function substr;

final class HttpOGMStringTranslator
{
    /**
     * @throws Exception
     *
     * @return string|Date|DateTime|Duration|LocalDateTime|LocalTime|Time
     */
    public function translate(Iterator $meta, string $value)
    {
        $type = $meta->current()['type'] ?? null;
        $meta->next();

        switch ($type) {
            case 'duration':
                $tbr = $this->translateDuration($value);
                break;
            case 'datetime':
                $tbr = $this->translateDateTime($value);
                break;
            case 'date':
                $tbr = $this->translateDate($value);
                break;
            case 'time':
                $tbr = $this->translateTime($value);
                break;
            case 'localdatetime':
                $tbr = $this->translateLocalDateTime($value);
                break;
            case 'localtime':
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

    private function translateDate(string $value): Date
    {
        $epoch = new DateTimeImmutable('@0');
        /** @psalm-suppress PossiblyFalseReference */
        $diff = DateTimeImmutable::createFromFormat('Y-m-d', $value)->diff($epoch);

        return new Date((int) $diff->format('%a'));
    }

    private function translateTime(string $value): Time
    {
        $value = substr($value, 0, 5);
        $values = explode(':', $value);

        /** @psalm-suppress PossiblyUndefinedIntArrayOffset */
        return new Time(((int) $values[0]) * 60 * 60 + ((int) $values[1]) * 60);
    }

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

        if ($tz !== null) {
            /** @psalm-suppress PossiblyFalseReference */
            return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, $tz);
        }

        /** @psalm-suppress PossiblyFalseReference */
        return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, 0);
    }

    private function translateLocalDateTime(string $value): LocalDateTime
    {
        [$date, $time] = explode('T', $value);
        [$time, $milliseconds] = explode('.', $time);

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);

        /** @psalm-suppress PossiblyFalseReference */
        return new LocalDateTime($date->getTimestamp(), (int) $milliseconds * 1000000);
    }

    private function translateLocalTime(string $value): LocalTime
    {
        throw new BadMethodCallException('TODO'); //TODO
    }
}