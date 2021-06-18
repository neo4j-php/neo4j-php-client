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

final class Date extends AbstractCypherContainer
{
    private int $days;

    public function __construct(int $days)
    {
        $this->days = $days;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@0'))->modify(sprintf('+%s days', $this->days));
    }

    public function getIterator()
    {
        yield 'days' => $this->days;
    }
}
