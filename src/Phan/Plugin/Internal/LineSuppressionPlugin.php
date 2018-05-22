<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\PluginV2;
use Phan\PluginV2\SuppressionCapability;
use Phan\Suggestion;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class LineSuppressionPlugin extends PluginV2 implements
    SuppressionCapability
{
    /**
     * @var array<string,array{contents:string,suppressions:array<string,array<int,int>>}>
     * Maps absolute file paths to the most recently known contents and the corresponding suppression lines for issues.
     */
    private $current_suppressions = [];

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
     * @param array<int,string|int|float|bool|Type|UnionType|FQSEN|TypedElement|UnaddressableTypedElement> $parameters @phan-unused-param
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
        $suggestion
    ) : bool {
        $issue_suppression_list = $this->getIssueSuppressionList($code_base, $context->getFile());
        return isset($issue_suppression_list[$issue_type][$lineno]);
    }

    /**
     * @return array<string,array<int,int>> Maps 0 or more issue types to a *list* of lines corresponding to issues that this plugin is going to suppress.
     *
     * This list is used only by UnusedSuppressionPlugin
     *
     * An empty array can be returned if this is unknown.
     */
    public function getIssueSuppressionList(
        CodeBase $code_base,
        string $file_path
    ) : array {
        $absolute_file_path = Config::projectPath($file_path);
        $file_contents = FileCache::getOrReadEntry($absolute_file_path)->getContents();  // This is the recommended way to fetch the file contents

        // This is expensive to compute, so we cache it and recalculate if the file contents for $absolute_file_path change.
        // It will change when Phan is running in language server mode, updating FileCache.
        $cached_suppressions = $this->current_suppressions[$absolute_file_path] ?? null;
        $suppress_issue_list = $cached_suppressions['suppressions'] ?? [];

        if (($cached_suppressions['contents'] ?? null) !== $file_contents) {
            $suppress_issue_list = $this->computeIssueSuggestionList($code_base, $file_contents);
            $this->current_suppressions[$absolute_file_path] = [
                'contents' => $file_contents,
                'suppressions' => $suppress_issue_list,
            ];
        }
        return $suppress_issue_list;
    }

    /**
     * @return array<string,array<int,int>> Maps 0 or more issue types to a *list* of lines corresponding to issues that this plugin is going to suppress.
     */
    private function computeIssueSuggestionList(
        CodeBase $unused_code_base,
        string $file_contents
    ) : array {
        $suggestion_list = [];
        $tokens = \token_get_all($file_contents);
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            $kind = $token[0];
            if ($kind !== T_COMMENT && $kind !== T_DOC_COMMENT) {
                continue;
            }
            $comment_text = $token[1];
            $comment_start_line = $token[2];

            $match_count = preg_match_all('/@phan-suppress-(next|current)-line\s+(\w+(,\s*\w+)*)/', $comment_text, $matches, PREG_OFFSET_CAPTURE);
            if (!$match_count) {
                continue;
            }

            // Support multiple suppressions within a comment. (E.g. for suppressing multiple warnings about a doc comment)
            for ($i = 0; $i < $match_count; $i++) {
                $comment_start_offset = $matches[0][$i][1];  // byte offset
                $is_next_line = $matches[1][$i][0] === 'next';
                $kind_list_text = $matches[2][$i][0];  // byte offset
                '@phan-var int $comment_start_offset';
                $kind_list = array_map('trim', explode(',', $kind_list_text));
                $line = $comment_start_line;
                if ($is_next_line) {
                    $line++;
                }
                $line += substr_count($comment_text, "\n", 0, $comment_start_offset);  // How many lines until that comment?
                foreach ($kind_list as $issue_kind) {
                    // Store the suggestion for the issue kind.
                    // Make this an array set for easier lookup.
                    $suggestion_list[$issue_kind][$line] = $line;
                }
            }
        }
        return $suggestion_list;
    }
}
