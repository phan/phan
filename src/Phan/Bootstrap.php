<?php declare(strict_types=1);

use Phan\CLI;
use Phan\CodeBase;

// Listen for all errors
error_reporting(E_ALL);

// Take as much memory as we need
ini_set("memory_limit", '-1');

// Add the root to the include path
define('CLASS_DIR', __DIR__ . '/../');
set_include_path(get_include_path() . PATH_SEPARATOR . CLASS_DIR);

// Use the composer autoloader
foreach ([
    __DIR__ . '/../../vendor/autoload.php',          // autoloader is in this project
    __DIR__ . '/../../../../../vendor/autoload.php', // autoloader is in parent project
    ] as $file) {
    if (file_exists($file)) {
        require_once($file);
        break;
    }
}

define('EXIT_SUCCESS', 0);
define('EXIT_FAILURE', 1);
define('EXIT_ISSUES_FOUND', EXIT_FAILURE);

// Throw exceptions so asserts can be linked to the code being analyzed
ini_set('assert.exception', '1');

// Explicitly set each option in case INI is set otherwise
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_WARNING, false);
assert_options(ASSERT_BAIL, false);
assert_options(ASSERT_QUIET_EVAL, false);
assert_options(ASSERT_CALLBACK, '');  // Can't explicitly set ASSERT_CALLBACK to null?

/**
 * Print more of the backtrace than is done by default
 * @suppress PhanAccessMethodInternal
 */
set_exception_handler(function (Throwable $throwable) {
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
 * @return mixed
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
 * @suppress PhanUnreferencedFunction
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
    error_log("$errfile:$errline [$errno] $errstr\n");
    if (class_exists(CodeBase::class, false)) {
        $most_recent_file = CodeBase::getMostRecentlyParsedOrAnalyzedFile();
        if (is_string($most_recent_file)) {
            error_log(sprintf("(Phan %s crashed when parsing/analyzing '%s')\n", CLI::PHAN_VERSION, $most_recent_file));
        } else {
            error_log(sprintf("(Phan %s crashed)\n", CLI::PHAN_VERSION));
        }
    }
    if (error_reporting() === 0) {
        // https://secure.php.net/manual/en/language.operators.errorcontrol.php
        // Don't make Phan terminate if the @-operator was being used on an expression.
        return false;
    }

    ob_start();
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    error_log(ob_get_clean());

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
    class CompileError extends Error
    {
    }
}
