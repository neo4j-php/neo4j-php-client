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

namespace Laudis\Neo4j\Exception;

use Laudis\Neo4j\Databags\Neo4jError;
use RuntimeException;
use Throwable;

/**
 * Exception when a Neo4j Error occurs.
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class Neo4jException extends RuntimeException
{
    private const MESSAGE_TEMPLATE = 'Neo4j errors detected. First one with code "%s" and message "%s"';
    /** @var non-empty-list<Neo4jError> */
    private array $errors;

    /**
     * @param non-empty-list<Neo4jError> $errors
     */
    public function __construct(array $errors, Throwable $previous = null)
    {
        $error = $errors[0];
        $message = sprintf(self::MESSAGE_TEMPLATE, $error->getCode(), $error->getMessage());
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    /**
     * @return non-empty-list<Neo4jError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getNeo4jCode(): string
    {
        return $this->errors[0]->getCode();
    }
}
