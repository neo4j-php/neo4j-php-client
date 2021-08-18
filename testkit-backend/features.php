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

return [
    // === OPTIMIZATIONS ===
    // On receiving Neo.ClientError.Security.AuthorizationExpired, the driver
    // shouldn't reuse any open connections for anything other than finishing
    // a started job. All other connections should be re-established before
    // running the next job with them.
    'AuthorizationExpiredTreatment' => false,

    // Driver doesn't explicitly send message data that is the default value.
    // This conserves bandwidth.
    'Optimization:ImplicitDefaultArguments' => false,

    // The driver sends no more than the strictly necessary RESET messages.
    'Optimization:MinimalResets' => false,

    // The driver caches connections (e.g., in a pool) and doesn't start a new
    // one (with hand-shake, HELLO, etc.) for each query.
    'Optimization:ConnectionReuse' => false,

    // The driver doesn't wait for a SUCCESS after calling RUN but pipelines a
    // PULL right afterwards and consumes two messages after that. This saves a
    // full round-trip.
    'Optimization:PullPipelining' => false,

    // === CONFIGURATION HINTS (BOLT 4.3+) ===
    // The driver understands and follow the connection hint
    // connection.recv_timeout_seconds which tells it to close the connection
    // after not receiving an answer on any request for longer than the given
    // time period. On timout, the driver should remove the server from its
    // routing table and assume all other connections to the server are dead
    // as well.
    'ConfHint:connection.recv_timeout_seconds' => false,

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
    'Temporary:CypherPathAndRelationship' => true,
];
