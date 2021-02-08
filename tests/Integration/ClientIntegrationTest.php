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

use Ds\Vector;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use Laudis\Neo4j\Tests\Base\ClientTest;

final class ClientIntegrationTest extends ClientTest
{
    public function createClient(): ClientInterface
    {
        $builder = ClientBuilder::create();
        $aliases = new Vector($this->connectionAliases());
        $aliases = $aliases->slice(0, $aliases->count() - 1);
        foreach ($aliases as $index => $alias) {
            $alias = (new Vector($alias))->first();
            if ($index % 2 === 0) {
                $explosion = explode('-', $alias);
                $version = $explosion[count($explosion) - 1];
                $builder->addBoltConnection('bolt-'.$version, 'bolt://neo4j:test@neo4j-'.$version);
                $builder->addBoltConnection('http-'.$version, 'http://neo4j:test@neo4j-'.$version);
            }
        }

        $builder->addBoltConnection('cluster', 'bolt://neo4j:test@core1', BoltInjections::create()->withAutoRouting(true));

        return $builder->build();
    }

    public function connectionAliases(): iterable
    {
        $tbr = [];
        foreach (['42', '41', '40', '35'] as $version) {
            $hostname = 'neo4j-'.$version.'.';
            if (gethostbyname($hostname) !== $hostname) {
                $tbr[] = ['bolt-'.$version];
                $tbr[] = ['http-'.$version];
            }
        }

        $tbr[] = ['cluster'];

        return $tbr;
    }
}
