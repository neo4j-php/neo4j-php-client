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

use PhpCsFixer\Config;

$header = <<<'EOF'
This file is part of the Laudis Neo4j package.

(c) Laudis technologies <http://laudis.tech>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

try {
    $finder = PhpCsFixer\Finder::create()
        ->in(__DIR__.'/src')
        ->in(__DIR__.'/tests');
} catch (Throwable $e) {
    echo $e->getMessage()."\n";

    exit(1);
}

return (new Config())
    ->setRules([
        '@Symfony' => true,

        'array_syntax' => ['syntax' => 'short'],
        'header_comment' => ['header' => $header],
        'linebreak_after_opening_tag' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'phpdoc_to_comment' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
;
