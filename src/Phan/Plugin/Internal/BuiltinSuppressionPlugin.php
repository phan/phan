<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Generator;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Phan;
use Phan\PluginV3;
use Phan\PluginV3\SuppressionCapability;
use Phan\Suggestion;
use PhpToken;

/**
 * Implements Phan's built in suppression kinds
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class BuiltinSuppressionPlugin extends PluginV3 implements
    SuppressionCapability
{
    /**
     * @var array<string,array{contents:string,suppressions:array<string,associative-array<int,int>>}>
     * Maps absolute file paths to the most recently known contents and the corresponding suppression lines for issues.
     * (Starts at 1. The index 0 is used for file-based suppressions)
     */
    private $current_line_suppressions = [];

    /**
     * @var array<string,array<string,true>>
     * Maps absolute file paths to the set of file-based suppressions that had an effect.
     */
    private $used_file_based_suppressions = [];

    /**
     * This will be called if both of these conditions hold:
     *
     * 1. Phan's file and element-based suppressions did not suppress the issue
     * 2. Earlier plugins didn't suppress the issue.
     *
     * @param CodeBase $code_base
     *
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param list<string|int|float|bool|Type|UnionType|FQSEN|TypedElement|UnaddressableTypedElement> $parameters @phan-unused-param
     *
     * @param ?Suggestion $suggestion @phan-unused-param
     *
     * @return bool true if the given issue instance should be suppressed, given the current file contents.
     */
    public function shouldSuppressIssue(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        int $lineno,
        array $parameters,
        ?Suggestion $suggestion
    ): bool {
        $issue_suppression_list = $this->getRawIssueSuppressionList($code_base, $context->getFile());
        $suppressions_for_issue_type = $issue_suppression_list[$issue_type] ?? null;
        if (isset($suppressions_for_issue_type[$lineno])) {
            return true;
        }
        if (isset($suppressions_for_issue_type[0])) {
            $this->used_file_based_suppressions[Config::projectPath($context->getFile())][$issue_type] = true;
            return true;
        }
        return false;
    }

    /**
     * @return array<string,list<int>> Maps 0 or more issue types to a *list* of lines corresponding to issues that this plugin is going to suppress.
     *
     * This list is externally used only by UnusedSuppressionPlugin
     *
     * An empty array can be returned if this is unknown.
     */
    public function getIssueSuppressionList(
        CodeBase $code_base,
        string $file_path
    ): array {
        $result = self::getRawIssueSuppressionList($code_base, $file_path);
        $used_file_based_suppression_set = $this->used_file_based_suppressions[$file_path] ?? [];
        foreach ($used_file_based_suppression_set as $issue_kind => $_) {
            unset($result[$issue_kind][0]);
        }
        return $result;
    }

    /**
     * @unused-param $code_base
     * @return array<string,array<int, int>> Maps 0 or more issue types to a *list* of lines corresponding to issues that this plugin is going to suppress.
     *
     * An empty array can be returned if this is unknown.
     *
     * The line number is mapped to the line causing issues to be suppressed on that line.
     * The line number of 0 represents suppressing issues in the entire file.
     */
    public function getRawIssueSuppressionList(
        CodeBase $code_base,
        string $file_path
    ): array {
        if ($file_path === 'internal') {
            return [];
        }
        if (Phan::isExcludedAnalysisFile($file_path)) {
            // Phan might call Issue::maybeEmit on code in vendor.
            // Don't bother loading or tokenizing the code in that case.
            return [];
        }
        $absolute_file_path = Config::projectPath($file_path);
        $file_contents = FileCache::getOrReadEntry($absolute_file_path)->getContents();  // This is the recommended way to fetch the file contents

        // This is expensive to compute, so we cache it and recalculate if the file contents for $absolute_file_path change.
        // It will change when Phan is running in language server mode, updating FileCache.
        $cached_suppressions = $this->current_line_suppressions[$absolute_file_path] ?? null;

        if (($cached_suppressions['contents'] ?? null) === $file_contents) {
            return $cached_suppressions['suppressions'] ?? [];
        }
        $suppress_issue_list = self::computeIssueSuppressionList($file_contents);
        $this->current_line_suppressions[$absolute_file_path] = [
            'contents' => $file_contents,
            'suppressions' => $suppress_issue_list,
        ];
        unset($this->used_file_based_suppressions[$absolute_file_path]);
        return $suppress_issue_list;
    }

    /**
     * @return array<string,array<int, int>> Maps 0 or more issue types to a set of lines corresponding to issues that this plugin is going to suppress.
     *
     * The line number is mapped to the line causing issues to be suppressed on that line.
     * The line number of 0 represents suppressing issues in the entire file.
     */
    private static function computeIssueSuppressionList(
        string $file_contents
    ): array {
        if (!\preg_match(self::SUPPRESS_ISSUE_REGEX, $file_contents)) {
            // If the **file** doesn't contain the regex we're looking for,
            // then none of the comments will.
            // (Much faster than tokenizing and checking tokens in the most common case)
            return [];
        }
        $suppression_list = [];
        foreach (self::yieldSuppressionComments($file_contents) as [
            $comment_text,
            $comment_start_line,
            $comment_start_offset,
            $comment_name,
            $kind_list_text
        ]) {
            $kind_list = \array_map('trim', \explode(',', $kind_list_text));
            foreach ($kind_list as $issue_kind) {
                // Build a map where the line being suppressed is mapped to the line causing the suppression.
                if ($comment_name === 'file-suppress') {
                    if (Config::getValue('disable_file_based_suppression')) {
                        continue;
                    }
                    $suppression_list[$issue_kind][0] = $comment_start_line + \substr_count($comment_text, "\n", 0, $comment_start_offset);
                    continue;
                }
                if (Config::getValue('disable_line_based_suppression')) {
                    continue;
                }
                // TODO: Why isn't the type of $comment_start_line inferred?
                $line = (int)$comment_start_line;
                switch ($comment_name) {
                    case 'suppress-previous-line':
                        $line--;
                        break;
                    case 'suppress-next-line':
                        $line++;
                        break;
                    case 'suppress-next-next-line':
                        $line += 2;
                        break;
                }
                $line += \substr_count($comment_text, "\n", 0, $comment_start_offset);  // How many lines until that comment?
                // Store the suppression for the issue kind.
                // Make this an array set for easier lookup.
                $suppression_list[$issue_kind][$line] = $comment_start_line;
            }
        }
        return $suppression_list;
    }

    // @phan-suppress-next-line PhanAccessClassConstantInternal
    private const SUPPRESS_ISSUE_REGEX = '/@phan-(suppress-(next(?:-next)?|current|previous)-line|file-suppress)\s+' . Builder::SUPPRESS_ISSUE_LIST . '/';

    /**
     * @return Generator<array{0:string,1:int,2:int,3:string,4:string}>
     * yields [$comment_text, $comment_start_line, $comment_start_offset, $comment_name, $kind_list_text];
     */
    private static function yieldSuppressionComments(
        string $file_contents
    ): Generator {
        // @phan-suppress-next-line PhanUndeclaredClassReference
        if (\PHP_VERSION_ID >= 80000 && \class_exists(PhpToken::class) && \method_exists(PhpToken::class, 'tokenize')) {
            return self::yieldSuppressionCommentsPhpToken($file_contents);
        }
        return self::yieldSuppressionCommentsOld($file_contents);
    }

    /**
     * @return Generator<array{0:string,1:int,2:int,3:string,4:string}>
     * yields [$comment_text, $comment_start_line, $comment_start_offset, $comment_name, $kind_list_text];
     * (using the faster PhpToken::tokenize())
     */
    private static function yieldSuppressionCommentsPhpToken(
        string $file_contents
    ): Generator {
        // @phan-suppress-next-line PhanUndeclaredClassMethod missing in php 8 before 8.0.0RC4
        foreach (PhpToken::tokenize($file_contents) as $token) {
            $kind = $token->id;
            if ($kind !== \T_COMMENT && $kind !== \T_DOC_COMMENT) {
                continue;
            }
            $comment_text = $token->text;
            if (\strpos($comment_text, '@phan-') === false) {
                continue;
            }
            yield from self::yieldSuppressionCommentsFromTokenContents(
                $comment_text,
                $token->line
            );
        }
    }

    /**
     * @return Generator<array{0:string,1:int,2:int,3:string,4:string}>
     * yields [$comment_text, $comment_start_line, $comment_start_offset, $comment_name, $kind_list_text];
     * (using the slower token_get_all())
     */
    private static function yieldSuppressionCommentsOld(
        string $file_contents
    ): Generator {
        foreach (\token_get_all($file_contents) as $token) {
            if (!\is_array($token)) {
                continue;
            }
            $kind = $token[0];
            if ($kind !== \T_COMMENT && $kind !== \T_DOC_COMMENT) {
                continue;
            }
            $comment_text = $token[1];
            if (\strpos($comment_text, '@phan-') === false) {
                continue;
            }
            yield from self::yieldSuppressionCommentsFromTokenContents(
                $comment_text,
                $token[2]
            );
        }
    }

    /**
     * @return list<array{0:string,1:int,2:int,3:string,4:string}>
     * returns list of [$comment_text, $comment_start_line, $comment_start_offset, $comment_name, $kind_list_text];
     */
    private static function yieldSuppressionCommentsFromTokenContents(
        string $comment_text,
        int $comment_start_line
    ): array {
        // TODO: Emit UnextractableAnnotation if the string begins with phan-suppress or phan-file-suppress but nothing matched
        $match_count = \preg_match_all(
            self::SUPPRESS_ISSUE_REGEX,
            $comment_text,
            $matches,
            \PREG_OFFSET_CAPTURE
        );
        $result = [];

        // Support multiple suppressions within a comment. (E.g. for suppressing multiple warnings about a doc comment)
        for ($i = 0; $i < $match_count; $i++) {
            $comment_start_offset = $matches[0][$i][1];  // byte offset
            $comment_name = $matches[1][$i][0];
            $kind_list_text = $matches[3][$i][0];  // byte offset

            $result[] = [$comment_text, $comment_start_line, $comment_start_offset, $comment_name, $kind_list_text];
        }
        return $result;
    }
}
