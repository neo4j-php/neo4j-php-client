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

namespace Laudis\Neo4j\Enum;

use Bolt\Bolt;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_1;
use Bolt\protocol\V4_2;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use Laudis\TypedEnum\TypedEnum;

/**
 * Defines the protocol used in a connection.
 *
 * @method static ConnectionProtocol BOLT_V3()
 * @method static ConnectionProtocol BOLT_V40()
 * @method static ConnectionProtocol BOLT_V41()
 * @method static ConnectionProtocol BOLT_V42()
 * @method static ConnectionProtocol BOLT_V43()
 * @method static ConnectionProtocol BOLT_V44()
 * @method static ConnectionProtocol HTTP()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class ConnectionProtocol extends TypedEnum
{
    private const BOLT_V3 = 'bolt-v3';
    private const BOLT_V40 = 'bolt-v40';
    private const BOLT_V41 = 'bolt-v41';
    private const BOLT_V42 = 'bolt-v42';
    private const BOLT_V43 = 'bolt-v43';
    private const BOLT_V44 = 'bolt-v44';
    private const HTTP = 'http';

    /**
     * @pure
     *
     * @psalm-suppress ImpureMethodCall
     */
    public static function determineBoltVersion(V3 $bolt): self
    {
        if ($bolt instanceof V4_4) {
            return self::BOLT_V44();
        }
        if ($bolt instanceof V4_3) {
            return self::BOLT_V43();
        }
        if ($bolt instanceof V4_2) {
            return self::BOLT_V42();
        }
        if ($bolt instanceof V4_1) {
            return self::BOLT_V41();
        }
        if ($bolt instanceof V4) {
            return self::BOLT_V40();
        }

        return self::BOLT_V3();
    }

    public function compare(ConnectionProtocol $protocol): int
    {
        $x = 0;
        $y = 0;

        /** @psalm-suppress ImpureMethodCall */
        foreach (array_values(self::getAllInstances()) as $index => $instance) {
            if ($instance === $this) {
                $x = $index;
            }

            if ($instance === $protocol) {
                $y = $index;
            }
        }

        return $x - $y;
    }
}
