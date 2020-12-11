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

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Tests\Base\ClientTest;

final class ClientIntegrationTest extends ClientTest
{
    public function createClient(): ClientInterface
    {
        $builder = ClientBuilder::create();
        foreach ($this->connectionAliases() as $index => $alias) {
            if ($index % 2 === 0) {
                $explosition = explode('-', $alias);
                $version = $explosition[count($explosition) - 1];
                $builder->addBoltConnection('bolt-'.$version, 'bolt://neo4j:test@neo4j-'.$version);
                $builder->addBoltConnection('http-'.$version, 'http://neo4j:test@neo4j-'.$version);
            }
        }

        return $builder->build();
    }

    public function connectionAliases(): iterable
    {
        $tbr = [];
        foreach (['42', '41', '40', '35'] as $version) {
            $hostname = 'neo4j-'.$version.'.';
            if (gethostbyname($hostname) !== $hostname) {
                $tbr[] = 'bolt-'.$version;
                $tbr[] = 'http-'.$version;
            }
        }

        return $tbr;
    }
}
