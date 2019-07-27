<?php declare(strict_types=1);

namespace Phan\AST;

use ast\Node;
use CompileError;
use Error;
use Microsoft\PhpParser\Diagnostic;
use ParseError;
use Phan\AST\TolerantASTConverter\ParseException;
use Phan\AST\TolerantASTConverter\ParseResult;
use Phan\AST\TolerantASTConverter\ShimFunctions;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\AST\TolerantASTConverter\TolerantASTConverterWithNodeMapping;
use Phan\CodeBase;
use Phan\Config;
use Phan\Daemon\Request;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Library\Cache;
use Phan\Library\DiskCache;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Throwable;
use function error_reporting;

/**
 * Parser parses the passed in PHP code based on configuration settings.
 *
 * It has options for error-tolerant parsing,
 * annotating \ast\Nodes with additional information used by the language server
 */
class Parser
{
    /** @var ?Cache<ParseResult> */
    private static $cache = null;

    /**
     * Creates a cache if Phan is configured to use caching in the current phase.
     *
     * @return ?Cache<ParseResult>
     */
    private static function maybeGetCache(CodeBase $code_base) : ?Cache
    {
        if ($code_base->getExpectChangesToFileContents()) {
            return null;
        }
        if (!Config::getValue('cache_polyfill_asts')) {
            return null;
        }
        return self::getCache();
    }

    /**
     * @return Cache<ParseResult>
     * @suppress PhanPartialTypeMismatchReturn
     */
    private static function getCache() : Cache
    {
        return self::$cache ?? self::$cache = self::makeNewCache();
    }

    /**
     * @return DiskCache<ParseResult>
     */
    private static function makeNewCache() : DiskCache
    {
        $igbinary_version = \phpversion('igbinary') ?: '';
        $use_igbinary = \version_compare($igbinary_version, '2.0.5') >= 0;

        $user = \getenv('USERNAME') ?: \getenv('USER');
        $directory = \sys_get_temp_dir() . '/phan';
        if ($user) {
            $directory .= "-$user";
        }
        return new DiskCache($directory, '-ast', ParseResult::class, $use_igbinary);
    }

    /**
     * Parses the code. If $suppress_parse_errors is false, this also emits SyntaxError.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param ?Request $request (A daemon mode request if in daemon mode. May affect the parser used for $file_path)
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. This may deliberately differ from what is currently on disk (e.g. for the language server mode or daemon mode)
     * @param bool $suppress_parse_errors (If true, don't emit SyntaxError)
     * @throws ParseError
     * @throws CompileError (possible in php 7.3)
     * @throws ParseException
     */
    public static function parseCode(
        CodeBase $code_base,
        Context $context,
        ?Request $request,
        string $file_path,
        string $file_contents,
        bool $suppress_parse_errors
    ) : ?Node {
        try {
            // This will choose the parser to use based on the config and $file_path
            // (For "Go To Definition", one of the files will have a slower parser which records the requested AST node)

            if (self::shouldUsePolyfill($file_path, $request)) {
                // This helper method has its own exception handling.
                // It may throw a ParseException, which is unintentionally not caught here.
                return self::parseCodePolyfill($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $request);
            }
            return self::parseCodeHandlingDeprecation($code_base, $context, $file_contents, $file_path);
        } catch (ParseError $native_parse_error) {
            return self::handleParseError($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $native_parse_error);
        } catch (CompileError $native_parse_error) {
            return self::handleParseError($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $native_parse_error);
        }
    }


    private static function parseCodeHandlingDeprecation(CodeBase $code_base, Context $context, string $file_contents, string $file_path) : Node
    {
        global $__no_echo_phan_errors;
        // Suppress errors such as "declare(encoding=...) ignored because Zend multibyte feature is turned off by settings" (#1076)
        // E_COMPILE_WARNING can't be caught by a PHP error handler,
        // the errors are printed to stderr by default (can't be captured),
        // and those errors might mess up language servers, etc. if ever printed to stdout
        $original_error_reporting = error_reporting();
        error_reporting($original_error_reporting & ~\E_COMPILE_WARNING);
        $__no_echo_phan_errors = static function (int $errno, string $errstr, string $unused_errfile, int $errline) use ($code_base, $context) : bool {
            if ($errno == E_DEPRECATED && preg_match('/Version.*is deprecated/i', $errstr)) {
                return false;
            }
            // Catch errors such as E_DEPRECATED in php 7.4 for the (real) cast.
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::CompatibleSyntaxNotice,
                $errline,
                $errstr
            );
            // Return true to prevent printing to stderr
            return true;
        };
        try {
            return \ast\parse_code(
                $file_contents,
                Config::AST_VERSION,
                $file_path
            );
        } finally {
            $__no_echo_phan_errors = false;
            error_reporting($original_error_reporting);
        }
    }

    /**
     * Handles ParseError|CompileError from the native parser.
     * This will return a Node or re-throw an error, depending on the configuration and parameters.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param ParseError|CompileError $native_parse_error (can be CompileError in 7.3+, will be ParseError in most cases)
     * @throws ParseError most of the time
     * @throws CompileError in PHP 7.3+
     */
    public static function handleParseError(
        CodeBase $code_base,
        Context $context,
        string $file_path,
        string $file_contents,
        bool $suppress_parse_errors,
        Error $native_parse_error
    ) : ?Node {
        if (!$suppress_parse_errors) {
            self::emitSyntaxErrorForNativeParseError($code_base, $context, $file_path, $file_contents, $native_parse_error);
        }
        if (!Config::getValue('use_fallback_parser')) {
            // By default, don't try to re-parse files with syntax errors.
            throw $native_parse_error;
        }

        // If there's a parse error in a file that's excluded from analysis, give up on parsing it.
        // Users might not see the parse error, and ignoring it (e.g. acting as though a file in vendor/ or ext/
        // that can't be parsed has class and function definitions)
        // may lead to users not noticing bugs.
        if (Phan::isExcludedAnalysisFile($file_path)) {
            throw $native_parse_error;
        }
        // But if the user would see the syntax error, go ahead and retry.

        $converter = new TolerantASTConverter();
        $converter->setPHPVersionId(Config::get_closest_target_php_version_id());
        $errors = [];
        try {
            $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors);
        } catch (\Exception $_) {
            // Generic fallback. TODO: log.
            throw $native_parse_error;
        }
        // TODO: loop over $errors?
        return $node;
    }

    /**
     * Emit PhanSyntaxError for ParseError|CompileError from the native parser.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to polyfill parser. May be overridden to ignore what is currently on disk.
     * @param ParseError|CompileError $native_parse_error (can be CompileError in 7.3+, will be ParseError in most cases)
     */
    public static function emitSyntaxErrorForNativeParseError(
        CodeBase $code_base,
        Context $context,
        string $file_path,
        string $file_contents,
        Error $native_parse_error
    ) : void {
        static $last_file_contents = null;
        static $errors = [];

        // Try to get the raw diagnostics by reference.
        // For efficiency, reuse the last result if this was called multiple times in a row.
        if ($last_file_contents !== $file_contents) {
            unset($errors);
            $errors = [];
            try {
                self::parseCodePolyfill($code_base, $context, $file_path, $file_contents, true, null, $errors);
            } catch (Throwable $_) {
                // ignore this exception
            }
        }
        $line = $native_parse_error->getLine();
        $message = $native_parse_error->getMessage();
        // If the polyfill parser emits the first error on the same line as the native parser,
        // mention the column that the polyfill parser found for the error.
        $diagnostic = $errors[0] ?? null;
        // $diagnostic_error_column is either 0 or the column of the error determined by the polyfill parser
        $diagnostic_error_column = 0;
        if ($diagnostic) {
            $start = (int) $diagnostic->start;
            $diagnostic_error_start_line = 1 + \substr_count($file_contents, "\n", 0, $start);
            if ($diagnostic_error_start_line == $line) {
                $diagnostic_error_column = $diagnostic->start - (\strrpos($file_contents, "\n", $start - \strlen($file_contents) - 1) ?: 0);
            }
        }

        Issue::maybeEmitWithParameters(
            $code_base,
            $context,
            Issue::SyntaxError,
            $line,
            [$message],
            null,
            $diagnostic_error_column
        );
    }

    /**
     * Parses the code. If $suppress_parse_errors is false, this also emits SyntaxError.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param bool $suppress_parse_errors (If true, don't emit SyntaxError)
     * @param ?Request $request - May affect the parser used for $file_path
     * @param array<int,Diagnostic> &$errors @phan-output-reference
     * @throws ParseException
     */
    public static function parseCodePolyfill(CodeBase $code_base, Context $context, string $file_path, string $file_contents, bool $suppress_parse_errors, ?Request $request, array &$errors = []) : ?Node
    {
        $converter = self::createConverter($file_path, $file_contents, $request);
        $converter->setPHPVersionId(Config::get_closest_target_php_version_id());
        $errors = [];
        try {
            $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors, self::maybeGetCache($code_base));
        } catch (\Exception $e) {
            // Generic fallback. TODO: log.
            throw new ParseException('Unexpected Exception of type ' . \get_class($e) . ': ' . $e->getMessage(), 0);
        }
        foreach ($errors as $diagnostic) {
            if ($diagnostic->kind === 0) {
                $start = (int)$diagnostic->start;
                $diagnostic_error_message = 'Fallback parser diagnostic error: ' . $diagnostic->message;
                $len = \strlen($file_contents);
                $diagnostic_error_start_line = 1 + \substr_count($file_contents, "\n", 0, $start);
                $diagnostic_error_column = $start - (\strrpos($file_contents, "\n", $start - $len - 1) ?: 0);

                if (!$suppress_parse_errors) {
                    Issue::maybeEmitWithParameters(
                        $code_base,
                        $context,
                        Issue::SyntaxError,
                        $diagnostic_error_start_line,
                        [$diagnostic_error_message],
                        null,
                        $diagnostic_error_column
                    );
                }
                if (!Config::getValue('use_fallback_parser')) {
                    // By default, don't try to re-parse files with syntax errors.
                    throw new ParseException($diagnostic_error_message, $diagnostic_error_start_line);
                }

                // If there's a parse error in a file that's excluded from analysis, give up on parsing it.
                // Users might not see the parse error, and ignoring it (e.g. acting as though a file in vendor/ or ext/
                // that can't be parsed has class and function definitions)
                // may lead to users not noticing bugs.
                if (Phan::isExcludedAnalysisFile($file_path)) {
                    throw new ParseException($diagnostic_error_message, $diagnostic_error_start_line);
                }
            }
        }
        return $node;
    }

    /**
     * Remove the leading #!/path/to/interpreter/of/php from a CLI script, if any was found.
     */
    public static function removeShebang(string $file_contents) : string
    {
        if (\substr($file_contents, 0, 2) !== "#!") {
            return $file_contents;
        }
        for ($i = 2; $i < \strlen($file_contents); $i++) {
            $c = $file_contents[$i];
            if ($c === "\r") {
                if (($file_contents[$i + 1] ?? '') === "\n") {
                    $i++;
                    break;
                }
            } elseif ($c === "\n") {
                break;
            }
        }
        if ($i >= \strlen($file_contents)) {
            return '';
        }
        $rest = (string)\substr($file_contents, $i + 1);
        if (\strcasecmp(\substr($rest, 0, 5), "<?php") === 0) {
            // declare(strict_types=1) must be the first part of the script.
            // Even empty php tags aren't allowed prior to it, so avoid adding empty tags if possible.
            return "<?php\n" . \substr($rest, 5);
        }
        // Preserve the line numbers by adding a no-op newline instead of the removed shebang
        return "<?php\n?>" . $rest;
    }

    private static function shouldUsePolyfill(string $file_path, Request $request = null) : bool
    {
        if (Config::getValue('use_polyfill_parser')) {
            return true;
        }
        if ($request) {
            return $request->shouldUseMappingPolyfill($file_path);
        }
        return false;
    }


    private static function createConverter(string $file_path, string $file_contents, Request $request = null) : TolerantASTConverter
    {
        if ($request && $request->shouldUseMappingPolyfill($file_path)) {
            // TODO: Rename to something better
            $converter = new TolerantASTConverterWithNodeMapping(
                $request->getTargetByteOffset($file_contents),
                static function (Node $node) : void {
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    ConfigPluginSet::instance()->prepareNodeSelectionPluginForNode($node);
                }
            );
            if ($request->shouldAddPlaceholdersForPath($file_path)) {
                $converter->setShouldAddPlaceholders(true);
            }
            return $converter;
        }

        return new TolerantASTConverter();
    }

    /**
     * Get a string representation of the AST kind value.
     * @suppress PhanAccessMethodInternal
     */
    public static function getKindName(int $kind) : string
    {
        static $use_native = null;
        $use_native = ($use_native ?? self::shouldUseNativeAST());
        if ($use_native) {
            return \ast\get_kind_name($kind);
        }
        // The native function doesn't exist or is missing some constants Phan would use.
        return ShimFunctions::getKindName($kind);
    }

    // TODO: Refactor and make more code use this check
    private static function shouldUseNativeAST() : bool
    {
        if (\PHP_VERSION_ID >= 70400) {
            $min_version = '1.0.2';
        } else {
            $min_version = '1.0.1';
        }
        return \version_compare(\phpversion('ast') ?: '0.0.0', $min_version) >= 0;
    }
}
