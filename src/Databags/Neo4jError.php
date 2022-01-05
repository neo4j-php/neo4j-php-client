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

use Bolt\error\MessageException;
use InvalidArgumentException;

/**
 * Contains the code and message of an error in a neo4j database.
 *
 * @psalm-immutable
 *
 * @see https://neo4j.com/docs/status-codes/current/
 */
final class Neo4jError
{
    private string $code;
    private string $message;
    private string $classification;
    private string $category;
    private string $title;

    public function __construct(string $code, string $message, string $classification, string $category, string $title)
    {
        $this->code = $code;
        $this->message = $message;
        $this->classification = $classification;
        $this->category = $category;
        $this->title = $title;
    }

    /**
     * @pure
     */
    public static function fromMessageException(MessageException $e): self
    {
        return self::fromMessageAndCode($e->getServerCode(), $e->getServerMessage());
    }

    /**
     * @pure
     *
     * @throws InvalidArgumentException
     */
    public static function fromMessageAndCode(string $code, string $message): Neo4jError
    {
        $parts = explode('.', $code, 4);
        if (count($parts) < 4) {
            throw new InvalidArgumentException('Invalid message exception code');
        }

        return new self($code, $message, $parts[1], $parts[2], $parts[3]);
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

    public function getClassification(): string
    {
        return $this->classification;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
