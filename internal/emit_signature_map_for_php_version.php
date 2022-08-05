<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;
use Phan\Language\UnionType;

/**
 * Emit the signature map for a given php version.
 * Can be used to change the base signature map to target a different version.
 */
function emit_signature_map_for_php_version(): void
{
    global $argv;
    if (count($argv) !== 3) {
        CLI::printErrorToStderr("Usage: {$argv[0]} PHP_VERSION_ID path/to/SignatureMapForVersion.php\n");
        exit(1);
    }
    $version_id = filter_var($argv[1], FILTER_VALIDATE_INT);
    if (!$version_id) {
        CLI::printErrorToStderr("PHP_VERSION_ID must be a number (e.g. 80000)");
        CLI::printErrorToStderr("Usage: {$argv[0]} PHP_VERSION_ID path/to/SignatureMap1.php\n");
        exit(1);
    }
    $path = dirname(__DIR__) . '/src/Phan/Language/UnionType.php';
    $contents = (string)file_get_contents($path);
    $contents = preg_replace('/(^<\?php)|\\\\?strtolower/i', '', $contents);
    $contents = str_replace('__DIR__', var_export(dirname($path), true), $contents);
    // @phan-suppress-next-line PhanPluginUnsafeEval
    eval($contents);
    $signatures = UnionType::internalFunctionSignatureMap($version_id);
    IncompatibleXMLSignatureDetector::saveSignatureMap($argv[2], $signatures);
}
emit_signature_map_for_php_version();
