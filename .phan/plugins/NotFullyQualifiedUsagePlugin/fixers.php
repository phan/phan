<?php declare(strict_types=1);

use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\QualifiedName;
use Phan\AST\TolerantASTConverter\NodeUtils;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\Plugin\Internal\IssueFixingPlugin\IssueFixer;

/**
 * Implements --automatic-fix for NotFullyQualifiedUsagePlugin
 *
 * This is a prototype, there are various features it does not implement.
 */
call_user_func(static function () : void {
    /**
     * @return ?FileEditSet
     */
    $fix = static function (CodeBase $code_base, FileCacheEntry $contents, IssueInstance $instance) : ?FileEditSet {
        $line = $instance->getLine();
        $expected_name = $instance->getTemplateParameters()[0];
        $edits = [];
        foreach ($contents->getNodesAtLine($line) as $node) {
            if (!$node instanceof QualifiedName) {
                continue;
            }
            if ($node->globalSpecifier || $node->relativeSpecifier) {
                // This is already qualified
                continue;
            }
            $actual_name = (new NodeUtils($contents->getContents()))->phpParserNameToString($node);
            if ($actual_name !== $expected_name) {
                continue;
            }
            $is_actual_call = $node->parent instanceof CallExpression;
            $is_expected_call = $instance->getIssue()->getType() !== NotFullyQualifiedUsageVisitor::NotFullyQualifiedGlobalConstant;
            if ($is_actual_call !== $is_expected_call) {
                IssueFixer::debug("skip check mismatch actual expected are call vs constants\n");
                // don't warn about constants with the same names as functions or vice-versa
                continue;
            }
            try {
                if ($is_expected_call) {
                    // Don't do this if the global function this refers to doesn't exist.
                    // TODO: Support namespaced functions
                    if (!$code_base->hasFunctionWithFQSEN(FullyQualifiedFunctionName::fromFullyQualifiedString($actual_name))) {
                        IssueFixer::debug("skip attempt to fix $actual_name() because function was not found in the global scope\n");
                        return null;
                    }
                } else {
                    // Don't do this if the global function this refers to doesn't exist.
                    // TODO: Support namespaced functions
                    if (!$code_base->hasGlobalConstantWithFQSEN(FullyQualifiedGlobalConstantName::fromFullyQualifiedString($actual_name))) {
                        IssueFixer::debug("skip attempt to fix $actual_name because the constant was not found in the global scope\n");
                        return null;
                    }
                }
            } catch (Exception $_) {
                continue;
            }
            //fwrite(STDERR, "name is: " . get_class($node->parent) . "\n");

            // They are case-sensitively identical.
            // Generate a fix.
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            $start = $node->getStart();
            $edits[] = new FileEdit($start, $start, '\\');
        }
        if ($edits) {
            return new FileEditSet($edits);
        }
        return null;
    };
    IssueFixer::registerFixerClosure(
        NotFullyQualifiedUsageVisitor::NotFullyQualifiedGlobalConstant,
        $fix
    );
    IssueFixer::registerFixerClosure(
        NotFullyQualifiedUsageVisitor::NotFullyQualifiedFunctionCall,
        $fix
    );
    IssueFixer::registerFixerClosure(
        NotFullyQualifiedUsageVisitor::NotFullyQualifiedOptimizableFunctionCall,
        $fix
    );
});
