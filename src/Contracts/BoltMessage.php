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

namespace Laudis\Neo4j\Contracts;

use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Iterator;

abstract class BoltMessage
{
    public function __construct(
        private readonly V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol,
    ) {
    }

    /**
     * Sends the Bolt message.
     */
    abstract public function send(): BoltMessage;

    public function getResponse(): Response
    {
        return $this->protocol->getResponse();
    }

    /**
     * @return Iterator<Response>
     */
    public function getResponses(): Iterator
    {
        /**
         * @var Iterator<Response>
         */
        return $this->protocol->getResponses();
    }
}
