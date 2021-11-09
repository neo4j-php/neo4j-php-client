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
use Laudis\TypedEnum\TypedEnum;

/**
 * Defines the protocol used in a connection.
 *
 * @method static ConnectionProtocol BOLT_V3()
 * @method static ConnectionProtocol BOLT_V40()
 * @method static ConnectionProtocol BOLT_V41()
 * @method static ConnectionProtocol BOLT_V42()
 * @method static ConnectionProtocol BOLT_V43()
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
    private const HTTP = 'http';

    /**
     * @pure
     *
     * @psalm-suppress ImpureMethodCall
     */
    public static function determineBoltVersion(Bolt $bolt): self
    {
        switch ($bolt->getProtocolVersion()) {
            case 3:
                $tbr = self::BOLT_V3();
                break;
            case 4.0:
                $tbr = self::BOLT_V40();
                break;
            case 4.1:
                $tbr = self::BOLT_V41();
                break;
            case 4.2:
                $tbr = self::BOLT_V42();
                break;
            case 4.3:
            default:
                $tbr = self::BOLT_V43();
                break;
        }

        return $tbr;
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
