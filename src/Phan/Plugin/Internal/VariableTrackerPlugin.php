<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2;
use Phan\Plugin\Internal\VariableTracker\VariableGraph;
use Phan\Plugin\Internal\VariableTracker\VariableTrackingScope;
use Phan\Plugin\Internal\VariableTracker\VariableTrackerVisitor;
use ast\Node;
use ast;

/**
 * NOTE: This is automatically loaded by phan based on config settings.
 * Do not include it in the 'plugins' config.
 */
final class VariableTrackerPlugin extends PluginV2 implements
    PostAnalyzeNodeCapability
{

    /**
     * @return string the name of a visitor
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return VariableTrackerElementVisitor::class;
    }
}

/**
 * TODO: Hook into the global scope as well?
 */
final class VariableTrackerElementVisitor extends PluginAwarePostAnalysisVisitor
{
    public function visitMethod(Node $node)
    {
        $this->analyzeMethodLike($node);
    }

    /**
     * @override
     */
    public function visitFuncDecl(Node $node)
    {
        $this->analyzeMethodLike($node);
    }

    /**
     * @override
     */
    public function visitClosure(Node $node)
    {
        $this->analyzeMethodLike($node);
    }

    /**
     * @return void
     */
    private function analyzeMethodLike(Node $node)
    {
        // \Phan\Debug::printNode($node);
        $stmts_node = $node->children['stmts'] ?? null;
        if (!($stmts_node instanceof Node)) {
            return;
        }
        $variable_graph = new VariableGraph();
        $this->addParametersAndUseVariablesToGraph($node, $variable_graph);

        try {
            VariableTrackerVisitor::$variable_graph = $variable_graph;
            $variable_tracker_visitor = new VariableTrackerVisitor(
                new VariableTrackingScope()
            );
            // TODO: Add params and use variables.
            $variable_tracker_visitor->__invoke($stmts_node);
        } finally {
            // @phan-suppress-next-line PhanTypeMismatchProperty
            VariableTrackerVisitor::$variable_graph = null;
        }
        $this->warnAboutVariableGraph($node, $variable_graph);
    }

    private function addParametersAndUseVariablesToGraph(Node $node, VariableGraph $graph) {
        // AST_PARAM_LIST of AST_PARAM
        foreach ($node->children['params']->children as $parameter) {
            $parameter_name = $parameter->children['name'];
            if (!is_string($parameter_name)) {
                continue;
            }
            $graph->recordVariableDefinition($parameter_name, $node);
            if ($parameter->flags & ast\flags\PARAM_REF) {
                $graph->markAsReference($parameter_name);
            }
        }
        foreach ($node->children['uses']->children ?? [] as $closure_use) {
            $name = $closure_use->children['name'];
            if (!is_string($name)) {
                continue;
            }
            if ($closure_use->flags & ast\flags\PARAM_REF) {
                $graph->markAsReference($name);
            }
        }
    }

    private function warnAboutVariableGraph(Node $node, VariableGraph $graph)
    {
        foreach ($graph->defs_uses as $variable_name => $defs_uses) {
            $type_bitmask = $graph->variable_types[$variable_name] ?? 0;
            if ($type_bitmask > 0) {
                // don't warn about static/global/references
                continue;
            }
            foreach ($defs_uses as $definition_id => $use_list) {
                if (\count($use_list) > 0) {
                    // Don't warn if there's at least one usage of that definition
                    continue;
                }
                $line = $graph->def_lines[$variable_name][$definition_id] ?? 1;
                // TODO: Emit a different issue type for plugins.
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($line),
                    'PhanUnusedVariable',
                    'Unused definition of variable {VARIABLE}',
                    [$variable_name]
                );
            }
        }
    }
}
