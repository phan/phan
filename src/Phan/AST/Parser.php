<?php declare(strict_types=1);

namespace Phan\AST;

use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Phan;

use ast\Node;

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
     * @throws \ParseError
     */
    public static function parseCode(CodeBase $code_base, Context $context, string $file_path, string $file_contents, bool $suppress_parse_errors)
    {
        try {
            return \ast\parse_code(
                $file_contents,
                Config::AST_VERSION,
                $file_path
            );
        } catch (\ParseError $native_parse_error) {
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
            try {
                $node = $converter->parseCodeAsPHPAST($file_contents, Config::AST_VERSION, $errors);
            } catch (\PhpParser\Error $fallback_parser_error) {
                // Shouldn't happen, we're using the error collecting parser
                throw new \ParseError('Fallback parser error: ' . $fallback_parser_error->getMessage(), $fallback_parser_error->getCode(), $fallback_parser_error);
            } catch (\Exception $e) {
                // Generic fallback. TODO: log.
                throw $native_parse_error;
            }
            // TODO: loop over $errors?
            return $node;
        }
    }
}
