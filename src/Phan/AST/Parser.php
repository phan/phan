<?php declare(strict_types=1);

namespace Phan\AST;

use ast\Node;
use CompileError;
use Error;
use ParseError;
use Phan\AST\TolerantASTConverter\ParseException;
use Phan\AST\TolerantASTConverter\ParseResult;
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
    private static function maybeGetCache(CodeBase $code_base)
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
     * @return ?Node
     * @throws ParseError
     * @throws CompileError (possible in php 7.3)
     * @throws ParseException
     */
    public static function parseCode(
        CodeBase $code_base,
        Context $context,
        $request,
        string $file_path,
        string $file_contents,
        bool $suppress_parse_errors
    ) {
        try {
            // This will choose the parser to use based on the config and $file_path
            // (For "Go To Definition", one of the files will have a slower parser which records the requested AST node)

            if (self::shouldUsePolyfill($file_path, $request)) {
                // This helper method has its own exception handling.
                // It may throw a ParseException, which is unintentionally not caught here.
                return self::parseCodePolyfill($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $request);
            }
            // Suppress "declare(encoding=...) ignored because Zend multibyte feature is turned off by settings" (#1076)
            // E_COMPILE_WARNING can't be caught by a PHP error handler,
            // the errors are printed to stderr by default (can't be captured),
            // and those errors might mess up language servers, etc. if ever printed to stdout
            $original_error_reporting = error_reporting();
            error_reporting($original_error_reporting & ~\E_COMPILE_WARNING);
            try {
                return \ast\parse_code(
                    $file_contents,
                    Config::AST_VERSION,
                    $file_path
                );
            } finally {
                error_reporting($original_error_reporting);
            }
        } catch (ParseError $native_parse_error) {
            return self::handleParseError($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $native_parse_error);
        } catch (CompileError $native_parse_error) {
            return self::handleParseError($code_base, $context, $file_path, $file_contents, $suppress_parse_errors, $native_parse_error);
        }
    }

    /**
     * Handles ParseError|CompileError.
     * This will return a Node or re-throw an error, depending on the configuration and parameters.
     *
     * This method is written to be compatible with PHP 7.0-7.3.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param ParseError|CompileError $native_parse_error (can be CompileError in 7.3, will be ParseError in most cases)
     * @return ?Node
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
    ) {
        if (!$suppress_parse_errors) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::SyntaxError,
                $native_parse_error->getLine(),
                $native_parse_error->getMessage()
            );
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
        $converter->setParseAllDocComments(Config::getValue('polyfill_parse_all_element_doc_comments'));
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
     * Parses the code. If $suppress_parse_errors is false, this also emits SyntaxError.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param bool $suppress_parse_errors (If true, don't emit SyntaxError)
     * @param ?Request $request - May affect the parser used for $file_path
     * @return ?Node
     * @throws ParseException
     */
    public static function parseCodePolyfill(CodeBase $code_base, Context $context, string $file_path, string $file_contents, bool $suppress_parse_errors, $request)
    {
        $converter = self::createConverter($file_path, $file_contents, $request);
        $converter->setPHPVersionId(Config::get_closest_target_php_version_id());
        $converter->setParseAllDocComments(Config::getValue('polyfill_parse_all_element_doc_comments'));
        $errors = [];
        try {
            $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors, self::maybeGetCache($code_base));
        } catch (\Exception $e) {
            // Generic fallback. TODO: log.
            throw new ParseException('Unexpected Exception of type ' . \get_class($e) . ': ' . $e->getMessage(), 0);
        }
        foreach ($errors as $diagnostic) {
            if ($diagnostic->kind === 0) {
                $diagnostic_error_start_line = 1 + \substr_count($file_contents, "\n", 0, $diagnostic->start);
                $diagnostic_error_message = 'Fallback parser diagnostic error: ' . $diagnostic->message;
                if (!$suppress_parse_errors) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::SyntaxError,
                        $diagnostic_error_start_line,
                        $diagnostic_error_message
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
                static function (Node $node) {
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
}
