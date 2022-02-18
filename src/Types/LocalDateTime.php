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

namespace Laudis\Neo4j\Types;

use Bolt\structures\IStructure;
use DateTimeImmutable;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use function sprintf;

/**
 * A date time represented in seconds and nanoseconds since the unix epoch.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 */
final class LocalDateTime extends AbstractPropertyObject implements BoltConvertibleInterface
{
    private int $seconds;
    private int $nanoseconds;

    public function __construct(int $seconds, int $nanoseconds)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
    }

    /**
     * The amount of seconds since the unix epoch.
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * The amount of nanoseconds after the seconds have passed.
     */
    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        return (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
                    ->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000));
    }

    /**
     * @return array{seconds: int, nanoseconds: int}
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'nanoseconds' => $this->nanoseconds,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        return new \Bolt\structures\LocalDateTime($this->getSeconds(), $this->getNanoseconds());
    }
}
