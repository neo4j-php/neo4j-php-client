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

use DateTimeImmutable;
use Exception;
use function sprintf;

final class LocalDateTime extends AbstractCypherContainer
{
    private int $seconds;
    private int $nanoseconds;

    public function __construct(int $seconds, int $nanoseconds)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

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

    public function getIterator()
    {
        yield 'seconds' => $this->seconds;
        yield 'nanoseconds' => $this->nanoseconds;
    }
}
