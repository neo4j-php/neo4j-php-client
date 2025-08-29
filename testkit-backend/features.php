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

return [
    'Feature:API:SSLSchemes' => true,
    // === FUNCTIONAL FEATURES ===
    // Driver supports the Bookmark Manager Feature
    'Feature:API:BookmarkManager' => true,
    // The driver offers a configuration option to limit time it spends at most,
    // trying to acquire a connection from the pool.
    'Feature:API:ConnectionAcquisitionTimeout' => true,
    // The driver offers a method to run a query in a retryable context at the
    // driver object level.
    'Feature:API:Driver.ExecuteQuery' => true,
    // The driver allows users to specify a session scoped auth token when
    // invoking driver.executeQuery.
    'Feature:API:Driver.ExecuteQuery:WithAuth' => true,
    // The driver offers a method for checking if a connection to the remote
    // server of cluster can be established and retrieve the server info of the
    // reached remote.
    'Feature:API:Driver:GetServerInfo' => true,
    // The driver offers a method for driver objects to report if they were
    // configured with a or without encryption.
    'Feature:API:Driver.IsEncrypted' => true,
    // The driver supports setting a custom max connection lifetime
    'Feature:API:Driver:MaxConnectionLifetime' => true,
    // The driver supports notification filters configuration.
    'Feature:API:Driver:NotificationsConfig' => true,
    // The driver offers a method for checking if the provided authentication
    // information is accepted by the server.
    'Feature:API:Driver.VerifyAuthentication' => true,
    // The driver offers a method for checking if a connection to the remote
    // server of cluster can be established.
    'Feature:API:Driver.VerifyConnectivity' => true,
    // The driver offers a method for checking if a protocol version negotiated
    // with the remote supports re-authentication.
    'Feature:API:Driver.SupportsSessionAuth' => true,
    // The driver supports connection liveness check.
    'Feature:API:Liveness.Check' => true,
    // The driver offers a method for the result to return all records as a list
    // or array. This method should exhaust the result.
    'Feature:API:Result.List' => true,
    // The driver offers a method for the result to peek at the next record in
    // the result stream without advancing it (i.e. without consuming any
    // records)
    'Feature:API:Result.Peek' => true,
    // The driver offers a method for the result to retrieve exactly one record.
    // This method asserts that exactly one record in left in the result
    // stream, else it will raise an exception.
    'Feature:API:Result.Single' => true,
    // The driver offers a method for the result to retrieve the next record in
    // the result stream. If there are no more records left in the result, the
    // driver will indicate so by returning None/null/nil/any other empty value.
    // If there are more than records, the driver emits a warning.
    // This method is supposed to always exhaust the result stream.
    'Feature:API:Result.SingleOptional' => true,
    // The driver offers a way to determine if exceptions are retryable or not.
    'Feature:API:RetryableExceptions' => true,
    // The session configuration allows to switch the authentication context
    // by supplying new credentials. This new context is only valid for the
    // current session.
    'Feature:API:Session:AuthConfig' => true,
    // The session supports notification filters configuration.
    'Feature:API:Session:NotificationsConfig' => true,
    // The driver implements configuration for client certificates.
    'Feature:API:SSLClientCertificate' => true,
    // The driver implements explicit configuration options for SSL.
    //  - enable / disable SSL
    //  - verify signature against system store / custom cert / not at all
    'Feature:API:SSLConfig' => true,
    // The result summary provides a way to access the transaction's
    // GqlStatusObject.
    'Feature:API:Summary:GqlStatusObjects' => false,
    // The driver supports sending and receiving geospatial data types.
    'Feature:API:Type.Spatial' => true,
    // The driver supports sending and receiving temporal data types.
    'Feature:API:Type.Temporal' => true,
    // The driver supports single-sign-on (SSO) by providing a bearer auth token
    // API.
    'Feature:Auth:Bearer' => true,
    // The driver supports custom authentication by providing a dedicated auth
    // token API.
    'Feature:Auth:Custom' => true,
    // The driver supports Kerberos authentication by providing a dedicated auth
    // token API.
    'Feature:Auth:Kerberos' => true,
    // The driver supports an auth token manager or similar mechanism for the
    // user to provide (potentially changing) auth tokens and a way to get
    // notified when the server reports a token expired.
    'Feature:Auth:Managed' => false,
    // The driver supports Bolt protocol version 3
    'Feature:Bolt:3.0' => true,
    // The driver supports Bolt protocol version 4.1
    'Feature:Bolt:4.1' => true,
    // The driver supports Bolt protocol version 4.2
    'Feature:Bolt:4.2' => true,
    // The driver supports Bolt protocol version 4.3
    'Feature:Bolt:4.3' => true,
    // The driver supports Bolt protocol version 4.4
    'Feature:Bolt:4.4' => true,
    // The driver supports Bolt protocol version 5.0
    'Feature:Bolt:5.0' => true,
    // The driver supports Bolt protocol version 5.1
    'Feature:Bolt:5.1' => true,
    // The driver supports Bolt protocol version 5.2
    'Feature:Bolt:5.2' => true,
    // The driver supports Bolt protocol version 5.3
    'Feature:Bolt:5.3' => true,
    // The driver supports Bolt protocol version 5.4
    'Feature:Bolt:5.4' => true,
    // The driver supports Bolt protocol version 5.5, support dropped due
    // to a bug in the spec
    'Feature:Bolt:5.5' => false,
    // The driver supports Bolt protocol version 5.6
    'Feature:Bolt:5.6' => false,
    // The driver supports Bolt protocol version 5.7
    'Feature:Bolt:5.7' => true,
    // The driver supports Bolt protocol version 5.8
    'Feature:Bolt:5.8' => true,
    // The driver supports negotiating the Bolt protocol version with the server
    // using handshake manifest v1.
    'Feature:Bolt:HandshakeManifestV1' => true,
    // The driver supports patching DateTimes to use UTC for Bolt 4.3 and 4.4
    'Feature:Bolt:Patch:UTC' => true,
    // The driver supports impersonation
    'Feature:Impersonation' => true,
    // The driver supports TLS 1.1 connections.
    // If this flag is missing, TestKit assumes that attempting to establish
    // such a connection fails.
    'Feature:TLS:1.1' => true,
    // The driver supports TLS 1.2 connections.
    // If this flag is missing, TestKit assumes that attempting to establish
    // such a connection fails.
    'Feature:TLS:1.2' => true,
    // The driver supports TLS 1.3 connections.
    // If this flag is missing, TestKit assumes that attempting to establish
    // such a connection fails.
    'Feature:TLS:1.3' => true,

    // === OPTIMIZATIONS ===
    // On receiving Neo.ClientError.Security.AuthorizationExpired, the driver
    // shouldn't reuse any open connections for anything other than finishing
    // a started job. All other connections should be re-established before
    // running the next job with them.
    'AuthorizationExpiredTreatment' => true,
    // (Bolt 5.1+) The driver doesn't wait for a SUCCESS after HELLO but
    // pipelines a LOGIN right afterwards and consumes two messages after.
    // Likewise, doesn't wait for a SUCCESS after LOGOFF and the following
    // LOGON but pipelines it with the next message and consumes all three
    // responses at once.
    // Each saves a full round-trip.
    'Optimization:AuthPipelining' => true,
    // The driver caches connections (e.g., in a pool) and doesn't start a new
    // one (with hand-shake, HELLO, etc.) for each query.
    'Optimization:ConnectionReuse' => true,
    // The driver first tries to SUCCESSfully BEGIN a transaction before calling
    // the user-defined transaction function. This way, the (potentially costly)
    // transaction function is not started until a working transaction has been
    // established.
    'Optimization:EagerTransactionBegin' => true,
    // For the executeQuery API, the driver doesn't wait for a SUCCESS after
    // sending BEGIN but pipelines the RUN and PULL right afterwards and
    // consumes three messages after that. This saves 2 full round-trips.
    'Optimization:ExecuteQueryPipelining' => true,
    // The driver implements a cache to match users to their most recently
    // resolved home database, routing requests with no set database to this
    // cached database if all open connections have an SSR connection hint.
    'Optimization:HomeDatabaseCache' => true,
    // The home db cache for optimistic home db resolution treats the principal
    // in basic auth the exact same way it treats impersonated users.
    'Optimization:HomeDbCacheBasicPrincipalIsImpersonatedUser' => true,
    // Driver doesn't explicitly send message data that is the default value.
    // This conserves bandwidth.
    'Optimization:ImplicitDefaultArguments' => true,
    // Driver should not send duplicated bookmarks to the server
    'Optimization:MinimalBookmarksSet' => true,
    // The driver sends no more than the strictly necessary RESET messages.
    'Optimization:MinimalResets' => true,
    // The driver's VerifyAuthentication method is optimized. It
    // * reuses connections from the pool
    // * only issues a single LOGOFF/LOGON cycle
    // * doesn't issue the cycle for newly established connections
    'Optimization:MinimalVerifyAuthentication' => true,
    // The driver doesn't wait for a SUCCESS after calling RUN but pipelines a
    // PULL right afterwards and consumes two messages after that. This saves a
    // full round-trip.
    'Optimization:PullPipelining' => true,
    // This feature requires `API_RESULT_LIST`.
    // The driver pulls all records (`PULL -1`) when Result.list() is called.
    // (As opposed to iterating over the Result with the configured fetch size.)
    // Note: If your driver supports this, make sure to document well that this
    //       method ignores the configures fetch size. Your users will
    //       appreciate it <3.
    'Optimization:ResultListFetchAll' => true,

    // === IMPLEMENTATION DETAILS ===
    // `Driver.IsEncrypted` can also be called on closed drivers.
    'Detail:ClosedDriverIsEncrypted' => true,
    // Security configuration options for encryption and certificates are
    // compared based on their value and might still match the default
    // configuration as long as values match.
    'Detail:DefaultSecurityConfigValueEquality' => true,
    // The driver cannot differentiate between integer and float numbers.
    // I.e., JavaScript :P
    'Detail:NumberIsNumber' => true,

    // === CONFIGURATION HINTS (BOLT 4.3+) ===
    // The driver understands and follow the connection hint
    // connection.recv_timeout_seconds which tells it to close the connection
    // after not receiving an answer on any request for longer than the given
    // time period. On timout, the driver should remove the server from its
    // routing table and assume all other connections to the server are dead
    // as well.
    'ConfHint:connection.recv_timeout_seconds' => true,

    // === BACKEND FEATURES FOR TESTING ===
    // The backend understands the FakeTimeInstall, FakeTimeUninstall and
    // FakeTimeTick protocol messages and provides a way to mock the system
    // time. This is mainly used for testing various timeouts.
    'Backend:MockTime' => true,
    // The backend understands the GetRoutingTable protocol message and provides
    // a way for TestKit to request the routing table (for testing only, should
    // not be exposed to the user).
    'Backend:RTFetch' => true,
    // The backend understands the ForcedRoutingTableUpdate protocol message
    // and provides a way to force a routing table update (for testing only,
    // should not be exposed to the user).
    'Backend:RTForceUpdate' => true,

    // Temporary driver feature that will be removed when all official drivers
    // have been unified in their behaviour of when they return a Result object.
    // We aim for drivers to not providing a Result until the server replied with
    // SUCCESS so that the result keys are already known and attached to the
    // Result object without further waiting or communication with the server.
    'Temporary:ResultKeys' => false,

    // Temporary driver feature that will be removed when all official driver
    // backends have implemented all summary response fields.
    'Temporary:FullSummary' => false,

    // Temporary driver feature that will be removed when all official driver
    // backends have implemented path and relationship types
    'Temporary:CypherPathAndRelationship' => false,
];
