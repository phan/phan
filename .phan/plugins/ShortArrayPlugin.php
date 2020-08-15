<?php

declare(strict_types=1);

use ast\Node;
use Phan\Config;
use Phan\Issue;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Demo plugin to suggest using short array syntax.
 *
 * TODO: Implement a fixer if possible, e.g. base it on token_get_all()
 */
class ShortArrayPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return ShortArrayVisitor::class;
    }
}

/**
 * This class has visitArray called on all array literals in files to suggest using short arrays instead
 */
class ShortArrayVisitor extends PluginAwarePostAnalysisVisitor
{
    // Do not define the visit() method unless a plugin has code and needs to visit most/all node types.

    /**
     * @param Node $node
     * An array literal(AST_ARRAY) node to analyze
     * @override
     */
    public function visitArray(Node $node): void
    {
        switch ($node->flags) {
            case \ast\flags\ARRAY_SYNTAX_LONG:
                $this->emit(
                    'PhanPluginShortArray',
                    'Should use [] instead of array()',
                    [],
                    Issue::SEVERITY_LOW,
                    Issue::REMEDIATION_A
                );
                return;
            case \ast\flags\ARRAY_SYNTAX_LIST:
                if (Config::get_closest_minimum_target_php_version_id() >= 70100) {
                    $this->emit(
                        'PhanPluginShortArrayList',
                        'Should use [] instead of list()',
                        [],
                        Issue::SEVERITY_LOW,
                        Issue::REMEDIATION_A
                    );
                }
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new ShortArrayPlugin();
