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

namespace Laudis\Neo4j\Authentication;

use Laudis\Neo4j\Bolt\BoltConnection;

/**
 * Merges Bolt HELLO fields required for Neo4j 4.3+ temporal UTC patch negotiation.
 */
final class BoltHelloMetadata
{
    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    public static function withUtcPatchIfSupported(BoltConnection $connection, array $metadata): array
    {
        // Neo4j 4.3–4.4: optional UTC temporal patch. Bolt 5+ TestKit scripts expect HELLO without patch_bolt.
        // Compare against "5" (not "5.0"): bolt library getVersion() returns "5" for class V5.
        $v = $connection->protocol()->getVersion();
        if (version_compare($v, '4.3', '>=') && version_compare($v, '5', '<')) {
            $metadata['patch_bolt'] = ['utc'];
        }

        return $metadata;
    }
}
