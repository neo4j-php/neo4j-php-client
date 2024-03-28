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

namespace Laudis\Neo4j\Common;

use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Exception\Neo4jException;

class ResponseHelper
{
    public static function getResponse(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol): Response
    {
        $response = $protocol->getResponse();
        if ($response->signature === Signature::FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }

        return $response;
    }
}
