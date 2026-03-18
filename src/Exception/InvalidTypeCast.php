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

namespace Laudis\Neo4j\Exception;

use function get_debug_type;
use function preg_match;

use RuntimeException;

final class InvalidTypeCast extends RuntimeException
{
    public function __construct(
        mixed $value,
        private readonly string $targetType,
    ) {
        $receivedType = get_debug_type($value);
        $message = sprintf(
            'Cannot cast %s to %s',
            $receivedType,
            $targetType
        );
        parent::__construct($message);
    }

    public function getReceivedType(): string
    {
        if (preg_match('/^Cannot cast (.+?) to /', $this->getMessage(), $m) && array_key_exists(1, $m)) {
            return $m[1];
        }

        return 'unknown';
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }
}
