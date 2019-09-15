<?php declare(strict_types=1);

namespace PHPDocRedundantPlugin;

use Microsoft\PhpParser;
use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\Node\Expression\AnonymousFunctionCreationExpression;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;
use Microsoft\PhpParser\ParseContext;
use Microsoft\PhpParser\PhpTokenizer;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;
use Phan\AST\TolerantASTConverter\NodeUtils;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Element\Comment\Builder;
use Phan\Library\FileCacheEntry;
use Phan\Library\StringUtil;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * This plugin implements --automatic-fix for PHPDocRedundantPlugin
 */
class Fixers
{
    /**
     * Remove a redundant phpdoc return type from the real signature
     */
    public static function fixRedundantFunctionLikeComment(
        CodeBase $unused_code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ) : ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $name = $params[0];
        $encoded_comment = $params[1];
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsToRemoveFunctionLikeComment($contents, $declaration, (string)$encoded_comment);
    }

    private static function computeEditsToRemoveFunctionLikeComment(FileCacheEntry $contents, FunctionLike $declaration, string $encoded_comment) : ?FileEditSet
    {
        if (!$declaration instanceof PhpParser\Node) {
            // impossible
            return null;
        }
        $comment_token = self::getDocCommentToken($declaration);
        if (!$comment_token) {
            return null;
        }
        $file_contents = $contents->getContents();
        $comment = $comment_token->getText($file_contents);
        $actual_encoded_comment = StringUtil::encodeValue($comment);
        if ($actual_encoded_comment !== $encoded_comment) {
            return null;
        }
        return self::computeEditSetToDeleteComment($file_contents, $comment_token);
    }

    private static function computeEditSetToDeleteComment(string $file_contents, Token $comment_token) : ?FileEditSet
    {
        // get the byte where the `)` of the argument list ends
        $last_byte_index = $comment_token->getEndPosition();
        $first_byte_index = $comment_token->start;
        // Skip leading whitespace and the previous newline, if those were found
        for (; $first_byte_index > 0; $first_byte_index--) {
            $prev_byte = $file_contents[$first_byte_index - 1];
            switch ($prev_byte) {
                case " ":
                case "\t":
                    // keep skipping previous bytes of whitespace
                    break;
                case "\n":
                    $first_byte_index--;
                    if ($first_byte_index > 0 && $file_contents[$first_byte_index - 1] === "\r") {
                        $first_byte_index--;
                    }
                    break 2;
                case "\r":
                    $first_byte_index--;
                    break 2;
                default:
                    // This is not whitespace, so stop.
                    break 2;
            }
        }
        $file_edit = new FileEdit($first_byte_index, $last_byte_index, '');
        return new FileEditSet([$file_edit]);
    }

    /**
     * Add a missing return type to the real signature
     */
    public static function fixRedundantReturnComment(
        CodeBase $unused_code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ) : ?FileEditSet {
        $lineno = $instance->getLine();
        $file_lines = $contents->getLines();

        $line = \trim($file_lines[$lineno]);
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        if (!\preg_match(Builder::RETURN_COMMENT_REGEX, $line)) {
            return null;
        }
        $first_deleted_line = $lineno;
        $last_deleted_line = $lineno;
        $is_blank_comment_line = static function (int $i) use ($file_lines) : bool {
            return \trim($file_lines[$i] ?? '') === '*';
        };
        while ($is_blank_comment_line($first_deleted_line - 1)) {
            $first_deleted_line--;
        }
        while ($is_blank_comment_line($last_deleted_line + 1)) {
            $last_deleted_line++;
        }
        $start_offset = $contents->getLineOffset($first_deleted_line);
        $end_offset = $contents->getLineOffset($last_deleted_line + 1);
        if (!$start_offset || !$end_offset) {
            return null;
        }
        // Return an edit to delete the `(at)return RedundantType` and the surrounding blank comment lines
        return new FileEditSet([new FileEdit($start_offset, $end_offset, '')]);
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     * @suppress PhanUndeclaredClassMethod
     * @suppress UnusedSuppression false positive for PhpTokenizer with polyfill due to https://github.com/Microsoft/tolerant-php-parser/issues/292
     */
    private static function getDocCommentToken(PhpParser\Node $node) : ?Token
    {
        $leadingTriviaText = $node->getLeadingCommentAndWhitespaceText();
        $leadingTriviaTokens = PhpTokenizer::getTokensArrayFromContent(
            $leadingTriviaText,
            ParseContext::SourceElements,
            $node->getFullStart(),
            false
        );
        for ($i = \count($leadingTriviaTokens) - 1; $i >= 0; $i--) {
            $token = $leadingTriviaTokens[$i];
            if ($token->kind === TokenKind::DocCommentToken) {
                return $token;
            }
        }
        return null;
    }

    private static function findFunctionLikeDeclaration(
        FileCacheEntry $contents,
        int $line,
        string $name
    ) : ?FunctionLike {
        $candidates = [];
        foreach ($contents->getNodesAtLine($line) as $node) {
            if ($node instanceof FunctionDeclaration || $node instanceof MethodDeclaration) {
                $name_node = $node->name;
                if (!$name_node) {
                    continue;
                }
                $declaration_name = (new NodeUtils($contents->getContents()))->tokenToString($name_node);
                if ($declaration_name === $name) {
                    $candidates[] = $node;
                }
            } elseif ($node instanceof AnonymousFunctionCreationExpression) {
                if (\preg_match('/^Closure\(/', $name)) {
                    $candidates[] = $node;
                }
            }
        }
        if (\count($candidates) === 1) {
            return $candidates[0];
        }
        return null;
    }
}
