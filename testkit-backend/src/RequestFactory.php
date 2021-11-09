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

use function is_string;
use Laudis\Neo4j\TestkitBackend\Requests\AuthorizationTokenRequest;
use Laudis\Neo4j\TestkitBackend\Requests\CheckMultiDBSupportRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DomainNameResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DriverCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ForcedRoutingTableUpdateRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetFeaturesRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetRoutingTableRequest;
use Laudis\Neo4j\TestkitBackend\Requests\NewDriverRequest;
use Laudis\Neo4j\TestkitBackend\Requests\NewSessionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResolverResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultConsumeRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultNextRequest;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Requests\RetryablePositiveRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionBeginTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionLastBookmarksRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionReadTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionWriteTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\StartTestRequest;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionCommitRequest;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionRollbackRequest;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest;
use Laudis\Neo4j\TestkitBackend\Requests\VerifyConnectivityRequest;
use Symfony\Component\Uid\Uuid;

final class RequestFactory
{
    private const MAPPINGS = [
        'StartTest' => StartTestRequest::class,
        'GetFeatures' => GetFeaturesRequest::class,
        'NewDriver' => NewDriverRequest::class,
        'AuthorizationToken' => AuthorizationTokenRequest::class,
        'VerifyConnectivity' => VerifyConnectivityRequest::class,
        'CheckMultiDBSupport' => CheckMultiDBSupportRequest::class,
        'ResolverResolutionCompleted' => ResolverResolutionCompletedRequest::class,
        'DomainNameResolutionCompleted' => DomainNameResolutionCompletedRequest::class,
        'DriverClose' => DriverCloseRequest::class,
        'NewSession' => NewSessionRequest::class,
        'SessionClose' => SessionCloseRequest::class,
        'SessionRun' => SessionRunRequest::class,
        'SessionReadTransaction' => SessionReadTransactionRequest::class,
        'SessionWriteTransaction' => SessionWriteTransactionRequest::class,
        'SessionBeginTransaction' => SessionBeginTransactionRequest::class,
        'SessionLastBookmarks' => SessionLastBookmarksRequest::class,
        'TransactionRun' => TransactionRunRequest::class,
        'TransactionCommit' => TransactionCommitRequest::class,
        'TransactionRollback' => TransactionRollbackRequest::class,
        'ResultNext' => ResultNextRequest::class,
        'ResultConsume' => ResultConsumeRequest::class,
        'RetryablePositive' => RetryablePositiveRequest::class,
        'RetryableNegative' => RetryableNegativeRequest::class,
        'ForcedRoutingTableUpdate' => ForcedRoutingTableUpdateRequest::class,
        'GetRoutingTable' => GetRoutingTableRequest::class,
    ];

    /**
     * @param iterable<array|scalar|null> $data
     */
    public function create(string $name, iterable $data): object
    {
        $class = self::MAPPINGS[$name];

        $params = [];
        foreach ($data as $value) {
            if (is_array($value) && isset($value['name'], $value['data'])) {
                /** @psalm-suppress MixedArgument */
                $params[] = $this->create($value['name'], $value['data']);
            } elseif (is_string($value) && Uuid::isValid($value)) {
                $params[] = Uuid::fromString($value);
            } else {
                $params[] = $value;
            }
        }

        return new $class(...$params);
    }
}
