<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use AssertionError;
use ast;
use ast\Node;
use Exception;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\Method;
use Phan\Language\Element\Variable;
use Phan\Plugin\Internal\VariableTracker\VariableGraph;
use Phan\Plugin\Internal\VariableTracker\VariableTrackerVisitor;
use Phan\Plugin\Internal\VariableTracker\VariableTrackingScope;
use Phan\PluginV2;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\Suggestion;
use function count;
use function is_string;
use function strlen;

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
 * This will analyze any variable definition and uses within function-like scopes,
 * and warn about unused variable definitions.
 *
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
        $scope = new VariableTrackingScope();
        $issue_categories = $this->addParametersAndUseVariablesToGraph($node, $variable_graph, $scope);

        try {
            VariableTrackerVisitor::$variable_graph = $variable_graph;
            $variable_tracker_visitor = new VariableTrackerVisitor($scope);
            // TODO: Add params and use variables.
            $variable_tracker_visitor->__invoke($stmts_node);
        } finally {
            // @phan-suppress-next-line PhanTypeMismatchProperty
            VariableTrackerVisitor::$variable_graph = null;
        }
        $this->warnAboutVariableGraph($node, $variable_graph, $issue_categories);
    }

    /**
     * @return array<int,string> maps unique definition ids to issue types
     */
    private function addParametersAndUseVariablesToGraph(
        Node $node,
        VariableGraph $graph,
        VariableTrackingScope $scope
    ) : array {
        $result = [];
        // AST_PARAM_LIST of AST_PARAM
        foreach ($node->children['params']->children as $parameter) {
            if (!($parameter instanceof Node)) {
                throw new AssertionError("Expected params to be Nodes");
            }
            $parameter_name = $parameter->children['name'];
            if (!is_string($parameter_name)) {
                continue;
            }
            // We narrow this down to the specific category if we need to warn.
            $result[\spl_object_id($parameter)] = Issue::UnusedPublicMethodParameter;

            $graph->recordVariableDefinition($parameter_name, $parameter, $scope);
            if ($parameter->flags & ast\flags\PARAM_REF) {
                $graph->markAsReference($parameter_name);
            }
        }
        foreach ($node->children['uses']->children ?? [] as $closure_use) {
            if (!($closure_use instanceof Node)) {
                throw new AssertionError("Expected uses to be nodes");
            }
            $name = $closure_use->children['name'];
            if (!is_string($name)) {
                continue;
            }
            $result[\spl_object_id($closure_use)] = Issue::UnusedClosureUseVariable;

            $graph->recordVariableDefinition($name, $closure_use, $scope);
            if ($closure_use->flags & ast\flags\PARAM_REF) {
                $graph->markAsReference($name);
            }
        }
        return $result;
    }

    private function methodHasOverrides() : bool
    {
        if (Config::getValue('unused_variable_detection_assume_override_exists')) {
            return true;
        }
        try {
            $method = $this->context->getFunctionLikeInScope($this->code_base);
            if (!($method instanceof Method)) {
                // should never happen
                return false;
            }

            return $method->getIsOverride() || $method->getIsOverriddenByAnother();
        } catch (Exception $_) {
            // should not happen
            return false;
        }
    }
    /**
     * @return string
     */
    private function getParameterCategory(Node $method_node)
    {
        $kind = $method_node->kind;
        if ($kind === ast\AST_CLOSURE) {
            return Issue::UnusedClosureParameter;
        } elseif ($kind === ast\AST_FUNC_DECL) {
            return Issue::UnusedGlobalFunctionParameter;
        }

        $flags = $method_node->flags;
        $final = $this->isParameterFinal($flags);

        if ($flags & ast\flags\MODIFIER_PRIVATE) {
            return $final ? Issue::UnusedPrivateFinalMethodParameter : Issue::UnusedPrivateMethodParameter;
        } elseif ($flags & ast\flags\MODIFIER_PROTECTED) {
            if ($final) {
                return Issue::UnusedProtectedFinalMethodParameter;
            }
            if (!$this->methodHasOverrides()) {
                return Issue::UnusedProtectedNoOverrideMethodParameter;
            }
            return Issue::UnusedProtectedMethodParameter;
        }
        if ($final) {
            return Issue::UnusedPublicFinalMethodParameter;
        }
        if (!$this->methodHasOverrides()) {
            return Issue::UnusedPublicNoOverrideMethodParameter;
        }
        return Issue::UnusedPublicMethodParameter;
    }

    private function isParameterFinal(int $flags) : bool
    {
        if (($flags & ast\flags\MODIFIER_FINAL) !== 0) {
            return true;
        }
        $context = $this->context;
        if ($context->isInClassScope()) {
            try {
                $class = $context->getClassInScope($this->code_base);
                return $class->isFinal();
            } catch (CodeBaseException $_) {
            }
        }
        return false;
    }

    /**
     * @param array<int,string> $issue_overrides_for_definition_ids maps definition ids to issue types
     * @return void
     */
    private function warnAboutVariableGraph(
        Node $method_node,
        VariableGraph $graph,
        $issue_overrides_for_definition_ids
    ) {
        foreach ($graph->def_uses as $variable_name => $def_uses_for_variable) {
            if ($variable_name === 'this') {
                continue;
            }
            if (\preg_match('/^(_$|(unused|raii))/i', $variable_name) > 0) {
                // Skip over $_, $unused*, and $raii*
                continue;
            }
            if (Variable::isSuperglobalVariableWithName($variable_name)) {
                continue;
            }
            $type_bitmask = $graph->variable_types[$variable_name] ?? 0;
            if ($type_bitmask & VariableGraph::IS_REFERENCE_OR_GLOBAL_OR_STATIC) {
                // don't warn about static/global/references
                continue;
            }
            foreach ($def_uses_for_variable as $definition_id => $use_list) {
                if (count($use_list) > 0) {
                    // Don't warn if there's at least one usage of that definition
                    continue;
                }
                $line = $graph->def_lines[$variable_name][$definition_id] ?? 1;
                $issue_type = $issue_overrides_for_definition_ids[$definition_id] ?? Issue::UnusedVariable;
                // Choose a more precise issue type
                if ($issue_type === Issue::UnusedPublicMethodParameter) {
                    // Narrow down issues about parameters into more specific issues
                    $doc_comment = $method_node->children['docComment'] ?? null;
                    if ($doc_comment && \preg_match('/@param[^$]*\$' . \preg_quote($variable_name) . '\b.*@phan-unused-param\b/', $doc_comment)) {
                        // Don't warn about parameters marked with phan-unused-param
                        continue;
                    }
                    $issue_type = $this->getParameterCategory($method_node);
                    if (strpos($issue_type, 'NoOverride') === false && strpos($issue_type, 'MethodParameter') !== false) {
                        $alternate_issue_type = str_replace('MethodParameter', 'NoOverrideMethodParameter', $issue_type);
                        // @phan-suppress-next-line PhanAccessMethodInternal
                        if (Issue::shouldSuppressIssue($this->code_base, $this->context, $alternate_issue_type, $line, [$variable_name], null)) {
                            continue;
                        }
                    }
                } elseif ($graph->isLoopValueDefinitionId($definition_id)) {
                    $issue_type = Issue::UnusedVariableValueOfForeachWithKey;
                } elseif ($graph->isCaughtException($definition_id)) {
                    $issue_type = Issue::UnusedVariableCaughtException;
                }
                Issue::maybeEmitWithParameters(
                    $this->code_base,
                    $this->context,
                    $issue_type,
                    $line,
                    [$variable_name],
                    // compute the suggestion for $variable_name based on the $issue_type
                    self::makeSuggestion($graph, $variable_name, $issue_type)
                );
            }
        }
    }

    /**
     * @return ?Suggestion
     */
    private static function makeSuggestion(VariableGraph $graph, string $variable_name, string $issue_type)
    {
        if ($issue_type !== Issue::UnusedVariable) {
            return null;
        }
        if (strlen($variable_name) <= 1) {
            // No point in guessing short variable names
            return null;
        }
        if (count($graph->def_uses[$variable_name] ?? []) > 1) {
            // We've defined this variable in more than one place, assume it's not a typo
            return null;
        }
        // Take all of the variables that were used anywhere else
        // (don't account for reachability)
        $variable_set = $graph->variable_types;
        // Suggest any variables with a similar name (excluding $variable_name) that were used in this class scope
        // It's possible that the usage just hasn't been typed out yet
        unset($variable_set[$variable_name]);
        if (count($variable_set) > Config::getValue('suggestion_check_limit')) {
            return null;
        }
        $suggestion_set = IssueFixSuggester::getSuggestionsForStringSet($variable_name, $variable_set);
        if (count($suggestion_set) === 0) {
            return null;
        }

        $suggestions = [];
        foreach ($suggestion_set as $suggested_variable_name => $_) {
            $suggestions[] = '$' . $suggested_variable_name;
        }
        \sort($suggestions);

        return Suggestion::fromString('Did you mean ' . \implode(' or ', $suggestions));
    }
}
