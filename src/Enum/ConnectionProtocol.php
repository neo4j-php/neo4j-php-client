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

namespace Laudis\Neo4j\Enum;

use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_1;
use Bolt\protocol\V4_2;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use JsonSerializable;
use Laudis\TypedEnum\TypedEnum;


enum ConnectionProtocol: string
{
    case V3 = '3';
    case V4_0 = '4';
    case V4_1 = '4.1';
    case V4_2 = '4.2';
    case V4_3 = '4.3';
    case V4_4 = '4.4';
    case V5 = '5';
    case V5_1 = '5.1';
    case V5_2 = '5.2';
    case V5_3 = '5.3';
    case V5_4 = '5.4';

    /**
     * @pure
     */
    public static function determineBoltVersion(V3|V4|V4_1|V4_2|V4_3|V4_4|V5|V5_1|V5_2|V5_3|V5_4 $bolt): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === basename(str_replace('\\', '/',  get_class($bolt)))) {
                return $case;
            }
        }

        return self::V4_4;
    }

    public function compare(ConnectionProtocol $protocol): int
    {
        $x = 0;
        $y = 0;

        foreach (self::cases() as $index => $instance) {
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
