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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Base class for all kind of driver errors that is NOT a backend specific error.
 */
final class DriverErrorResponse implements TestkitResponseInterface
{
    public function __construct(private Uuid $id, private string $errorType, private ?string $message)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'DriverError',
            'data' => [
                'id' => $this->id->toRfc4122(),
                'errorType' => $this->errorType,
                'msg' => $this->message,
            ],
        ];
    }
}
