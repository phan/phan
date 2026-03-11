<?php

declare(strict_types=1);

namespace CaseMismatchPlugin;

use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * Implements --automatic-fix for CaseMismatchPlugin.
 *
 * Each fixer locates the wrongly-cased token in the source file
 * and replaces it with the correctly-cased version.
 */
class Fixers
{
    /**
     * Fix a class/interface/trait/enum name casing mismatch.
     * Template params: [0] = wrong short name, [1] = correct name.
     * @unused-param $code_base
     */
    public static function fixClassName(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $wrong_name = (string)$params[0];
        $correct_name = (string)$params[1];

        return self::fixQualifiedNameLastPart($contents, $instance->getLine(), $wrong_name, $correct_name);
    }

    /**
     * Fix a function name casing mismatch.
     * Template params: [0] = wrong name + "()", [1] = correct name + "()".
     * @unused-param $code_base
     */
    public static function fixFunctionName(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        // Strip the "()" suffix that the plugin appends to function names
        $wrong_name = substr((string)$params[0], 0, -2);
        $correct_name = substr((string)$params[1], 0, -2);

        return self::fixQualifiedNameLastPart($contents, $instance->getLine(), $wrong_name, $correct_name);
    }

    /**
     * Fix a method name casing mismatch.
     * Template params: [0] = wrong name + "()", [1] = correct name + "()".
     * @unused-param $code_base
     */
    public static function fixMethodName(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $wrong_name = substr((string)$params[0], 0, -2);
        $correct_name = substr((string)$params[1], 0, -2);
        $line = $instance->getLine();
        $file_contents = $contents->getContents();
        $edits = [];

        foreach ($contents->getNodesAtLine($line) as $node) {
            if ($node instanceof MemberAccessExpression || $node instanceof ScopedPropertyAccessExpression) {
                $member_token = $node->memberName;
                if (!$member_token instanceof Token) {
                    continue;
                }
                // Only fix method calls, not property access
                if (!$node->parent instanceof CallExpression) {
                    continue;
                }
                $actual_text = $member_token->getText($file_contents);
                if ($actual_text !== $wrong_name) {
                    continue;
                }
                $start = $member_token->getStartPosition();
                $end = $member_token->getEndPosition();
                $edits[] = new FileEdit($start, $end, $correct_name);
            }
        }

        return $edits ? new FileEditSet($edits) : null;
    }

    /**
     * Fix a namespace segment casing mismatch.
     * Template params: [0] = wrong segment with leading "\", [1] = correct segment with leading "\".
     * @unused-param $code_base
     */
    public static function fixNamespace(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $wrong_segment = ltrim((string)$params[0], '\\');
        $correct_segment = ltrim((string)$params[1], '\\');
        $line = $instance->getLine();
        $file_contents = $contents->getContents();

        foreach ($contents->getNodesAtLine($line) as $node) {
            if (!$node instanceof QualifiedName) {
                continue;
            }
            foreach ($node->nameParts as $part) {
                if (!$part instanceof Token || $part->kind !== TokenKind::Name) {
                    continue;
                }
                $actual_text = $part->getText($file_contents);
                if ($actual_text !== $wrong_segment) {
                    continue;
                }
                $start = $part->getStartPosition();
                $end = $part->getEndPosition();
                return new FileEditSet([new FileEdit($start, $end, $correct_segment)]);
            }
        }

        return null;
    }

    /**
     * Find a QualifiedName node at the given line whose last name part matches
     * $wrong_name, and replace it with $correct_name.
     */
    private static function fixQualifiedNameLastPart(
        FileCacheEntry $contents,
        int $line,
        string $wrong_name,
        string $correct_name
    ): ?FileEditSet {
        $file_contents = $contents->getContents();
        $edits = [];

        foreach ($contents->getNodesAtLine($line) as $node) {
            if (!$node instanceof QualifiedName) {
                continue;
            }
            $last_token = $node->getLastNamePart();
            if (!$last_token instanceof Token) {
                continue;
            }
            $actual_text = $last_token->getText($file_contents);
            if ($actual_text !== $wrong_name) {
                continue;
            }
            $start = $last_token->getStartPosition();
            $end = $last_token->getEndPosition();
            $edits[] = new FileEdit($start, $end, $correct_name);
        }

        return $edits ? new FileEditSet($edits) : null;
    }
}
