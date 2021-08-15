<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

/**
 * Sort the functiton or delta signature maps provided as arguments on stdin
 */
function sort_signature_maps(): void {
    global $argv;
    $files = array_slice($argv, 1);
    if (!$files) {
        CLI::printErrorToStderr("Usage: ${argv[1]} path/to/SignatureMap1.php");
        exit(1);
    }
    foreach ($files as $original_path) {
        $contents = require($original_path);
        if (!is_array($contents)) {
            CLI::printErrorToStderr("$original_path is not a signature map - expected it to return an array\n");
            continue;
        }
        if (isset($contents['added'])) {
            foreach ($contents as &$section) {
                IncompatibleXMLSignatureDetector::sortSignatureMap($section);
            }
            unset($section);
        } else {
            IncompatibleXMLSignatureDetector::sortSignatureMap($contents);
        }
        $new_path = $original_path . '.sorted';
        // @phan-suppress-next-line PhanPluginRemoveDebugCall
        fprintf(STDERR, "Saving sorted signature map to %s\n", $new_path);
        IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($new_path, $original_path, $contents);
    }
}
sort_signature_maps();
