<?php

declare(strict_types=1);

/**
 * Temporary utility script to switch phan from the old flat style of signature map
 * to one that is easier to read and edit.
 *
 * @phan-file-suppress PhanUnreferencedFunction
 */

/**
 * Convert the new style of delta map to a flattened version.
 * Possibly useful for interoperability with other applications using the signature map.
 *
 * @param array{added?:array<string,string[]>,removed?:array<string,string[]>,changed?:array<string,array{old:string[],new:string[]}>} $map
 * @return array{old:array<string,string[]>,new:array<string,string[]>}
 */
function convert_verbose_delta_map_to_flat_delta_map(array $map): array
{
    $new_delta_map = $map['added'] ?? [];
    $old_delta_map = $map['removed'] ?? [];
    foreach ($map['changed'] ?? [] as $name => ['old' => $old_signature, 'new' => $new_signature]) {
        $new_delta_map[$name] = $new_signature;
        $old_delta_map[$name] = $old_signature;
    }
    uksort($new_delta_map, 'strcasecmp');
    uksort($old_delta_map, 'strcasecmp');
    return [
        'new' => $new_delta_map,
        'old' => $old_delta_map,
    ];
}

/**
 * @param array{old:array<string,string[]>,new:array<string,string[]>} $map
 * @return array{added:array<string,string[]>,removed:array<string,string[]>,changed:array<string,array{old:string[],new:string[]}>} $map
 */
function convert_flat_delta_map_to_verbose_delta_map(array $map): array
{
    $removed = [];
    $changed = [];

    $old = $map['old'];
    $new = $map['new'];
    foreach ($old as $name => $old_signature) {
        $new_signature = $new[$name] ?? null;
        if ($new_signature) {
            $changed[$name] = [
                'old' => $old_signature,
                'new' => $new_signature,
            ];
            unset($new[$name]);
            continue;
        }
        $removed[$name] = $old_signature;
    }
    $added = $new;
    return [
        'added' => $added,
        'changed' => $changed,
        'removed' => $removed,
    ];
}

// @phan-suppress-next-line PhanPluginRemoveDebugCall
fwrite(STDERR, "This script is now a no-op\n");
/*
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/IncompatibleXMLSignatureDetector.php';

//$pattern = dirname(__DIR__, 2) . '/psalm/dictionaries/CallMap*delta.php';

$pattern = dirname(__DIR__) . '/src/Phan/Language/Internal/FunctionSignatureMap*delta.php';
$files = glob($pattern);
if (!$files) {
    \Phan\CLI::printErrorToStderr("Could not find $pattern\n");
    exit(1);
}
foreach ($files as $filename) {
    $flat_delta_map = require($filename);
    $result = convert_flat_delta_map_to_verbose_delta_map($flat_delta_map);

    $new_filename = $filename;
    // @phan-suppress-next-line PhanPluginRemoveDebugCall
    fprintf(STDERR, "Saving adjusted map to %s\n", $new_filename);
    IncompatibleSignatureDetectorBase::saveSignatureDeltaMap($new_filename, $filename, $result);
}
 */
