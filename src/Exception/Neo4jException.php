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

use Ds\Vector;
use Laudis\Neo4j\Databags\Neo4jError;
use RuntimeException;
use Throwable;

final class Neo4jException extends RuntimeException
{
    private const MESSAGE_TEMPLATE = 'Neo4j errors detected. First one with code "%s" and message "%s"';
    /** @var Vector<Neo4jError> */
    private Vector $errors;

    /**
     * @param Vector<Neo4jError> $errors
     */
    public function __construct(Vector $errors, Throwable $previous = null)
    {
        $error = $errors->first();
        $message = sprintf(self::MESSAGE_TEMPLATE, $error->getCode(), $error->getMessage());
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    /**
     * @return Vector<Neo4jError>
     */
    public function getErrors(): Vector
    {
        return $this->errors;
    }
}
