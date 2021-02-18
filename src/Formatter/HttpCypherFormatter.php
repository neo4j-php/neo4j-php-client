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

namespace Laudis\Neo4j\Formatter;

use Ds\Vector;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\StatementStatistics;

/**
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class HttpCypherFormatter
{
    /**
     * @param CypherResponseSet $responseSet
     *
     * @return Vector<StatementStatistics>
     *
     * @codeCoverageIgnore Still dead code
     */
    public function formatStats(array $responseSet): Vector
    {
        $tbr = new Vector();
        foreach ($responseSet['results'] as $result) {
            if (!isset($result['stats'])) {
                throw new InvalidArgumentException('No stats found in the response set');
            }
            $tbr->push(new StatementStatistics(
                $result['stats']['nodes_created'],
                $result['stats']['nodes_deleted'],
                $result['stats']['relationships_created'],
                $result['stats']['relationships_deleted'],
                $result['stats']['properties_set'],
                $result['stats']['labels_added'],
                $result['stats']['labels_removed'],
                $result['stats']['indexes_added'],
                $result['stats']['indexes_removed'],
                $result['stats']['constraints_added'],
                $result['stats']['constraints_removed'],
                $result['stats']['contains_updates'],
                $result['stats']['contains_system_updates'] ?? false,
                $result['stats']['system_updates'] ?? 0,
            ));
        }

        return $tbr;
    }
}
