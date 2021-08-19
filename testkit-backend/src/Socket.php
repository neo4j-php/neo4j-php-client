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

use function error_get_last;
use function getenv;
use function is_numeric;
use function is_string;
use function json_encode;
use const PHP_EOL;
use RuntimeException;
use function stream_get_line;
use const STREAM_SHUT_RDWR;
use function stream_socket_accept;
use function stream_socket_server;
use function stream_socket_shutdown;

final class Socket
{
    /** @var resource */
    private $streamSocketServer;
    /** @var resource|null */
    private $socket;

    /**
     * @param resource $streamSocketServer
     */
    public function __construct($streamSocketServer)
    {
        $this->streamSocketServer = $streamSocketServer;
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
            $address = '0.0.0.0';
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
        $bind = 'tcp://'.$address.':'.$port;
        $streamSocketServer = stream_socket_server($bind, $errorNumber, $errorString);
        if ($streamSocketServer === false) {
            throw new RuntimeException('stream_socket_server() failed: reason: '.$errorNumber.':'.$errorString);
        }

//        stream_set_blocking($streamSocketServer, false);

        return new self($streamSocketServer);
    }

    public function reset(): void
    {
        if ($this->socket !== null && !stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR)) {
            throw new RuntimeException(json_encode(error_get_last(), JSON_THROW_ON_ERROR));
        }

        $this->socket = null;
    }

    public function readMessage(): ?string
    {
        if ($this->socket === null) {
            $socket = stream_socket_accept($this->streamSocketServer, 2 ** 20);
            if ($socket === false) {
                throw new RuntimeException(json_encode(error_get_last(), JSON_THROW_ON_ERROR));
            }

            $this->socket = $socket;
        }

        $length = 2 ** 30;
        $this->getLine($this->socket, $length);
        $message = $this->getLine($this->socket, $length);
        $this->getLine($this->socket, $length);

        return $message;
    }

    public function write(string $message): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('Trying to write to an uninitialised socket');
        }

        $result = stream_socket_sendto($this->socket, $message);
        if ($result === -1) {
            throw new RuntimeException(json_encode(error_get_last() ?? 'Unknown error', JSON_THROW_ON_ERROR));
        }
    }

    public static function setupEnvironment(): void
    {
        error_reporting(E_ALL);
        // Allow the script to hang around waiting for connections.
        set_time_limit(0);
    }

    public function __destruct()
    {
        $this->reset();
        if (!stream_socket_shutdown($this->streamSocketServer, STREAM_SHUT_RDWR)) {
            throw new RuntimeException(json_encode(error_get_last(), JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @param resource $socket
     */
    private function getLine($socket, int $length): ?string
    {
        $line = stream_get_line($socket, $length, PHP_EOL);
        if ($line === false) {
            return null;
        }

        return $line;
    }
}
