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

namespace Laudis\Neo4j\Types;

use Bolt\protocol\IStructure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

use function sprintf;

use UnexpectedValueException;

/**
 * A date represented by seconds and nanoseconds since unix epoch, enriched with a timezone identifier.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int|string, int|string>
 *
 * @psalm-suppress TypeDoesNotContainType
 */
final class DateTimeZoneId extends AbstractPropertyObject implements BoltConvertibleInterface
{
    /**
     * @param non-empty-string $tzId
     */
    public function __construct(
        private readonly int $seconds,
        private readonly int $nanoseconds,
        private readonly string $tzId
    ) {}

    /**
     * Returns the amount of seconds since unix epoch.
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * Returns the amount of nanoseconds after the seconds have passed.
     */
    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * Returns the timezone identifier.
     */
    public function getTimezoneIdentifier(): string
    {
        return $this->tzId;
    }

    /**
     * Casts to an immutable date time.
     *
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        $dateTimeImmutable = (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
            ->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000));

        if ($dateTimeImmutable === false) {
            throw new UnexpectedValueException('Expected DateTimeImmutable');
        }

        return $dateTimeImmutable->setTimezone(new DateTimeZone($this->tzId));
    }

    /**
     * @return array{seconds: int, nanoseconds: int, tzId: string}
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'nanoseconds' => $this->nanoseconds,
            'tzId' => $this->tzId,
        ];
    }

    /**
     * @return CypherMap<string|int>
     */
    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        return new \Bolt\protocol\v1\structures\DateTimeZoneId($this->getSeconds(), $this->getNanoseconds(), $this->getTimezoneIdentifier());
    }
}
