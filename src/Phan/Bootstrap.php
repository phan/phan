<?php

declare(strict_types=1);

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Library\StringUtil;

// Listen for all errors
error_reporting(E_ALL);

// Take as much memory as we need
ini_set("memory_limit", '-1');

// Add the root to the include path
define('CLASS_DIR', __DIR__ . '/../');
set_include_path(get_include_path() . PATH_SEPARATOR . CLASS_DIR);

if (PHP_VERSION_ID < 70100) {
    fprintf(
        STDERR,
        "ERROR: Phan 2.x requires PHP 7.1+ to run, but PHP %s is installed." . PHP_EOL,
        PHP_VERSION
    );
    fwrite(STDERR, "PHP 7.0 reached its end of life in December 2018." . PHP_EOL);
    fwrite(STDERR, "Exiting without analyzing code." . PHP_EOL);
    // The version of vendor libraries this depends on will also require php 7.1
    exit(1);
}

const LATEST_KNOWN_PHP_AST_VERSION = '1.0.5';

/**
 * Dump instructions on how to install php-ast
 */
function phan_output_ast_installation_instructions(): void
{
    require_once __DIR__ . '/Library/StringUtil.php';
    $ini_path = php_ini_loaded_file() ?: '(php.ini path could not be determined - try creating one at ' . dirname(PHP_BINARY) . '\\php.ini as a new empty file, or one based on php.ini.development or php.ini.production)';
    $configured_extension_dir = ini_get('extension_dir');
    $extension_dir = StringUtil::isNonZeroLengthString($configured_extension_dir) ? $configured_extension_dir : '(extension directory could not be determined)';
    $extension_dir = "'$extension_dir'";
    $new_extension_dir = dirname(PHP_BINARY) . '\\ext';
    if (!is_dir((string)$configured_extension_dir)) {
        $extension_dir .= ' (extension directory does not exist and may need to be changed)';
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        if (PHP_VERSION_ID < 70500 || !preg_match('/[a-zA-Z]/', PHP_VERSION)) {
            fprintf(
                STDERR,
                PHP_EOL . "Windows users can download php-ast from https://windows.php.net/downloads/pecl/releases/ast/%s/php_ast-%s-%s-%s-%s-%s.zip" . PHP_EOL,
                LATEST_KNOWN_PHP_AST_VERSION,
                LATEST_KNOWN_PHP_AST_VERSION,
                PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                PHP_ZTS ? 'ts' : 'nts',
                PHP_VERSION_ID <= 70100 ? 'vc14' : 'vc15',
                PHP_INT_SIZE == 4 ? 'x86' : 'x64'
            );
            fwrite(STDERR, "(if that link doesn't work, check https://windows.php.net/downloads/pecl/releases/ast/ )" . PHP_EOL);
            fwrite(STDERR, "To install php-ast, add php_ast.dll from the zip to $extension_dir," . PHP_EOL);
            fwrite(STDERR, "Then, enable php-ast by adding the following lines to your php.ini file at '$ini_path'" . PHP_EOL . PHP_EOL);
            if (!is_dir((string)$configured_extension_dir) && is_dir($new_extension_dir)) {
                fwrite(STDERR, "extension_dir=$new_extension_dir" . PHP_EOL);
            }
            fwrite(STDERR, "extension=php_ast.dll" . PHP_EOL . PHP_EOL);
        }
    } else {
        fwrite(STDERR, <<<EOT
php-ast can be installed in the following ways:

1. Unix (PECL): Run 'pecl install ast' and add extension=ast.so to your php.ini.

2. Unix (Compile): Download https://github.com/nikic/php-ast then compile and install the extension as follows:

   cd path/to/php-ast
   phpize
   ./configure
   make
   sudo make install

   Additionally, add extension=ast.so to your php.ini file.

EOT
        );
    }
    fwrite(STDERR, "For more information, see https://github.com/phan/phan/wiki/Getting-Started#installing-dependencies" . PHP_EOL);
}

if (extension_loaded('ast')) {
    // Warn if the php-ast version is too low.
    $ast_version = (string)phpversion('ast');
    if (version_compare($ast_version, '1.0.0') <= 0) {
        if ($ast_version === '') {
            // Seen in php 7.3 with file_cache when ast is initially enabled but later disabled, due to the result of extension_loaded being assumed to be a constant by opcache.
            fwrite(STDERR, "ERROR: extension_loaded('ast') is true, but phpversion('ast') is the empty string. You probably need to clear opcache (opcache.file_cache='" . ini_get('opcache.file_cache') . "')" . PHP_EOL);
        }
        // TODO: Change this to a warning for 0.1.5 - 1.0.0. (https://github.com/phan/phan/issues/2954)
        // 0.1.5 introduced the ast\Node constructor, which is required by the polyfill
        //
        // NOTE: We haven't loaded the autoloader yet, so these issue messages can't be colorized.
        fprintf(
            STDERR,
            "ERROR: Phan 2.x requires php-ast 1.0.1+ because it depends on AST version 70. php-ast '%s' is installed." . PHP_EOL,
            $ast_version
        );
        phan_output_ast_installation_instructions();
        fwrite(STDERR, "Exiting without analyzing files." . PHP_EOL);
        exit(1);
    } elseif (PHP_VERSION_ID >= 80000 && version_compare($ast_version, '1.0.4') < 0) {
        fprintf(
            STDERR,
            "WARNING: Phan 2.x requires php-ast 1.0.4+ to properly analyze ASTs for php 8.0+. php-ast %s and php %s is installed." . PHP_EOL,
            $ast_version,
            PHP_VERSION
        );
        phan_output_ast_installation_instructions();
    } elseif (PHP_VERSION_ID >= 70400 && version_compare($ast_version, '1.0.2') < 0) {
        fprintf(
            STDERR,
            "WARNING: Phan 2.x requires php-ast 1.0.2+ to properly analyze ASTs for php 7.4+. php-ast %s and php %s is installed." . PHP_EOL,
            $ast_version,
            PHP_VERSION
        );
        phan_output_ast_installation_instructions();
    }
}
// Use the composer autoloader
$found_autoloader = false;
foreach ([
    dirname(__DIR__, 2) . '/vendor/autoload.php', // autoloader is in this project (we're in src/Phan and want vendor/autoload.php)
    dirname(__DIR__, 5) . '/vendor/autoload.php', // autoloader is in parent project (we're in vendor/phan/phan/src/Phan/Bootstrap.php and want autoload.php
    dirname(__DIR__, 4) . '/autoload.php',        // autoloader is in parent project (we're in non-standard-vendor/phan/phan/src/Phan/Bootstrap.php and want non-standard-vendor/autoload.php
    ] as $file) {
    if (file_exists($file)) {
        require_once($file);
        $found_autoloader = true;
        break;
    }
}
if (!$found_autoloader) {
    fwrite(STDERR, "Could not locate the autoloader\n");
}

define('EXIT_SUCCESS', 0);
define('EXIT_FAILURE', 1);
define('EXIT_ISSUES_FOUND', EXIT_FAILURE);

// Throw exceptions so asserts can be linked to the code being analyzed
ini_set('assert.exception', '1');
// Set a substitute character for StringUtil::asUtf8()
ini_set('mbstring.substitute_character', (string)0xFFFD);

// Explicitly set each option in case INI is set otherwise
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_WARNING, false);
assert_options(ASSERT_BAIL, false);
// ASSERT_QUIET_EVAL has been removed starting with PHP 8
if (defined('ASSERT_QUIET_EVAL')) {
    assert_options(ASSERT_QUIET_EVAL, false); // @phan-suppress-current-line PhanUndeclaredConstant, UnusedPluginSuppression
}
assert_options(ASSERT_CALLBACK, '');  // Can't explicitly set ASSERT_CALLBACK to null?

/**
 * Print more of the backtrace than is done by default
 * @suppress PhanAccessMethodInternal
 */
set_exception_handler(static function (Throwable $throwable): void {
    fwrite(STDERR, "ERROR: $throwable\n");
    if (class_exists(CodeBase::class, false)) {
        $most_recent_file = CodeBase::getMostRecentlyParsedOrAnalyzedFile();
        if (is_string($most_recent_file)) {
            fprintf(STDERR, "(Phan %s crashed due to an uncaught Throwable when parsing/analyzing '%s')\n", CLI::PHAN_VERSION, $most_recent_file);
        } else {
            fprintf(STDERR, "(Phan %s crashed due to an uncaught Throwable)\n", CLI::PHAN_VERSION);
        }
    }
    exit(EXIT_FAILURE);
});

/**
 * Executes $closure with Phan's default error handler disabled.
 *
 * This is useful in cases where PHP notices are unavoidable,
 * e.g. notices in preg_match() when checking if a regex is valid
 * and you don't want the default behavior of terminating the program.
 *
 * @template T
 * @param Closure():T $closure
 * @return T
 * @see PregRegexCheckerPlugin
 */
function with_disabled_phan_error_handler(Closure $closure)
{
    global $__no_echo_phan_errors;
    $__no_echo_phan_errors = true;
    try {
        return $closure();
    } finally {
        $__no_echo_phan_errors = false;
    }
}

/**
 * Print a backtrace with values to stderr.
 */
function phan_print_backtrace(bool $is_crash = false, int $frames_to_skip = 2): void
{
    // Uncomment this if even trying to print the details would crash
    /*
    ob_start();
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    fwrite(STDERR, rtrim(ob_get_clean() ?: "failed to dump backtrace") . PHP_EOL);
     */

    $frames = debug_backtrace();
    if (isset($frames[1])) {
        fwrite(STDERR, 'More details:' . PHP_EOL);
        if (class_exists(Config::class, false)) {
            $max_frame_length = max(100, Config::getValue('debug_max_frame_length'));
        } else {
            $max_frame_length = 1000;
        }
        $truncated = false;
        foreach ($frames as $i => $frame) {
            if ($i < $frames_to_skip) {
                continue;
            }
            $frame_details = \Phan\Debug\Frame::frameToString($frame);
            if (strlen($frame_details) > $max_frame_length) {
                $truncated = true;
                if (function_exists('mb_substr')) {
                    $frame_details = mb_substr($frame_details, 0, $max_frame_length) . '...';
                } else {
                    $frame_details = substr($frame_details, 0, $max_frame_length) . '...';
                }
            }
            fprintf(STDERR, '#%d: %s' . PHP_EOL, $i, $frame_details);
        }
        if ($truncated) {
            fwrite(STDERR, "(Some long strings (usually JSON of AST Nodes) were truncated. To print more details for some stack frames of this " . ($is_crash ? "crash" : "log") . ", " .
               "increase the Phan config setting debug_max_frame_length)" . PHP_EOL);
        }
    }
}

/**
 * The error handler for PHP notices, etc.
 * This is a named function instead of a closure to make stack traces easier to read.
 *
 * @suppress PhanAccessMethodInternal
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 */
function phan_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    global $__no_echo_phan_errors;
    if ($__no_echo_phan_errors) {
        if ($__no_echo_phan_errors instanceof Closure) {
            if ($__no_echo_phan_errors($errno, $errstr, $errfile, $errline)) {
                return true;
            }
        } else {
            return false;
        }
    }
    // php-src/ext/standard/streamsfuncs.c suggests that this is the only error caused by signal handlers and there are no translations
    if ($errno === E_WARNING && preg_match('/^stream_select.*unable to select/', $errstr)) {
        // Don't execute the PHP internal error handler
        return true;
    }
    if ($errno === E_USER_DEPRECATED && preg_match('/(^Passing a command as string when creating a |method is deprecated since Symfony 4\.4)/', $errstr)) {
        // Suppress deprecation notices running `vendor/bin/paratest`.
        // Don't execute the PHP internal error handler.
        return true;
    }
    if ($errno === E_DEPRECATED && preg_match('/ast\\\\parse_.*Version.*is deprecated/i', $errstr)) {
        static $did_warn = false;
        if (!$did_warn) {
            $did_warn = true;
            if (!getenv('PHAN_SUPPRESS_AST_DEPRECATION')) {
                CLI::printWarningToStderr(sprintf(
                    "php-ast AST version %d used by Phan %s has been deprecated. Check if a newer version of Phan is available." . PHP_EOL,
                    Config::AST_VERSION,
                    CLI::PHAN_VERSION
                ));
                fwrite(STDERR, "(Set PHAN_SUPPRESS_AST_DEPRECATION=1 to suppress this message)" . PHP_EOL);
            }
        }
        // Don't execute the PHP internal error handler
        return true;
    }
    fwrite(STDERR, "$errfile:$errline [$errno] $errstr\n");
    if (error_reporting() === 0) {
        // https://secure.php.net/manual/en/language.operators.errorcontrol.php
        // Don't make Phan terminate if the @-operator was being used on an expression.
        return false;
    }

    if (class_exists(CodeBase::class, false)) {
        $most_recent_file = CodeBase::getMostRecentlyParsedOrAnalyzedFile();
        if (is_string($most_recent_file)) {
            fprintf(STDERR, "(Phan %s crashed when parsing/analyzing '%s')" . PHP_EOL, CLI::PHAN_VERSION, $most_recent_file);
        } else {
            fprintf(STDERR, "(Phan %s crashed)" . PHP_EOL, CLI::PHAN_VERSION);
        }
    }

    phan_print_backtrace(true);

    exit(EXIT_FAILURE);
}
set_error_handler('phan_error_handler');

if (!class_exists(CompileError::class)) {
    /**
     * For self-analysis, add CompileError if it was not already declared.
     *
     * In PHP 7.3, a new CompileError class was introduced, and ParseError was turned into a subclass of CompileError.
     *
     * Phan handles both of those separately, so that Phan will work in 7.1+
     *
     * @suppress PhanRedefineClassInternal
     */
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
    class CompileError extends Error
    {
    }
}

if (!function_exists('spl_object_id')) {
    require_once dirname(__DIR__) . '/spl_object_id.php';
}
