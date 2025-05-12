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

final class AuthorizationTokenRequest
{
    public function __construct(
        public readonly string $scheme,
        public readonly string $realm,
        public readonly string $principal,
        public readonly string $credentials,
    ) {
    }
}
