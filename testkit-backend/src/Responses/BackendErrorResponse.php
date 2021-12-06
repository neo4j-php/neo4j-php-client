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

/**
 * Indicates an internal error has occurred.
 */
final class BackendErrorResponse implements TestkitResponseInterface
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'BackendError',
            'data' => [
                'msg' => $this->message,
            ],
        ];
    }
}
