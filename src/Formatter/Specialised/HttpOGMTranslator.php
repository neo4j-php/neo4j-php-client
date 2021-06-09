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

namespace Laudis\Neo4j\Formatter\Specialised;

use Exception;
use function is_array;
use function is_string;
use Iterator;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 * @psalm-import-type RelationshipArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 * @psalm-import-type NodeArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 * @psalm-import-type MetaArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 */
final class HttpOGMTranslator
{
    private HttpOGMArrayTranslator $arrayTranslator;
    private HttpOGMStringTranslator $stringTranslator;

    public function __construct(HttpOGMArrayTranslator $arrayTranslator, HttpOGMStringTranslator $stringTranslator)
    {
        $this->arrayTranslator = $arrayTranslator;
        $this->stringTranslator = $stringTranslator;
    }

    /**
     * @param scalar|array|null           $value
     * @param Iterator<MetaArray>         $meta
     * @param Iterator<RelationshipArray> $relationship
     * @param list<NodeArray>             $nodes
     *
     * @throws Exception
     *
     * @return OGMTypes
     */
    public function translate(Iterator $meta, Iterator $relationship, array $nodes, $value)
    {
        if (is_array($value)) {
            return $this->arrayTranslator->translate($meta, $relationship, $nodes, $value);
        }
        if (is_string($value)) {
            return $this->stringTranslator->translate($meta, $value);
        }

        return $value;
    }
}
