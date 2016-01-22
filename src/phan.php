<?php declare(strict_types=1);

// Check the environment to make sure Phan can run successfully
require_once(__DIR__ . '/requirements.php');

// Build a code base based on PHP internally defined
// functions, methods and classes before loading our
// own
$code_base = require_once(__DIR__ . '/codebase.php');

require_once(__DIR__ . '/Phan/Bootstrap.php');

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Phan;

// Create our CLI interface and load arguments
$cli = new CLI();

$file_list = $cli->getFileList();

// If requested, expand the file list to a set of
// all files that should be re-analyzed
if (Config::get()->expand_file_list) {
    assert(
        (bool)(Config::get()->stored_state_file_path),
        'Requesting an expanded dependency list can only '
        . ' be done if a state-file is defined'
    );

    // Analyze the file list provided via the CLI
    $file_list = Phan::expandedFileList($code_base, $file_list);
}

// Analyze the file list provided via the CLI
Phan::analyzeFileList(
    $code_base,
    $file_list
);
