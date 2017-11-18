<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Type;
use Phan\Language\Context;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StringType;
use Phan\Language\Element\Variable;
use Phan\Language\UnionType;
use ast\Node;

trait ConditionVisitorUtil
{
    protected $code_base;

    /**
     * Remove any types which are definitely truthy from that variable (objects, TrueType, ResourceType, etc.)
     * E.g. if (empty($x)) {} would result in this.
     * Note that Phan can't know some scalars are not an int/string/float, since 0/""/"0"/0.0/[] are empty.
     * (Remove arrays anyway)
     */
    protected final function removeTruthyFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function (UnionType $type) : bool {
                return $type->containsTruthy();
            },
            function (UnionType $type) : UnionType {
                return $type->nonTruthyClone();
            },
            $suppress_issues
        );
    }

    // Remove any types which are definitely falsey from that variable (NullType, FalseType)
    protected final function removeFalseyFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function (UnionType $type) : bool {
                return $type->containsFalsey();
            },
            function (UnionType $type) : UnionType {
                return $type->nonFalseyClone();
            },
            $suppress_issues
        );
    }


    protected final function removeNullFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function (UnionType $type) : bool {
                return $type->containsNullable();
            },
            function (UnionType $type) : UnionType {
                return $type->nonNullableClone();
            },
            $suppress_issues
        );
    }

    protected final function removeFalseFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function (UnionType $type) : bool {
                return $type->containsFalse();
            },
            function (UnionType $type) : UnionType {
                return $type->nonFalseClone();
            },
            false
        );
    }

    protected final function removeTrueFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            function (UnionType $type) : bool {
                return $type->containsTrue();
            },
            function (UnionType $type) : UnionType {
                return $type->nonTrueClone();
            },
            false
        );
    }

    /**
     * If the inferred UnionType makes $should_filter_cb return true
     * (indicating there are Types to be removed from the UnionType or altered),
     * then replace the UnionType with the modified UnionType which $filter_union_type_cb returns,
     * and update the context.
     *
     * Note: It's expected that $should_filter_cb returns false on the new UnionType of that variable.
     */
    protected final function updateVariableWithConditionalFilter(
        Node $var_node,
        Context $context,
        \Closure $should_filter_cb,
        \Closure $filter_union_type_cb,
        bool $suppress_issues
    ) : Context {
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($var_node, $context);
            if (\is_null($variable)) {
                return $context;
            }

            $union_type = $variable->getUnionType();
            if (!$should_filter_cb($union_type)) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                $filter_union_type_cb($union_type)
            );

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            if (!$suppress_issues) {
                Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
            }
        } catch (\Exception $exception) {
            // Swallow it
        }
        return $context;
    }

    protected final function updateVariableWithNewType(
        Node $var_node,
        Context $context,
        UnionType $new_union_type,
        bool $suppress_issues
    ) : Context {
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($var_node, $context);
            if (\is_null($variable)) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                $new_union_type
            );

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            if (!$suppress_issues) {
                Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
            }
        } catch (\Exception $exception) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     */
    protected final function updateVariableToBeIdentical(
        Node $var_node,
        $expr,
        Context $context
    ) : Context {
        $var_name = $var_node->children['name'] ?? null;
        // Don't analyze variables such as $$a
        if (\is_string($var_name) && $var_name) {
            try {
                $exprType = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $context, $expr);
                if ($exprType) {
                    // Get the variable we're operating on
                    $variable = $this->getVariableFromScope($var_node, $context);
                    if (\is_null($variable)) {
                        return $context;
                    }

                    // Make a copy of the variable
                    $variable = clone($variable);

                    $variable->setUnionType($exprType);

                    // Overwrite the variable with its new type in this
                    // scope without overwriting other scopes
                    $context = $context->withScopeVariable(
                        $variable
                    );
                    return $context;
                }
            } catch (\Exception $e) {
                // Swallow it (E.g. IssueException for undefined variable)
            }
        }
        return $context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x !== 'literal')`
     */
    protected final function updateVariableToBeNotIdentical(
        Node $var_node,
        $expr,
        Context $context
    ) : Context {
        $var_name = $var_node->children['name'] ?? null;
        if (\is_string($var_name)) {
            try {
                if ($expr instanceof Node && $expr->kind === \ast\AST_CONST) {
                    $exprNameNode = $expr->children['name'];
                    if ($exprNameNode->kind === \ast\AST_NAME) {
                        // Currently, only add this inference when we're absolutely sure this is a check rejecting null/false/true
                        $exprName = $exprNameNode->children['name'];
                        switch (\strtolower($exprName)) {
                            case 'null':
                                return $this->removeNullFromVariable($var_node, $context, false);
                            case 'false':
                                return $this->removeFalseFromVariable($var_node, $context);
                            case 'true':
                                return $this->removeTrueFromVariable($var_node, $context);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Swallow it (E.g. IssueException for undefined variable)
            }
        }
        return $context;
    }

    /**
     * @return ?Variable - Returns null if the variable is undeclared and ignore_undeclared_variables_in_global_scope applies.
     *                     or if assertions won't be applied?
     * @throws IssueException if variable is undeclared and not ignored.
     * @see UnionTypeVisitor->visitVar
     *
     * TODO: support assertions on superglobals, within the current file scope?
     */
    protected final function getVariableFromScope(Node $var_node, Context $context)
    {
        if ($var_node->kind !== \ast\AST_VAR) {
            return null;
        }
        $var_name_node = $var_node->children['name'] ?? null;

        if ($var_name_node instanceof Node) {
            // This is nonsense. Give up, but check if it's a type other than int/string.
            // (e.g. to catch typos such as $$this->foo = bar;)
            $name_node_type = (new UnionTypeVisitor($this->code_base, $context, true))($var_name_node);
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType();
                $int_or_string_type->addType(StringType::instance(false));
                $int_or_string_type->addType(IntType::instance(false));
                $int_or_string_type->addType(NullType::instance(false));
            }
            if (!$name_node_type->canCastToUnionType($int_or_string_type)) {
                Issue::maybeEmit($this->code_base, $context, Issue::TypeSuspiciousIndirectVariable, $var_name_node->lineno ?? 0, (string)$name_node_type);
            }

            return null;
        }

        $var_name = (string)$var_name_node;

        if (!$context->getScope()->hasVariableWithName($var_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope())) {
                return null;
            }
            if (!Config::getValue('ignore_undeclared_variables_in_global_scope')
                || !$context->isInGlobalScope()
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $context->getFile(),
                        $var_node->lineno ?? 0,
                        [$var_name]
                    )
                );
            }
            return null;
        }
        return $context->getScope()->getVariableByName(
            $var_name
        );
    }

    protected static final function isArgumentListWithVarAsFirstArgument(array $args) : bool
    {
        if (\count($args) >= 1) {
            $arg = $args[0];
            return ($arg instanceof Node) && ($arg->kind === \ast\AST_VAR);
        }
        return false;
    }

    /**
     * Fetches the function name. Does not check for function uses or namespaces.
     * @return ?string (null if function name could not be found)
     */
    protected static final function getFunctionName(Node $node)
    {
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return null;
        }
        $raw_function_name = $expr->children['name'] ?? null;
        if (!(\is_string($raw_function_name) && $raw_function_name)) {
            return null;
        }
        return $raw_function_name;
    }
}
