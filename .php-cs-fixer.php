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

use PhpCsFixer\Config;

$header = <<<'EOF'
This file is part of the Neo4j PHP Client and Driver package.

(c) Nagels <https://nagels.tech>

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
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,

        'array_syntax' => ['syntax' => 'short'],
        'header_comment' => ['header' => $header],
        'linebreak_after_opening_tag' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'phpdoc_to_comment' => false,
        'yoda_style' => false,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
    ])
    ->setFinder($finder)
;
