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

namespace Laudis\Neo4j\Common;

use function extension_loaded;

use Laudis\Neo4j\Contracts\SemaphoreFactoryInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Psr\Http\Message\UriInterface;

final class SemaphoreFactory implements SemaphoreFactoryInterface
{
    private static ?SemaphoreFactory $instance = null;
    /** @var callable(string, int):SemaphoreInterface */
    private $constructor;

    /**
     * @param callable(string, int):SemaphoreInterface $constructor
     */
    private function __construct($constructor)
    {
        $this->constructor = $constructor;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= (extension_loaded('ext-sysvsem')) ?
            new self([SysVSemaphore::class, 'create']) :
            new self([SingleThreadedSemaphore::class, 'create']);
    }

    public function create(UriInterface $uri, DriverConfiguration $config): SemaphoreInterface
    {
        // Because interprocess switching of connections between PHP sessions is impossible,
        // we have to build a key to limit the amount of open connections, potentially between ALL sessions.
        // because of this we have to settle on a configuration basis to limit the connection pool,
        // not on an object basis.
        // The combination is between the server and the user agent as it most closely resembles an "application"
        // connecting to a server. The application thus supports multiple authentication methods, but they have
        // to be shared between the same connection pool.
        $key = $uri->getHost().':'.($uri->getPort() ?? '').':'.$config->getUserAgent();

        return ($this->constructor)($key, $config->getMaxPoolSize());
    }
}
