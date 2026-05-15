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

use function is_string;

use Laudis\Neo4j\TestkitBackend\Requests\AuthorizationTokenRequest;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarkManagerCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksConsumerCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksSupplierCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\CheckMultiDBSupportRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DomainNameResolutionCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\DriverCloseRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ExecuteQueryRequest;
use Laudis\Neo4j\TestkitBackend\Requests\ForcedRoutingTableUpdateRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetFeaturesRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetRoutingTableRequest;
use Laudis\Neo4j\TestkitBackend\Requests\GetServerInfoRequest;
use Laudis\Neo4j\TestkitBackend\Requests\NewBookmarkManagerRequest;
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
use Traversable;

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
        'ExecuteQuery' => ExecuteQueryRequest::class,
    ];

    /**
     * @param iterable<array|scalar|null> $data
     */
    public function create(string $name, iterable $data): object
    {
        $assoc = $data instanceof Traversable ? iterator_to_array($data, false) : (array) $data;

        if ($name === 'NewSession') {
            return new NewSessionRequest(
                Uuid::fromString((string) $assoc['driverId']),
                (string) $assoc['accessMode'],
                $assoc['bookmarks'] ?? null,
                $assoc['database'] ?? null,
                $assoc['fetchSize'] ?? null,
                $assoc['impersonatedUser'] ?? null,
                array_key_exists('bookmarkManagerId', $assoc) ? Uuid::fromString((string) $assoc['bookmarkManagerId']) : null,
            );
        }

        if ($name === 'NewBookmarkManager') {
            return new NewBookmarkManagerRequest(
                $assoc['initialBookmarks'] ?? null,
                (bool) ($assoc['bookmarksSupplierRegistered'] ?? false),
                (bool) ($assoc['bookmarksConsumerRegistered'] ?? false),
            );
        }

        if ($name === 'BookmarkManagerClose') {
            return new BookmarkManagerCloseRequest(Uuid::fromString((string) $assoc['id']));
        }

        if ($name === 'BookmarksSupplierCompleted') {
            return new BookmarksSupplierCompletedRequest(
                Uuid::fromString((string) $assoc['requestId']),
                $assoc['bookmarks'] ?? [],
            );
        }

        if ($name === 'BookmarksConsumerCompleted') {
            return new BookmarksConsumerCompletedRequest(Uuid::fromString((string) $assoc['requestId']));
        }

        $class = self::MAPPINGS[$name];

        if ($name === 'AuthorizationToken') {
            return new AuthorizationTokenRequest(
                $assoc['scheme'],
                $assoc['realm'] ?? '',
                $assoc['principal'],
                $assoc['credentials']
            );
        }

        if ($name === 'NewDriver') {
            return new NewDriverRequest(
                uri: $data['uri'],
                authToken: $this->create('AuthorizationToken', $data['authorizationToken']['data']),
                authTokenManagerId: $data['authTokenManagerId'] ?? null,
                userAgent: $data['userAgent'] ?? null,
                resolverRegistered: $data['resolverRegistered'] ?? null,
                domainNameResolverRegistered: $data['domainNameResolverRegistered'] ?? null,
                connectionTimeoutMs: $data['connectionTimeoutMs'] ?? null,
                fetchSize: $data['fetchSize'] ?? null,
                maxTxRetryTimeMs: $data['maxTxRetryTimeMs'] ?? null,
                livenessCheckTimeoutMs: $data['livenessCheckTimeoutMs'] ?? null,
                maxConnectionPoolSize: $data['maxConnectionPoolSize'] ?? null,
                connectionAcquisitionTimeoutMs: $data['connectionAcquisitionTimeoutMs'] ?? null,
                clientCertificate: $data['clientCertificate'] ?? null,
                clientCertificateProviderId: $data['clientCertificateProviderId'] ?? null,
                telemetryDisabled: $data['telemetryDisabled'] ?? null,
            );
        }

        $params = [];
        foreach ($assoc as $value) {
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
