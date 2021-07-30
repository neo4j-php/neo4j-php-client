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

namespace Laudis\Neo4j\TestkitBackend;

use const AF_INET;
use const E_ALL;
use function error_reporting;
use function getenv;
use function is_numeric;
use function is_string;
use RuntimeException;
use const SOCK_STREAM;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_listen;
use function socket_strerror;
use const SOL_TCP;

final class Socket
{
    /** @var resource */
    private $baseSocket;
    /** @var resource|null */
    private $socket;

    /**
     * @param resource $baseSocket
     * @param resource $socket
     */
    public function __construct($baseSocket)
    {
        $this->baseSocket = $baseSocket;
    }

    public static function fromEnvironment(): self
    {
        $address = self::loadAddress();
        $port = self::loadPort();

        return self::fromAddressAndPort($address, $port);
    }

    private static function loadAddress(): string
    {
        $address = getenv('TESTKIT_BACKEND_ADDRESS');
        if (!is_string($address)) {
            $address = '127.0.0.1';
        }

        return $address;
    }

    private static function loadPort(): int
    {
        $port = getenv('TESTKIT_BACKEND_PORT');
        if (!is_numeric($port)) {
            $port = 9876;
        }

        return (int) $port;
    }

    public static function fromAddressAndPort(string $address, int $port): self
    {
        $baseSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($baseSocket === false) {
            throw new RuntimeException('socket_create() failed: reason: '.socket_strerror(socket_last_error()));
        }

        if (!socket_bind($baseSocket, $address, $port)) {
            throw new RuntimeException('socket_bind() failed: reason: '.socket_strerror(socket_last_error($baseSocket)));
        }

        if (!socket_listen($baseSocket, 5)) {
            throw new RuntimeException('socket_listen() failed: reason: '.socket_strerror(socket_last_error($baseSocket)));
        }

        return new self($baseSocket);
    }

    public function reset(): void
    {
        if ($this->socket !== null) {
            socket_close($this->socket);
        }

        $this->socket = null;
    }

    public function read(int $length = 2048): string
    {
        if ($this->socket === null) {
            $socket = socket_accept($this->baseSocket);
            if ($socket === false) {
                throw new RuntimeException('socket_accept() failed: reason: '.socket_strerror(socket_last_error($this->baseSocket)));
            }

            $this->socket = $socket;
        }

        $buffer = socket_read($this->socket, $length, PHP_NORMAL_READ);
        if ($buffer === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new RuntimeException('socket_read() failed: reason: '.$error);
        }

        return $buffer;
    }

    public function write(string $message): void
    {
        $result = socket_write($this->socket, $message, mb_strlen($message));
        if ($result === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new RuntimeException('socket_write() failed: reason: '.$error);
        }
    }

    public static function setupEnvironment(): void
    {
//        error_reporting(E_ALL);
        // Allow the script to hang around waiting for connections.
        set_time_limit(0);
        // Turn on implicit output flushing so we see what we're getting as it comes in.
        ob_implicit_flush();
    }

    public function __destruct()
    {
        $this->reset();
        socket_close($this->baseSocket);
    }
}
