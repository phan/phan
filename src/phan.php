<?php declare(strict_types=1);

// Phan does a ton of GC and this offers a major speed
// improvement if your system can handle it (which it
// should be able to)
gc_disable();

// Check the environment to make sure Phan can run successfully
require_once(__DIR__ . '/requirements.php');

// Build a code base based on PHP internally defined
// functions, methods and classes before loading our
// own
$code_base = require_once(__DIR__ . '/codebase.php');

require_once(__DIR__ . '/Phan/Bootstrap.php');

use Phan\CLI;
use Phan\Phan;

if (extension_loaded('ast')) {
    // Warn if the php-ast version is too low.
    $ast_version = (new ReflectionExtension('ast'))->getVersion();
    if (version_compare($ast_version, '0.1.5') < 0) {
        fprintf(STDERR, "Phan supports php-ast version 0.1.5 or newer, but the installed php-ast version is $ast_version. You may see bugs in some edge cases\n");
    }
}

// Create our CLI interface and load arguments
$cli = new CLI();

// Analyze the file list provided via the CLI
$is_issue_found =
    Phan::analyzeFileList(
        $code_base,
        function (bool $recomputeFileList = false) use ($cli) {
            if ($recomputeFileList) {
                $cli->recomputeFileList();
            }
            return $cli->getFileList();
        }  // Daemon mode will reload the file list.
    );

// Provide an exit status code based on if
// issues were found
exit($is_issue_found ? EXIT_ISSUES_FOUND : EXIT_SUCCESS);
