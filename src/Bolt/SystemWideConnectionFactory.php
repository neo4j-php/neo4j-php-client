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

namespace Laudis\Neo4j\Bolt;

use function extension_loaded;

use Laudis\Neo4j\Contracts\BasicConnectionFactoryInterface;

/**
 * Singleton connection factory based on the installed extensions.
 */
class SystemWideConnectionFactory implements BasicConnectionFactoryInterface
{
    private static ?SystemWideConnectionFactory $instance = null;

    /**
     * @param SocketConnectionFactory|StreamConnectionFactory $factory
     */
    private function __construct(
        private $factory
    ) {}

    /**
     * @psalm-suppress InvalidNullableReturnType
     */
    public static function getInstance(): SystemWideConnectionFactory
    {
        if (self::$instance === null) {
            $factory = new StreamConnectionFactory();
            if (extension_loaded('sockets')) {
                self::$instance = new self(new SocketConnectionFactory($factory));
            } else {
                self::$instance = new self($factory);
            }
        }

        /** @psalm-suppress NullableReturnStatement */
        return self::$instance;
    }

    public function create(UriConfiguration $config): Connection
    {
        return $this->factory->create($config);
    }
}
