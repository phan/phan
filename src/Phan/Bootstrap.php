<?php declare(strict_types=1);

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;

// Listen for all errors
error_reporting(E_ALL);

// Take as much memory as we need
ini_set("memory_limit", '-1');

// Add the root to the include path
define('CLASS_DIR', __DIR__ . '/../');
set_include_path(get_include_path() . PATH_SEPARATOR . CLASS_DIR);

// Use the composer autoloader
$found_autoloader = false;
foreach ([
    dirname(__DIR__, 2) . '/vendor/autoload.php', // autoloader is in this project (we're in src/Phan and want vendor/autoload.php)
    dirname(__DIR__, 5) . '/vendor/autoload.php', // autoloader is in parent project (we're in vendor/phan/phan/src/Phan/Bootstrap.php and want autoload.php
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
set_exception_handler(static function (Throwable $throwable) {
    error_log("$throwable\n");
    if (class_exists(CodeBase::class, false)) {
        $most_recent_file = CodeBase::getMostRecentlyParsedOrAnalyzedFile();
        if (is_string($most_recent_file)) {
            error_log(sprintf("(Phan %s crashed due to an uncaught Throwable when parsing/analyzing '%s')\n", CLI::PHAN_VERSION, $most_recent_file));
        } else {
            error_log(sprintf("(Phan %s crashed due to an uncaught Throwable)\n", CLI::PHAN_VERSION));
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
 * The error handler for PHP notices, etc.
 * This is a named function instead of a closure to make stack traces easier to read.
 *
 * @suppress PhanAccessMethodInternal
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 */
function phan_error_handler($errno, $errstr, $errfile, $errline)
{
    global $__no_echo_phan_errors;
    if ($__no_echo_phan_errors) {
        return false;
    }
    // php-src/ext/standard/streamsfuncs.c suggests that this is the only error caused by signal handlers and there are no translations
    if ($errno === E_WARNING && preg_match('/^stream_select.*unable to select/', $errstr)) {
        // Don't execute the PHP internal error handler
        return true;
    }
    if ($errno === E_DEPRECATED && preg_match('/ast\\\\parse_/', $errstr)) {
        static $did_warn = false;
        if (!$did_warn) {
            $did_warn = true;
            if (!getenv('PHAN_SUPPRESS_AST_DEPRECATION')) {
                fprintf(
                    STDERR,
                    "php-ast AST version %d used by Phan %s has been deprecated. Check if a newer version of Phan is available." . PHP_EOL,
                    Config::AST_VERSION,
                    CLI::PHAN_VERSION
                );
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
    ob_start();
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    fwrite(STDERR, rtrim(ob_get_clean() ?: "failed to dump backtrace") . PHP_EOL);

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
            if ($i == 0) {
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
            fwrite(STDERR, "(Some long strings (usually JSON of AST Nodes) were truncated. To print more details for some stack frames of this crash, " .
               "increase the Phan config setting debug_max_frame_length)" . PHP_EOL);
        }
    }

    exit(EXIT_FAILURE);
}
set_error_handler('phan_error_handler');

if (!class_exists(CompileError::class)) {
    /**
     * For self-analysis, add CompileError if it was not already declared.
     *
     * In PHP 7.3, a new CompileError class was introduced, and ParseError was turned into a subclass of CompileError.
     *
     * Phan handles both of those separately, so that Phan will work with 7.0-7.3.
     *
     * @suppress PhanRedefineClassInternal
     */
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
    class CompileError extends Error
    {
    }
}

if (extension_loaded('ast')) {
    // Warn if the php-ast version is too low.
    $ast_version = (new ReflectionExtension('ast'))->getVersion();
    if (version_compare($ast_version, '1.0.0') <= 0) {
        if (PHP_VERSION_ID >= 70400) {
            fwrite(STDERR, "Phan is being run with php-ast version $ast_version." . PHP_EOL);
            fwrite(STDERR, "However, when run with PHP 7.4+, Phan requires php-ast 1.0.1 or newer. Older versions of php-ast will crash Phan." . PHP_EOL);
            fwrite(STDERR, "Alternately, to run this version of Phan with PHP 7.4 without upgrading php-ast, uninstall/disable php-ast in php.ini,"
               . " then add the CLI option --allow-polyfill-parser (which is noticeably slower)" . PHP_EOL);
            exit(EXIT_FAILURE);
        }
        if (!getenv('PHAN_SUPPRESS_AST_UPGRADE_NOTICE')) {
            fprintf(
                STDERR,
                "A future major version of Phan will require php-ast 1.0.1+ for AST version 70. php-ast %s is installed." . PHP_EOL,
                $ast_version
            );
            fwrite(STDERR, "(Set PHAN_SUPPRESS_AST_UPGRADE_NOTICE=1 to suppress this message)" . PHP_EOL);
        }
    }
    if (version_compare($ast_version, '0.1.5') < 0) {
        fprintf(STDERR, "Phan supports php-ast version 0.1.5 or newer, but the installed php-ast version is %s. You may see bugs in some edge cases" . PHP_EOL, $ast_version);
    }
}
if (PHP_VERSION_ID < 70100) {
    if (!getenv('PHAN_SUPPRESS_PHP_UPGRADE_NOTICE')) {
        fprintf(
            STDERR,
            "A future major version of Phan will require PHP 7.1+ to run, but PHP %s is installed." . PHP_EOL,
            PHP_VERSION
        );
        fwrite(STDERR, "PHP 7.0 reached its end of life in December 2018." . PHP_EOL);
        fwrite(STDERR, "(Set PHAN_SUPPRESS_PHP_UPGRADE_NOTICE=1 to suppress this message)" . PHP_EOL);
    }
}
