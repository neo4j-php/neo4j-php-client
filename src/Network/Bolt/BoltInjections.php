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

namespace Laudis\Neo4j\Network\Bolt;

/**
 * @psalm-type SSLContextOptions = null|array{
 *     peer_name?: string,
 *     verify_peer?: bool,
 *     verify_peer_name?: bool,
 *     allow_self_signed?: bool,
 *     cafile?: string,
 *     capath?: string,
 *     local_cert?: string,
 *     local_pk?: string,
 *     passphrase?: string,
 *     verify_depth?: int,
 *     ciphers?: string,
 *     capture_peer_cert?: bool,
 *     capture_peer_cert_chain?: bool,
 *     SNI_enabled?: bool,
 *     disable_compression?: bool,
 *     peer_fingerprint?: string|array,
 *     security_level?: int
 * }
 *
 * @psalm-type LazySSLContextOptions = callable():SSLContextOptions|SSLContextOptions
 */
final class BoltInjections
{
    /** @var callable():string|string */
    private $database;
    /** @var LazySSLContextOptions */
    private $sslContextOptions;

    /**
     * @param callable():string|?string $database
     * @param LazySSLContextOptions     $sslContextOptions
     */
    public function __construct($database = null, $sslContextOptions = null)
    {
        $this->database = $database ?? static function (): string { return 'neo4j'; };
        $this->sslContextOptions = $sslContextOptions;
    }

    /**
     * @param SSLContextOptions|null $sslContextOptions
     *
     * @see https://www.php.net/manual/en/context.ssl.php for ssl connections
     *
     * @return static
     */
    public static function create(?string $database = null, ?array $sslContextOptions = null): self
    {
        return new self($database, $sslContextOptions);
    }

    /**
     * @param string|callable():string $database
     */
    public function withDatabase($database): self
    {
        return new self($database);
    }

    /**
     * @param LazySSLContextOptions $options
     */
    public function withSslContextOptions($options): self
    {
        return new self($this->database, $options);
    }

    public function database(): string
    {
        if (is_callable($this->database)) {
            $this->database = call_user_func($this->database);
        }

        return $this->database;
    }

    /**
     * @return SSLContextOptions
     */
    public function sslContextOptions(): ?array
    {
        if (is_callable($this->sslContextOptions)) {
            /** @psalm-suppress PossiblyInvalidFunctionCall */
            $this->sslContextOptions = call_user_func($this->sslContextOptions);
        }

        return $this->sslContextOptions;
    }
}
