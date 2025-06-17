<?php

declare(strict_types=1);

namespace PHPDocRedundantPlugin;

use LogicException;
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
     * @param CodeBase $code_base @unused-param
     */
    public static function fixRedundantFunctionLikeComment(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
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

    private static function computeEditsToRemoveFunctionLikeComment(FileCacheEntry $contents, FunctionLike $declaration, string $encoded_comment): ?FileEditSet
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

    private static function computeEditSetToDeleteComment(string $file_contents, Token $comment_token): FileEditSet
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
     * Delete a list of redundant (at) param annotations.
     * @param CodeBase $code_base @unused-param
    */
    public static function fixRedundantParameterListComment(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $first_line = $instance->getLine();
        $encoded_lines_to_delete = $instance->getTemplateParameters()[1];
        if (!is_string($encoded_lines_to_delete)) {
            throw new LogicException('Issue parameters changed');
        }
        $last_line = $first_line + substr_count($encoded_lines_to_delete, "\\n");
        return self::computeEditSetToDeleteCommentLinesAndBlanks($contents, $contents->getLines(), $first_line, $last_line);
    }

    /**
     * Delete a redundant (at) return annotation.
     * @param CodeBase $code_base @unused-param
     */
    public static function fixRedundantReturnComment(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $lineno = $instance->getLine();
        $file_lines = $contents->getLines();

        $line = \trim($file_lines[$lineno]);
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        if (!\preg_match(Builder::RETURN_COMMENT_REGEX, $line)) {
            return null;
        }
        return self::computeEditSetToDeleteCommentLinesAndBlanks($contents, $file_lines, $lineno, $lineno);
    }

    /**
     * Computes an edit set to delete comment lines in the specified range, plus any surrounding blank comment lines.
     * @param associative-array<int,string> $file_lines
     */
    private static function computeEditSetToDeleteCommentLinesAndBlanks(
        FileCacheEntry $contents,
        array $file_lines,
        int $first_deleted_line,
        int $last_deleted_line
    ): ?FileEditSet {
        $is_blank_comment_line = static function (int $i) use ($file_lines): bool {
            return \trim($file_lines[$i] ?? '') === '*';
        };
        $is_content_comment_line = static function (int $i) use ($file_lines): bool {
            return \trim($file_lines[$i] ?? '', " \r\n\t*/") !== '';
        };

        $empty_lines_before = 0;
        while ($is_blank_comment_line($first_deleted_line - $empty_lines_before - 1)) {
            $empty_lines_before++;
        }
        $has_content_before = $is_content_comment_line($first_deleted_line - $empty_lines_before - 1);

        $empty_lines_after = 0;
        while ($is_blank_comment_line($last_deleted_line + $empty_lines_after + 1)) {
            $empty_lines_after++;
        }
        $has_content_after = $is_content_comment_line($last_deleted_line + $empty_lines_after + 1);

        if ($has_content_before && $has_content_after) {
            // If there is content before and after the (at)param tags, and we found at least one empty line to delete,
            // leave one of the empty lines in place to keep some grouping/separation between tags.
            if ($empty_lines_before > 0) {
                $empty_lines_before--;
            } elseif ($empty_lines_after > 0) {
                $empty_lines_after--;
            }
        }

        $first_deleted_line -= $empty_lines_before;
        $last_deleted_line += $empty_lines_after;

        $start_offset = $contents->getLineOffset($first_deleted_line);
        $end_offset = $contents->getLineOffset($last_deleted_line + 1);
        if (!$start_offset || !$end_offset) {
            return null;
        }
        return new FileEditSet([new FileEdit($start_offset, $end_offset, '')]);
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     * @suppress PhanUndeclaredClassMethod
     * @suppress UnusedSuppression false positive for PhpTokenizer with polyfill due to https://github.com/Microsoft/tolerant-php-parser/issues/292
     */
    private static function getDocCommentToken(PhpParser\Node $node): ?Token
    {
        $leadingTriviaText = $node->getLeadingCommentAndWhitespaceText();
        $leadingTriviaTokens = PhpTokenizer::getTokensArrayFromContent(
            $leadingTriviaText,
            ParseContext::SourceElements,
            $node->getFullStartPosition(),
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
    ): ?FunctionLike {
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
