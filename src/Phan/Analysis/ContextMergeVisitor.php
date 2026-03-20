<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast\Node;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\Plugin\ConfigPluginSet;

/**
 * This will merge inferred variable types from multiple contexts in branched control structures
 * (E.g. if/elseif/else, try/catch, loops, ternary operators, etc.
 */
class ContextMergeVisitor extends KindVisitorImplementation
{
    /** @var ?CodeBase */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exists.
     */
    private $context;

    /**
     * @var list<Context>
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     */
    private $child_context_list;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param list<Context> $child_context_list
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     * @param ?CodeBase $code_base
     * The code base within which we're operating (optional)
     */
    public function __construct(
        Context $context,
        array $child_context_list,
        ?CodeBase $code_base = null
    ) {
        $this->context = $context;
        $this->child_context_list = $child_context_list;
        $this->code_base = $code_base;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node @unused-param
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node): Context
    {
        // TODO: if ($this->context->isInGlobalScope()) {
        //            copy local to global
        //       }

        return \end($this->child_context_list) ?: $this->context;
    }

    /**
     * Merges the only try block of a try/catch node into the parent context.
     * This acts as though the entire block succeeds or throws on the first statement, which isn't necessarily the case.
     *
     * visitTry() was split out into multiple functions for the following reasons:
     *
     * 1. The try{} block affects the Context of catch blocks (and finally block), if any
     * 2. The catch blocks affect the Context of the finally block, if any
     *
     * TODO: Look at ways to improve accuracy based on inferences of the exit status of the node?
     */
    public function mergeTryContext(Node $node): Context
    {
        if (\count($this->child_context_list) !== 1) {
            throw new AssertionError("Expected one child context in " . __METHOD__);
        }

        // Get the list of scopes for each branch of the
        // conditional
        $context = $this->context;
        $try_context = $this->child_context_list[0];

        if (self::willRemainingStatementsBeAnalyzedAsIfTryMightFail($node, $this->code_base, $context)) {
            return $this->combineScopeList([
                $context->getScope(),
                $try_context->getScope()
            ]);
        }
        return $try_context;
    }

    private static function willRemainingStatementsBeAnalyzedAsIfTryMightFail(Node $node, ?CodeBase $code_base, Context $context): bool
    {
        if ($node->children['finally'] !== null) {
            // We want to analyze finally as if the try block (and one or more of the catch blocks) was or wasn't executed.
            // ... This isn't optimal.
            // A better solution would be to analyze finally{} twice,
            // 1. As if try could fail
            // 2. As if try did not fail, using the latter to analyze statements after the finally{}.
            return true;
        }
        $catch_nodes = $node->children['catches']->children ?? [];
        if (!$catch_nodes) {
            // If there are no catches, be conservative and assume the try might fail.
            return true;
        }
        // E.g. after analyzing the following code:
        //      try { $x = expr(); } catch (Exception $e) { echo "Caught"; return; } catch (OtherException $e) { continue; }
        // Phan should infer that $x is guaranteed to be defined only if every catch unconditionally exits.
        foreach ($catch_nodes as $catch_node) {
            if (!($catch_node instanceof Node)) {
                continue;
            }
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
            if (!BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($catch_node->children['stmts'], $code_base, $context)) {
                // At least one catch may fall through, so analyze as if the try might fail.
                return true;
            }
        }
        // All catches unconditionally exit, so we can analyze remaining statements as if the try succeeded.
        return false;
    }

    /**
     * Returns a context resulting from merging the possible variable types from the catch statements
     * that will fall through.
     */
    public function mergeCatchContext(Node $node, bool $try_statement_will_throw_or_return = false): Context
    {
        if (\count($this->child_context_list) < 2) {
            throw new AssertionError("Expected at least two contexts in " . __METHOD__);
        }
        // Get the list of scopes for each branch of the
        // conditional
        $scope_list = \array_map(static function (Context $context): Scope {
            return $context->getScope();
        }, $this->child_context_list);

        $catch_scope_list = [];
        $catch_nodes = $node->children['catches']->children;
        foreach ($catch_nodes as $i => $catch_node) {
            if (!$catch_node instanceof Node) {
                continue;
            }
            $catch_context = $this->child_context_list[$i + 1] ?? null;
            $catch_stmts_node = $catch_node->children['stmts'];
            if ($catch_stmts_node instanceof Node &&
                !BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($catch_stmts_node, $this->code_base, $catch_context ?? $this->context)) {
                $catch_scope_list[] = $scope_list[$i + 1];
            }
        }
        // TODO: Check if try node unconditionally returns.

        // Use the context scope that was already processed by mergeTryContext.
        // This preserves the "possibly undefined" flags for variables defined only in the try block.
        // $this->context was set by mergeTryContext and already has variables from the try block
        // marked as possibly undefined (if applicable).
        $merged_try_scope = clone($this->context->getScope());

        // Get the raw try scope (without possibly undefined flags) for merging types
        $raw_try_scope = \reset($this->child_context_list)->getScope();

        if (!$catch_scope_list) {
            // All of the catch statements will unconditionally rethrow or return.
            // So, after the try and catch blocks (finally is analyzed separately),
            // the context is the same as the merged try context (which may have possibly undefined variables).
            return $this->context;
        }

        if (\count($catch_scope_list) > 1) {
            $catch_scope = $this->combineScopeList($catch_scope_list)->getScope();
        } else {
            $catch_scope = \reset($catch_scope_list);
        }

        // Merge types from catch blocks into the merged try scope
        // We use $raw_try_scope to check which variables were in the try block,
        // but we modify $merged_try_scope to preserve possibly undefined flags
        foreach ($raw_try_scope->getVariableMap() as $variable_name => $raw_variable) {
            $variable_name = (string)$variable_name;  // e.g. ${42}
            $merged_variable = $merged_try_scope->getVariableByNameOrNull($variable_name);
            if (!$merged_variable) {
                // Variable was in raw try but not in merged scope (shouldn't happen, but be defensive)
                continue;
            }
            // Merge types if try and catch have a variable in common
            $catch_variable = $catch_scope->getVariableByNameOrNull($variable_name);
            if ($catch_variable) {
                $merged_union_type = $merged_variable->getUnionType();
                $catch_union_type = $catch_variable->getUnionType();
                $raw_union_type = $raw_variable->getUnionType();
                $was_definitely_undefined = $merged_union_type->isDefinitelyUndefined();
                $was_possibly_undefined = !$was_definitely_undefined && $merged_union_type->isPossiblyUndefined();
                $catch_defines_variable = !$catch_union_type->isPossiblyUndefined() && !$catch_union_type->isDefinitelyUndefined();

                if ($catch_defines_variable) {
                    $new_union_type = $raw_union_type->withUnionType($catch_union_type);
                    if (!$raw_union_type->containsNullableOrUndefined() && !$catch_union_type->containsNullableOrUndefined()) {
                        $new_union_type = $new_union_type->nonNullableClone();
                    }
                } else {
                    $new_union_type = $merged_union_type->withUnionType($catch_union_type);
                    if ($was_definitely_undefined && ($catch_union_type->isDefinitelyUndefined() || $catch_union_type->isPossiblyUndefined())) {
                        $new_union_type = $new_union_type->withIsDefinitelyUndefined();
                    } elseif ($was_possibly_undefined && ($catch_union_type->isPossiblyUndefined() || $catch_union_type->isDefinitelyUndefined())) {
                        $new_union_type = $new_union_type->withIsPossiblyUndefined(true);
                    }
                    if (($was_definitely_undefined || $was_possibly_undefined) && !$raw_union_type->containsNullableOrUndefined() && !$catch_union_type->containsNullableOrUndefined()) {
                        $new_union_type = $new_union_type->nonNullableClone();
                    }
                }
                $merged_variable->setUnionType($new_union_type);
            }
        }

        // Look for variables that exist in catch, but not try
        // (unless the try statement unconditionally throws, returns, exits, infinitely loops, etc.)
        if ($try_statement_will_throw_or_return) {
            foreach ($catch_scope->getVariableMap() as $variable) {
                // Add it to the merged try scope
                $merged_try_scope->addVariable($variable);
            }
        } else {
            foreach ($catch_scope->getVariableMap() as $variable_name => $variable) {
                $variable_name = (string)$variable_name;
                if (!$raw_try_scope->hasVariableWithName($variable_name)) {
                    $type = $variable->getUnionType();
                    if (!$type->containsNullableLabeled()) {
                        $type = $type->withType(NullType::instance(false));
                    }
                    // Note that it can be null
                    // TODO: This still infers the wrong type when there are multiple catch blocks.
                    // Combine all of the catch blocks into one context and merge with that instead?
                    $variable->setUnionType($type->withIsPossiblyUndefined(true));

                    // Add it to the merged try scope
                    $merged_try_scope->addVariable($variable);
                }
            }
        }

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($merged_try_scope);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIf(Node $node): Context
    {
        // Get the list of scopes for each branch of the
        // conditional
        $scope_list = \array_map(static function (Context $context): Scope {
            return $context->getScope();
        }, $this->child_context_list);

        $has_else = self::hasElse($node->children);

        // If we're not guaranteed to hit at least one
        // branch, mark the incoming scope as a possibility
        if (!$has_else) {
            $scope_list[] = $this->context->getScope();
        }

        // If there weren't multiple branches, continue on
        // as if the conditional never happened
        if (\count($scope_list) < 2) {
            // @phan-suppress-next-line PhanPossiblyFalseTypeReturn child_context_list is not empty
            return \reset($this->child_context_list);
        }

        return $this->combineScopeList($scope_list);
    }

    /**
     * Similar to visitIf, but only includes contexts up to (and including) the first context inferred to be unconditionally true.
     */
    public function mergePossiblySingularChildContextList(): Context
    {
        // Get the list of scopes for each branch of the
        // conditional
        $scope_list = \array_map(static function (Context $context): Scope {
            return $context->getScope();
        }, $this->child_context_list);

        // If there weren't multiple branches, continue on
        // as if the conditional never happened
        if (\count($scope_list) < 2) {
            // @phan-suppress-next-line PhanPossiblyFalseTypeReturn child_context_list is not empty
            return \reset($this->child_context_list);
        }

        return $this->combineScopeList($scope_list);
    }

    /**
     * @param array<mixed,Node|mixed> $children children of a Node of kind AST_IF
     */
    private static function hasElse(array $children): bool
    {
        foreach ($children as $child_node) {
            if ($child_node instanceof Node
                && \is_null($child_node->children['cond'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * A generic helper method to merge multiple Contexts. (e.g. for use outside of BlockAnalysisVisitor)
     * If you wish to include the base context, add it to $child_context_list in the constructor of ContextMergeVisitor.
     */
    public function combineChildContextList(): Context
    {
        $child_context_list = $this->child_context_list;
        if (\count($child_context_list) < 2) {
            throw new AssertionError("Expected at least two child contexts in " . __METHOD__);
        }
        $scope_list = \array_map(static function (Context $context): Scope {
            return $context->getScope();
        }, $child_context_list);
        return $this->combineScopeList($scope_list);
    }

    /**
     * Returns a new scope which combines the parent scope with a list of 2 or more child scopes
     * (one of those scopes is permitted to be the parent scope)
     * @param list<Scope> $scope_list
     * @suppress PhanAccessPropertyInternal Repeatedly using ConfigPluginSet::$mergeVariableInfoClosure
     */
    public function combineScopeList(array $scope_list): Context
    {
        if (\count($scope_list) < 2) {
            throw new AssertionError("Expected at least two child contexts in " . __METHOD__);
        }
        // Get a list of all variables in all scopes
        $variable_map = Scope::getDifferingVariables($scope_list);
        if (!$variable_map) {
            return $this->context->withClonedScope();
        }

        // A function that determines if a variable is defined on
        // every branch
        $is_defined_on_all_branches =
            function (string $variable_name) use ($scope_list): bool {
                foreach ($scope_list as $scope) {
                    $variable = $scope->getVariableByNameOrNull($variable_name);
                    if (\is_object($variable)) {
                        if (!$variable->getUnionType()->isPossiblyUndefined()) {
                            continue;
                        }
                        // fall through and check if this is a superglobal or global
                    }
                    // When there are conditions on superglobals or hardcoded globals,
                    // then one scope will have a copy of the variable but not the other.
                    if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                        $scope->addVariable(new Variable(
                            $this->context,
                            $variable_name,
                            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
                            Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name),
                            0
                        ));
                        return true;
                    }
                    return false;
                }
                return true;
            };

        /** @param list<Scope> $scopes */
        $compute_union_type = static function (string $variable_name, array $scopes): UnionType {
            $previous_type = null;
            $type_list = [];
                // Get a list of all variables with the given name from
                // each scope
                foreach ($scopes as $scope) {
                    $variable = $scope->getVariableByNameOrNull($variable_name);
                    if (\is_null($variable)) {
                        continue;
                    }

                    $type = $variable->getUnionType();
                    // Frequently, a branch won't even modify a variable's type.
                    // The immutable UnionType might have the exact same instance
                    if ($type !== $previous_type) {
                        $type_list[] = $type;

                        $previous_type = $type;
                    }
                }

                if (\count($type_list) < 2) {
                    return $type_list[0] ?? UnionType::empty();
                } else {
                    // compute the un-normalized types
                    $result = UnionType::merge($type_list, $variable_name !== Context::VAR_NAME_THIS_PROPERTIES);
                }

                $result_count = $result->typeCount();
                foreach ($type_list as $type) {
                    if ($type->typeCount() < $result_count) {
                        // normalize it if any of the types varied
                        // (i.e. one of the types lacks types in the type union)
                        //
                        // This is useful to avoid ending up with "bool|?false|true" (Will convert to "?bool")
                        return $result->asNormalizedTypes();
                    }
                }
                // Otherwise, don't normalize it - The different contexts didn't differ in the union types
                return $result;
            };

        $union_type = static function (string $variable_name) use ($scope_list, $compute_union_type): UnionType {
            return $compute_union_type($variable_name, $scope_list);
        };
        $parent_scope = $this->context->getScope();
        $union_type_with_parent = static function (string $variable_name) use ($scope_list, $compute_union_type, $parent_scope): UnionType {
            $extended_scope_list = $scope_list;
            $extended_scope_list[] = $parent_scope;
            return $compute_union_type($variable_name, $extended_scope_list);
        };

        // Clone the incoming scope so we can modify it
        // with the outgoing merged scope
        $scope = clone($this->context->getScope());

        foreach ($variable_map as $name => $variable) {
            $name = (string)$name;
            // Skip variables that are only partially defined
            if (!$is_defined_on_all_branches($name)) {
                if ($name === Context::VAR_NAME_THIS_PROPERTIES) {
                    $type = $union_type_with_parent($name)->asNormalizedTypes()->asMappedUnionType(static function (Type $type): Type {
                        if (!$type instanceof ArrayShapeType) {
                            return $type;
                        }
                        $new_field_types = [];
                        foreach ($type->getFieldTypes() as $field_name => $value) {
                            $new_field_types[$field_name] = $value->isDefinitelyUndefined() ? $value : $value->withIsPossiblyUndefined(true);
                        }
                        return ArrayShapeType::fromFieldTypes($new_field_types, $type->isNullable());
                    });
                    $existing_override = $this->context->getScope()->getVariableByNameOrNull($name);
                    if ($existing_override) {
                        $type = $type->withUnionType($existing_override->getUnionType());
                    }
                    $type = $type->withIsPossiblyUndefined(true);  // Also mark the union type itself as possibly undefined
                    $variable = clone($variable);
                    $variable->setUnionType($type);
                    $scope->addVariable($variable);
                    // there are no overrides for $this on at least one branch.
                    // TODO: Could try to combine local overrides with the defaults.
                    continue;
                }
                $variable = clone($variable);
                $variable->setUnionType($union_type($name)->withIsPossiblyUndefined(true));
                if (ConfigPluginSet::$mergeVariableInfoClosure) {
                    // @phan-suppress-next-line PhanTypePossiblyInvalidCallable
                    (ConfigPluginSet::$mergeVariableInfoClosure)($variable, $scope_list, false);
                }
                $scope->addVariable($variable);
                continue;
            }

            // Limit the type of the variable to the subset
            // of types that are common to all branches
            $variable = clone($variable);

            $variable->setUnionType(
                $union_type($name)
            );
            if (ConfigPluginSet::$mergeVariableInfoClosure) {
                // @phan-suppress-next-line PhanTypePossiblyInvalidCallable
                (ConfigPluginSet::$mergeVariableInfoClosure)($variable, $scope_list, true);
            }

            // Add the variable to the outgoing scope
            $scope->addVariable($variable);
        }

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($scope);
    }
}
