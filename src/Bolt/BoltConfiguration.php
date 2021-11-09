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

namespace Laudis\Neo4j\Bolt;

use function call_user_func;
use function is_callable;
use function is_string;
use Laudis\Neo4j\Client;
use Laudis\Neo4j\Contracts\ConfigInterface;

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
 * @psalm-suppress DeprecatedInterface
 *
 * @deprecated
 * @see Client::withDriver()
 */
final class BoltConfiguration implements ConfigInterface
{
    /** @var callable():string|string */
    private $database;
    /** @var LazySSLContextOptions */
    private $sslContextOptions;
    /** @var callable():bool|bool */
    private $autoRouting;

    /**
     * @psalm-mutation-free
     *
     * @param callable():string|?string $database
     * @param LazySSLContextOptions     $sslContextOptions
     * @param callable():bool|bool      $autoRouting
     */
    public function __construct($database = null, $sslContextOptions = null, $autoRouting = false)
    {
        $this->database = $database ?? static function (): string { return 'neo4j'; };
        $this->sslContextOptions = $sslContextOptions;
        $this->autoRouting = $autoRouting;
    }

    /**
     * @param SSLContextOptions|null $sslContextOptions
     *
     * @see https://www.php.net/manual/en/context.ssl.php for ssl connections
     *
     * @pure
     *
     * @return static
     */
    public static function create(?string $database = null, ?array $sslContextOptions = null, bool $autoRouting = false): self
    {
        return new self($database, $sslContextOptions, $autoRouting);
    }

    /**
     * @param string|callable():string $database
     */
    public function withDatabase($database): self
    {
        return new self($database, $this->sslContextOptions, $this->autoRouting);
    }

    /**
     * @param LazySSLContextOptions $options
     */
    public function withSslContextOptions($options): self
    {
        return new self($this->database, $options, $this->autoRouting);
    }

    /**
     * @param callable():bool|bool $routing
     *
     * @return $this
     */
    public function withAutoRouting($routing): self
    {
        return new self($this->database, $this->sslContextOptions, $routing);
    }

    public function getDatabase(): string
    {
        if (is_string($this->database)) {
            return $this->database;
        }

        /** @var string */
        $this->database = call_user_func($this->database);

        return $this->database;
    }

    public function hasAutoRouting(): bool
    {
        if (is_callable($this->autoRouting)) {
            $this->autoRouting = call_user_func($this->autoRouting);
        }

        return $this->autoRouting;
    }

    /**
     * @return SSLContextOptions
     */
    public function getSslContextOptions(): ?array
    {
        if (is_callable($this->sslContextOptions)) {
            $this->sslContextOptions = call_user_func($this->sslContextOptions);
        }

        return $this->sslContextOptions;
    }

    /**
     * @psalm-suppress DeprecatedClass
     */
    public function mergeConfig(ConfigInterface $config): ConfigInterface
    {
        return new self($config->getDatabase(), $this->sslContextOptions, $config->hasAutoRouting());
    }
}
