<?php
declare(strict_types=1);

namespace Laudis\Neo4j\Exception;

use RuntimeException;
use Throwable;
use function get_debug_type;
use function gettype;

final class RuntimeTypeException extends RuntimeException
{
    /**
     * @param mixed $value
     */
    public function __construct($value, string $type)
    {
        $actualType = get_debug_type($value);
        $message = sprintf('Cannot cast %s to type: %s', $actualType, $type);
        parent::__construct($message);
    }
}
