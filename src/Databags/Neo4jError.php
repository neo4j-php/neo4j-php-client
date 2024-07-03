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

namespace Laudis\Neo4j\Databags;

use Bolt\protocol\Response;
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
    public function __construct(
        private readonly string $code,
        private readonly ?string $message,
        private readonly string $classification,
        private readonly string $category,
        private readonly string $title
    ) {}

    /**
     * @pure
     */
    public static function fromBoltResponse(Response $response): self
    {
        /**
         * @psalm-suppress ImpurePropertyFetch
         *
         * @var array{code: string, message:string} $content
         */
        $content = $response->content;

        return self::fromMessageAndCode($content['code'], $content['message']);
    }

    /**
     * @pure
     *
     * @throws InvalidArgumentException
     */
    public static function fromMessageAndCode(string $code, ?string $message): Neo4jError
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
    public function getMessage(): ?string
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
