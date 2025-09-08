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

namespace Laudis\Neo4j\TestkitBackend\Requests;

/**
 * Represents a client certificate for mutual TLS authentication.
 *
 * This follows the same pattern as the official Neo4j Python and Java drivers:
 * - certfile: Path to the client certificate file (PEM format)
 * - keyfile: Path to the private key file (PEM format, PKCS#8)
 * - password: Optional password for the private key
 */
class ClientCertificateRequest
{
    public function __construct(
        public readonly string $certfile,
        public readonly string $keyfile,
        public readonly ?string $password = null,
    ) {
    }
}
