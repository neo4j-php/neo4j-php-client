<?php

declare(strict_types=1);

/**
 * php-bolt 7.5.0 unpacks a UUID as 32 hex characters, then its constructor
 * requires the hyphenated form, so every received UUID throws. PackStream 1
 * also treats marker 0xE0 as an unknown value and returns null, so Bolt 6.0
 * accepts a UUID instead of failing. These two edits match the local driver
 * behaviour the TestKit UUID tests cover.
 */

$root = dirname(__DIR__);
$bolt = $root.'/vendor/stefanak-michal/bolt/src/packstream';

patchFile(
    $bolt.'/v1/Unpacker.php',
    <<<'UPSTREAM'
        if ($marker === null) {
            return null;
        }

        if ($marker == 0xC3) {
UPSTREAM,
    <<<'PATCHED'
        if ($marker === null) {
            return null;
        }

        // UUID (marker 0xE0) exists only in PackStream 1.1 / Bolt 6.1.
        if ($marker === 0xE0) {
            throw new UnpackException('Unknown PackStream marker 0xe0');
        }

        if ($marker == 0xC3) {
PATCHED,
);

patchFile(
    $bolt.'/v1_1/Unpacker.php',
    <<<'UPSTREAM'
                substr($hex, 0, 8)
                . substr($hex, 8, 4)
                . substr($hex, 12, 4)
                . substr($hex, 16, 4)
                . substr($hex, 20, 12)
UPSTREAM,
    <<<'PATCHED'
                substr($hex, 0, 8)
                . '-' . substr($hex, 8, 4)
                . '-' . substr($hex, 12, 4)
                . '-' . substr($hex, 16, 4)
                . '-' . substr($hex, 20, 12)
PATCHED,
);

/**
 * @param non-empty-string $path
 */
function patchFile(string $path, string $upstream, string $patched): void
{
    if (!is_file($path)) {
        fwrite(STDERR, "php-bolt file not found: {$path}\n");
        exit(1);
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }

    $contents = str_replace("\r\n", "\n", $contents);
    if (str_contains($contents, $patched)) {
        fwrite(STDOUT, "already patched {$path}\n");

        return;
    }

    if (!str_contains($contents, $upstream)) {
        fwrite(STDERR, "Unexpected php-bolt contents, not patching {$path}\n");
        exit(1);
    }

    $updated = str_replace($upstream, $patched, $contents);
    if (file_put_contents($path, $updated) === false) {
        fwrite(STDERR, "Unable to write {$path}\n");
        exit(1);
    }

    fwrite(STDOUT, "patched {$path}\n");
}
