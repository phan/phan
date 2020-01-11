<?php

declare(strict_types=1);

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

// Create our CLI interface and load arguments
$cli = CLI::fromArgv();

// Analyze the file list provided via the CLI
$is_issue_found =
    Phan::analyzeFileList(
        $code_base,
        /** @return list<string> */
        static function (bool $recompute_file_list = false) use ($cli): array {
            if ($recompute_file_list) {
                $cli->recomputeFileList();
            }
            return $cli->getFileList();
        }  // Daemon mode will reload the file list.
    );

// Provide an exit status code based on if
// issues were found
exit($is_issue_found ? EXIT_ISSUES_FOUND : EXIT_SUCCESS);
