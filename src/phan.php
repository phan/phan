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
use Phan\CodeBase;
use Phan\Config;
use Phan\Phan;

try {
    $node = \ast\parse_code(
        '<?php 42;',
        Config::AST_VERSION
    );
} catch (LogicException $throwable) {
    assert(false,
        'Unknown AST version ('
        . Config::AST_VERSION
        . ') in configuration. '
        . 'You may need to rebuild the latest '
        . 'version of the php-ast extension.'
    );
}

if (extension_loaded('ast')) {
    // Warn if the php-ast version is too low.
    // (It's remotely possible \ast\parse_code could a pure PHP substitute for php-ast in the future)
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
        function() use($cli) { return $cli->getFileList(); }  // Daemon mode will reload the file list.
    );

// Provide an exit status code based on if
// issues were found
exit($is_issue_found ? EXIT_ISSUES_FOUND : EXIT_SUCCESS);
