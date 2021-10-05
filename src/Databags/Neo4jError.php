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

namespace Laudis\Neo4j\Databags;

/**
 * Contains the code and message of an error in a neo4j database.
 *
 * @psalm-immutable
 */
final class Neo4jError
{
    private string $code;
    private string $message;

    public function __construct(string $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * Returns the code of the error.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Returns the message of the error.
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
