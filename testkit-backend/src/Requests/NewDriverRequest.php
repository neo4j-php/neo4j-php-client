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

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Requests;

final class NewDriverRequest
{
    public function __construct(
        public readonly string $uri,
        public readonly AuthorizationTokenRequest $authToken,
        public readonly ?string $authTokenManagerId = null,
        public readonly ?string $userAgent = null,
        public readonly ?bool $resolverRegistered = null,
        public readonly ?bool $domainNameResolverRegistered = null,
        public readonly ?int $connectionTimeoutMs = null,
        public readonly ?int $fetchSize = null,
        public readonly ?int $maxTxRetryTimeMs = null,
        public readonly ?int $livenessCheckTimeoutMs = null,
        public readonly ?int $maxConnectionPoolSize = null,
        public readonly ?int $connectionAcquisitionTimeoutMs = null,
        public readonly mixed $clientCertificate = null,
        public readonly ?string $clientCertificateProviderId = null,
    ) {
    }
}
