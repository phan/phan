<?php declare(strict_types=1);

namespace Phan\AST;

use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\AST\TolerantASTConverter\ParseException;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Phan;

use ast\Node;
use ParseError;

class Parser
{
    /**
     * Parses the code. If $suppress_parse_errors is false, this also emits SyntaxError.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param bool $suppress_parse_errors (If true, don't emit SyntaxError)
     * @return ?Node
     * @throws ParseError
     * @throws ParseException
     */
    public static function parseCode(CodeBase $code_base, Context $context, string $file_path, string $file_contents, bool $suppress_parse_errors)
    {
        try {
            if (Config::getValue('use_polyfill_parser')) {
                // This helper method has its own exception handling.
                // It may throw a ParseException, which is unintentionally not caught here.
                return self::parseCodePolyfill($code_base, $context, $file_path, $file_contents, $suppress_parse_errors);
            }
            return \ast\parse_code(
                $file_contents,
                Config::AST_VERSION,
                $file_path
            );
        } catch (ParseError $native_parse_error) {
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
            $converter->setShouldAddPlaceholders(false);
            $converter->setPHPVersionId(Config::get_closest_target_php_version_id());
            $errors = [];
            try {
                $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors);
            } catch (\Exception $e) {
                // Generic fallback. TODO: log.
                throw $native_parse_error;
            }
            // TODO: loop over $errors?
            return $node;
        }
    }

    /**
     * Parses the code. If $suppress_parse_errors is false, this also emits SyntaxError.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $file_path file path for error reporting
     * @param string $file_contents file contents to pass to parser. May be overridden to ignore what is currently on disk.
     * @param bool $suppress_parse_errors (If true, don't emit SyntaxError)
     * @return ?Node
     * @throws ParseException
     */
    public static function parseCodePolyfill(CodeBase $code_base, Context $context, string $file_path, string $file_contents, bool $suppress_parse_errors)
    {
        $converter = new TolerantASTConverter();
        $converter->setShouldAddPlaceholders(false);
        $converter->setPHPVersionId(Config::get_closest_target_php_version_id());
        $errors = [];
        try {
            $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors);
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
}
