<?php

declare(strict_types=1);

use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\QualifiedName;
use Phan\AST\TolerantASTConverter\NodeUtils;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\Plugin\Internal\IssueFixingPlugin\IssueFixer;

/**
 * Implements --automatic-fix for NotFullyQualifiedUsagePlugin
 *
 * This is a prototype, there are various features it does not implement.
 */

call_user_func(static function (): void {
    /**
     * @param $code_base @unused-param
     * @return ?FileEditSet a representation of the edit to make to replace a call to a function alias with a call to the original function
     */
    $fix = static function (CodeBase $code_base, FileCacheEntry $contents, IssueInstance $instance): ?FileEditSet {
        $line = $instance->getLine();
        $reason = (string)$instance->getTemplateParameters()[1];
        if (!preg_match('/Deprecated because: DeprecateAliasPlugin marked this as an alias of (\w+)\(\)/', $reason, $match)) {
            return null;
        }
        $new_name = (string)$match[1];

        $function_repr = (string)$instance->getTemplateParameters()[0];
        if (!preg_match('/\\\\(\w+)\(\)/', $function_repr, $match)) {
            return null;
        }
        $expected_name = $match[1];
        $edits = [];
        foreach ($contents->getNodesAtLine($line) as $node) {
            if (!$node instanceof QualifiedName) {
                continue;
            }
            $is_actual_call = $node->parent instanceof CallExpression;
            if (!$is_actual_call) {
                continue;
            }
            $file_contents = $contents->getContents();
            $actual_name = strtolower((new NodeUtils($file_contents))->phpParserNameToString($node));
            if ($actual_name !== $expected_name) {
                continue;
            }
            //fwrite(STDERR, "name is: " . get_class($node->parent) . "\n");

            // They are case-sensitively identical.
            // Generate a fix.
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            $start = $node->getStartPosition();
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            $end = $node->getEndPosition();
            $edits[] = new FileEdit($start, $end, (($file_contents[$start] ?? '') === '\\' ? '\\' : '') . $new_name);
        }
        if ($edits) {
            return new FileEditSet($edits);
        }
        return null;
    };
    IssueFixer::registerFixerClosure(
        'PhanDeprecatedFunctionInternal',
        $fix
    );
});
