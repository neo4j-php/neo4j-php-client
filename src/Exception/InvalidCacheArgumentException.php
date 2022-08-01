<?php

namespace Laudis\Neo4j\Exception;

use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

class InvalidCacheArgumentException extends RuntimeException implements InvalidArgumentException
{

}
