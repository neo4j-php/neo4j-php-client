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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Base class for all kind of driver errors that is NOT a backend specific error.
 */
final class DriverErrorResponse implements TestkitResponseInterface
{
    private Uuid $id;
    private Neo4jException $exception;

    public function __construct(Uuid $id, Neo4jException $exception)
    {
        $this->id = $id;
        $this->exception = $exception;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'DriverError',
            'data' => [
                'id' => $this->id->toRfc4122(),
                'code' => $this->exception->getNeo4jCode(),
                'msg' => $this->exception->getNeo4jMessage(),
            ],
        ];
    }
}
