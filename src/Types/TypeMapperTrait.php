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

namespace Laudis\Neo4j\Types;

use Bolt\structures\Node as BoltNode;
use Bolt\structures\Date as BoltDate;
use Bolt\structures\Duration as BoltDuration;
use Bolt\structures\DateTime as BoltDateTime;

trait TypeMapperTrait
{
    protected $rawToTypes = [
        'node' => [Node::class, 'makeFromHttpNode'],
        BoltNode::class => [Node::class, 'makeFromBoltNode'],
        BoltDate::class => [Date::class, 'makeFromBoltDate'],
        BoltDuration::class => [Duration::class, 'makeFromBoltDuration'],
        BoltDateTime::class => [DateTime::class, 'makeFromBoltDateTime'],
    ];

    protected function mapValueToType(string $type, $value)
    {
        if (!isset($this->rawToTypes[$type])) {
            // @TODO use psalm to statically type the $result array to make sure it complies.
            // if the type isn't declared in $this->rawToTypes we'll just send the value back as is.
            return $value;
        }

        return call_user_func_array($this->rawToTypes[$type], [$value]);
    }

}
