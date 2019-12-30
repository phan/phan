#!/usr/bin/env php
<?php
declare(strict_types=1);

use Phan\CLIBuilder;
use Phan\Phan;

/**
 * Print usage for suggest_functions_to_fully_qualify and exit.
 */
function suggest_functions_to_fully_qualify_usage(int $status) : void
{
    global $argv;
    $program = $argv[0];
    fwrite($status != 0 ? STDERR : STDOUT, <<<EOT
Usage: $program [options]

Dumps a stub that can be used to instrument the codebase for functions that should be changed to be fully qualified.

Options:
  -h, --help: Print this help message to stdout.

EOT
    );
    exit($status);
}
call_user_func(static function () : void {
    global $argv;
    $options = getopt(
        "hp",
        [
            'help',
            'progress-bar',
        ],
        $optind
    );
    $has_any_option = static function (string ...$arg_names) use ($options) : bool {
        foreach ($arg_names as $arg) {
            if (array_key_exists($arg, $options)) {
                return true;
            }
        }
        return false;
    };

    if ($has_any_option('h', 'help')) {
        suggest_functions_to_fully_qualify_usage(0);
        return;
    }
    $remaining_argv = array_slice($argv, $optind);
    if (count($remaining_argv) !== 0) {
        fwrite(STDERR, "ERROR: Expected 0 arguments, got " . count($remaining_argv) . "\n");
        suggest_functions_to_fully_qualify_usage(1);
    }

    $code_base = require_once(__DIR__ . '/../src/codebase.php');
    require_once(__DIR__ . '/../src/Phan/Bootstrap.php');

    $cli_builder = new CLIBuilder();
    if ($has_any_option('p', 'progress-bar')) {
        $cli_builder->setOption('progress-bar');
    }
    $cli_builder->setOption('quick');
    $cli_builder->setOption('plugin', __DIR__ . '/lib/NotFullyQualifiedReporterPlugin.php');
    // @phan-suppress-next-line PhanThrowTypeAbsentForCall
    $cli = $cli_builder->build();

    // @phan-suppress-next-line PhanThrowTypeAbsentForCall
    Phan::analyzeFileList($code_base, /** @return string[] */ static function () use ($cli) : array {
        return $cli->getFileList();
    });
});
