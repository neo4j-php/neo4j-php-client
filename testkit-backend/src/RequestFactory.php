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

namespace Laudis\Neo4j\TestkitBackend;

use Laudis\Neo4j\TestkitBackend\Requests\ExecuteQueryRequest;
use function is_string;

use Laudis\Neo4j\TestkitBackend\Requests\AuthorizationTokenRequest;
use Laudis\Neo4j\TestkitBackend\Requests\CheckMultiDBSupportRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DomainNameResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DriverCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ForcedRoutingTableUpdateRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetFeaturesRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetRoutingTableRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetServerInfoRequest;
use Laudis\Neo4j\TestkitBackend\Requests\NewDriverRequest;
use Laudis\Neo4j\TestkitBackend\Requests\NewSessionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResolverResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultConsumeRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultListRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultNextRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultPeekRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultSingleOptionalRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ResultSingleRequest;
use Laudis\Neo4j\TestkitBackend\Requests\RetryableNegativeRequest;
use Laudis\Neo4j\TestkitBackend\Requests\RetryablePositiveRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionBeginTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionLastBookmarksRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionReadTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Requests\SessionWriteTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Requests\StartTestRequest;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionCloseRequest;
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
        'TransactionClose' => TransactionCloseRequest::class,
        'ResultNext' => ResultNextRequest::class,
        'ResultSingle' => ResultSingleRequest::class,
        'ResultList' => ResultListRequest::class,
        'ResultPeek' => ResultPeekRequest::class,
        'ResultSingleOptional' => ResultSingleOptionalRequest::class,
        'ResultConsume' => ResultConsumeRequest::class,
        'RetryablePositive' => RetryablePositiveRequest::class,
        'RetryableNegative' => RetryableNegativeRequest::class,
        'ForcedRoutingTableUpdate' => ForcedRoutingTableUpdateRequest::class,
        'GetRoutingTable' => GetRoutingTableRequest::class,
        'GetServerInfo' => GetServerInfoRequest::class,
    ];

    /**
     * @param iterable<array|scalar|null> $data
     */
    public function create(string $name, iterable $data): object
    {
        $class = self::MAPPINGS[$name];

        if ($name === 'AuthorizationToken') {
            return new AuthorizationTokenRequest(
                $data['scheme'],
                $data['realm'] ?? '',
                $data['principal'],
                $data['credentials']
            );
        }

        if ($name === 'NewDriver') {
            $authToken = new AuthorizationTokenRequest('basic', '', '', '');
            if (array_key_exists('authorizationToken', $data) && is_array($data['authorizationToken'])) {
                $tokenData = $data['authorizationToken'];
                if (isset($tokenData['name'], $tokenData['data'])) {
                    /** @var AuthorizationTokenRequest $authToken */
                    $authToken = $this->create($tokenData['name'], $tokenData['data']);
                } else {
                    $authToken = $this->create('AuthorizationToken', $tokenData);
                }
            }

            return new NewDriverRequest(
                $data['uri'],
                $authToken,
                $data['authTokenManagerId'] ?? null,
                $data['userAgent'] ?? null,
                $data['resolverRegistered'] ?? null,
                $data['domainNameResolverRegistered'] ?? null,
                $data['connectionTimeoutMs'] ?? null,
                $data['fetchSize'] ?? null,
                $data['maxTxRetryTimeMs'] ?? null,
                $data['livenessCheckTimeoutMs'] ?? null,
                $data['maxConnectionPoolSize'] ?? null,
                $data['connectionAcquisitionTimeoutMs'] ?? null,
                $data['clientCertificate'] ?? null,
                $data['clientCertificateProviderId'] ?? null,
                $data['telemetryDisabled'] ?? null,
            );
        }

        if ($name === 'ExecuteQuery') {
            $driverId = $data['driverId'] ?? $data[0] ?? null;
            if (is_string($driverId)) {
                $driverId = Uuid::fromString($driverId);
            }

            return new ExecuteQueryRequest(
                $driverId,
                $data['cypher'] ?? $data[1] ?? '',
                $data['params'] ?? $data[2] ?? null,
                $data['config'] ?? $data[3] ?? null,
            );
        }

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
