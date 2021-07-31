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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\TestkitBackend\Input\GetFeaturesInput;
use Laudis\Neo4j\TestkitBackend\Responses\FeatureListResponse;

/**
 * @implements ActionInterface<GetFeaturesInput>
 */
final class GetFeatures implements ActionInterface
{
    private array $featuresConfig;

    public function __construct(array $featuresConfig)
    {
        $this->featuresConfig = $featuresConfig;
    }

    /**
     * @param GetFeaturesInput $input
     */
    public function handle($input): FeatureListResponse
    {
        $features = [];
        foreach ($this->featuresConfig as $feature => $available) {
            if ($available) {
                $features[] = $feature;
            }
        }

        return new FeatureListResponse($features);
    }
}
