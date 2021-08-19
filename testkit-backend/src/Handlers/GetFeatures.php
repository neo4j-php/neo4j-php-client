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

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Requests\GetFeaturesRequest;
use Laudis\Neo4j\TestkitBackend\Responses\FeatureListResponse;

/**
 * @implements RequestHandlerInterface<GetFeaturesRequest>
 */
final class GetFeatures implements RequestHandlerInterface
{
    /** @var iterable<string, bool> */
    private iterable $featuresConfig;

    /**
     * @param iterable<string, bool> $featuresConfig
     */
    public function __construct(iterable $featuresConfig)
    {
        $this->featuresConfig = $featuresConfig;
    }

    /**
     * @param GetFeaturesRequest $request
     */
    public function handle($request): FeatureListResponse
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
