<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast\Node;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

/**
 * This will merge inferred variable types from multiple contexts in branched control structures
 * (E.g. if/elseif/else, try/catch, loops, ternary operators, etc.
 */
class ContextMergeVisitor extends KindVisitorImplementation
{
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
     */
    public function __construct(
        Context $context,
        array $child_context_list
    ) {
        $this->context = $context;
        $this->child_context_list = $child_context_list;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $unused_node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $unused_node): Context
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

        if (self::willRemainingStatementsBeAnalyzedAsIfTryMightFail($node)) {
            return $this->combineScopeList([
                $context->getScope(),
                $try_context->getScope()
            ]);
        }
        return $try_context;
    }

    private static function willRemainingStatementsBeAnalyzedAsIfTryMightFail(Node $node): bool
    {
        if ($node->children['finally'] !== null) {
            // We want to analyze finally as if the try block (and one or more of the catch blocks) was or wasn't executed.
            // ... This isn't optimal.
            // A better solution would be to analyze finally{} twice,
            // 1. As if try could fail
            // 2. As if try did not fail, using the latter to analyze statements after the finally{}.
            return true;
        }
        // E.g. after analyzing the following code:
        //      try { $x = expr(); } catch (Exception $e) { echo "Caught"; return; } catch (OtherException $e) { continue; }
        // Phan should infer that $x is guaranteed to be defined.
        foreach ($node->children['catches']->children ?? [] as $catch_node) {
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable, PhanPossiblyUndeclaredProperty this is never null
            if (BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($catch_node->children['stmts'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a context resulting from merging the possible variable types from the catch statements
     * that will fall through.
     */
    public function mergeCatchContext(Node $node): Context
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
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable, PhanPossiblyUndeclaredProperty this is never null
            if (!BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($catch_node->children['stmts'])) {
                $catch_scope_list[] = $scope_list[$i + 1];
            }
        }
        // TODO: Check if try node unconditionally returns.

        // Merge in the types for any variables found in a catch.
        // if ($node->children['finally'] !== null) {
            // If we have to analyze a finally statement later,
            // then be conservative and assume the try statement may or may not have failed.
            // E.g. the below example must have a inferred type of string|false
            //      $x = new stdClass(); try {...; $x = (string)fn(); } catch(Exception $e) { $x = false; }
            // $try_scope = $this->context->getScope();
        // } else {
            // If we don't have to worry about analyzing the finally statement, then assume that the entire try statement succeeded or the a catch statement succeeded.
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $try_scope = \reset($this->child_context_list)->getScope();
        // }

        if (\count($catch_scope_list) === 0) {
            // All of the catch statements will unconditionally rethrow or return.
            // So, after the try and catch blocks (finally is analyzed separately),
            // the context is the same as if the try block finished successfully.
            return $this->context->withScope($try_scope);
        }

        // TODO: Use getVariableMapExcludingScope
        foreach ($try_scope->getVariableMap() as $variable_name => $variable) {
            $variable_name = (string)$variable_name;  // e.g. ${42}
            foreach ($catch_scope_list as $catch_scope) {
                // Merge types if try and catch have a variable in common
                $catch_variable = $catch_scope->getVariableByNameOrNull(
                    $variable_name
                );
                if ($catch_variable) {
                    $variable->setUnionType($variable->getUnionType()->withUnionType(
                        $catch_variable->getUnionType()
                    ));
                }
            }
        }

        // Look for variables that exist in catch, but not try
        foreach ($catch_scope_list as $catch_scope) {
            foreach ($catch_scope->getVariableMap() as $variable_name => $variable) {
                $variable_name = (string)$variable_name;
                if (!$try_scope->hasVariableWithName($variable_name)) {
                    // Note that it can be null
                    $variable->setUnionType($variable->getUnionType()->withType(
                        NullType::instance(false)
                    ));

                    // Add it to the try scope
                    $try_scope->addVariable($variable);
                }
            }
        }

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($try_scope);
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

        // Get the intersection of all types for all versions of
        // the variable from every side of the branch
        $union_type =
            static function (string $variable_name) use ($scope_list): UnionType {
                $previous_type = null;
                $type_list = [];
                // Get a list of all variables with the given name from
                // each scope
                foreach ($scope_list as $scope) {
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

        // Clone the incoming scope so we can modify it
        // with the outgoing merged scope
        $scope = clone($this->context->getScope());

        foreach ($variable_map as $name => $variable) {
            $name = (string)$name;
            // Skip variables that are only partially defined
            if (!$is_defined_on_all_branches($name)) {
                if ($name === Context::VAR_NAME_THIS_PROPERTIES) {
                    $type = $union_type($name)->asNormalizedTypes()->asMappedUnionType(static function (Type $type): Type {
                        if (!$type instanceof ArrayShapeType) {
                            return $type;
                        }
                        $new_field_types = [];
                        foreach ($type->getFieldTypes() as $field_name => $value) {
                            $new_field_types[$field_name] = $value->isDefinitelyUndefined() ? $value : $value->withIsPossiblyUndefined(true);
                        }
                        return ArrayShapeType::fromFieldTypes($new_field_types, $type->isNullable());
                    });
                    $variable = clone($variable);
                    $variable->setUnionType($type);
                    $scope->addVariable($variable);
                    // there are no overrides for $this on at least one branch.
                    // TODO: Could try to combine local overrides with the defaults.
                    continue;
                }
                $variable = clone($variable);
                $variable->setUnionType($union_type($name)->nullableClone()->withIsPossiblyUndefined(true));
                $scope->addVariable($variable);
                continue;
            }

            // Limit the type of the variable to the subset
            // of types that are common to all branches
            $variable = clone($variable);

            $variable->setUnionType(
                $union_type($name)
            );

            // Add the variable to the outgoing scope
            $scope->addVariable($variable);
        }

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($scope);
    }
}
