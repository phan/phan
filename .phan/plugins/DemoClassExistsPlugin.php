<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\Analysis\ConditionVisitorUtil;
use Phan\CodeBase;
use Phan\Exception\FQSENException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\SuppressionCapability;
use Phan\PluginV3\PreAnalyzeNodeCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\Suggestion;

use ast;
use ast\Node;

/**
 * This plugin ignores missing classes/methods within if(class_exists()) {} blocks.
 */
class DemoClassExistsPlugin extends PluginV3 implements
    SuppressionCapability, PreAnalyzeNodeCapability, PostAnalyzeNodeCapability
{
    /**
     * @var array<array<ast\Node,string,array<string>>>
     * Stack of AST_IF_ELEM nodes that change "are we within class_exists()-conditional if{} block?" state.
     */
    public static $withinStack = [];

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @param Context $context @phan-unused-param
     * @param string $issue_type
     * @param int $lineno @phan-unused-param
     * @param list<mixed> $parameters
     * @param ?Suggestion $suggestion @phan-unused-param
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
            // If the class doesn't exist, but we are within if(class_exists()) {} block,
            // then we assume that it exists (providing a stub of this class),
            // because these checks are used for optional features in the code, for example:
            // if(class_exists("SomeClass")) { SomeClass::doSomething(); } else { /* fallback */ }
            // ... here the absence of SomeClass shouldn't trigger an error within that if{} block.
        if ($issue_type === 'PhanUndeclaredClassMethod') {
            $class_name = $parameters[1];

            if (self::isWithinClassExists($class_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @param string $file_path the file to check for suppressions of @phan-unused-param
     * @return array<string,list<int>> Maps 0 or more issue types to a *map* of lines that this plugin is going to suppress.
     * The keys of the map are the lines being suppressed, and the values are the lines *causing* the suppressions (if extracted from comments or nodes)
     *
     * An empty array can be returned if this is unknown.
     */
    public function getIssueSuppressionList(
        CodeBase $code_base,
        string $file_path
    ): array {
        return [];
    }

    /**
     * @return string - name of PluginAwarePreAnalysisVisitor subclass
     */
    public static function getPreAnalyzeNodeVisitorClassName(): string
    {
        return DemoClassExistsVisitor::class;
    }

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return DemoClassExistsCleanup::class;
    }

    /**
     * @param string $class_name Fully qualified class name.
     * @return bool - True if we are within if(class_name()), false otherwise.
     */
    private static function isWithinClassExists(string $class_name): bool {
        foreach (self::$withinStack as $stackRecord) {
            list(, $func_name, $func_params) = $stackRecord;
            if ($func_name === 'class_exists' && $func_params === [ $class_name ]) {
                return true;
            }
        }

        return false;
    }
}

/**
 * This PreAnalysisVisitor determines which AST_IF_ITEM blocks are conditional on class_exists("Something") being true.
 */
class DemoClassExistsVisitor extends PluginAwarePreAnalysisVisitor {
    public function visitIf(Node $node): Context
    {
        $if_branches = $node->children; // Multiple AST_IF_ELEM nodes, representing if/elseif/else blocks.

        // Determine which of the AST_IF_ELEM branches is wrapped in class_exists() condition
        foreach ($if_branches as $branch_node) {
            if (!isset($branch_node->children['cond'])) {
                // Else block.
                continue;
            }

            $cond = $branch_node->children['cond'];
            if (!($cond instanceof ast\Node)) {
                // Condition within if() is a constant, e.g. if(0)
                continue;
            }

            if ($cond->kind === ast\AST_CALL) {
                $raw_function_name = ConditionVisitorUtil::getFunctionName($cond);
                $args = $cond->children['args']->children;

                if ($raw_function_name === 'class_exists' && \count($args) === 1) {
                    $class_name = $args[0];
                    if (is_string($class_name)) {
                        // Found class_exists() with hardcoded class name
                        $this->enterIfClassExists($branch_node, $class_name);
                    }
                }
            }

            // TODO: additionally detect if(!class_exists()) blocks,
            // and then apply enterIfClassExists() to all following elseif/else blocks.
        }

        return $this->context;
    }

    /**
     * @param Node $if_elem_node AST_IF_ELEM block that only runs if class_exists($class_name) is true.
     * @param string $class_name
     */
    private function enterIfClassExists(Node $if_elem_node, string $class_name): void {
        try {
            $class_fqsen = FullyQualifiedClassName::fromStringInContext($class_name, $this->context);
        } catch(FQSENException $_) {
            return;
        }

        if ($this->code_base->hasClassWithFQSEN($class_fqsen)) {
            // Special handling is unnecessary, because this class exists and can be analyzed by Phan.
            // We only record situations when class doesn't exist. (e.g. belongs to optional library,
            // and that library is not present when running Phan)
            return;
        }

        DemoClassExistsPlugin::$withinStack[] = [ $if_elem_node, 'class_exists', [ (string)$class_fqsen ] ];
    }
}

/**
 * This PostAnalysisVisitor cleans obsolete elements of DemoClassExistsPlugin::$withinStack
 * after leaving if/elseif/else blocks that were related to these elements.
 */
class DemoClassExistsCleanup extends PluginAwarePostAnalysisVisitor {

    public function visitIfElem(Node $node): Context
    {
        // Forget some elements in DemoClassExistsPlugin::$withinStack,
        // but only those related to this if{} block
        while (DemoClassExistsPlugin::$withinStack) {
            list($if_elem_node) = end(DemoClassExistsPlugin::$withinStack);
            if ($if_elem_node !== $node) {
                break;
            }

            // Relevant record found, remove it from stack (because the analyzer is exiting this "if" block).
            array_pop(DemoClassExistsPlugin::$withinStack);
        }

        return $this->context;
    }
}
