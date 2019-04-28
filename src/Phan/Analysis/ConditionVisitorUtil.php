<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Closure;
use Phan\Analysis\ConditionVisitor\BinaryCondition;
use Phan\Analysis\ConditionVisitor\ComparisonCondition;
use Phan\Analysis\ConditionVisitor\IdenticalCondition;
use Phan\Analysis\ConditionVisitor\NotEqualsCondition;
use Phan\Analysis\ConditionVisitor\NotIdenticalCondition;
use Phan\AST\UnionTypeVisitor;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\LiteralTypeInterface;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use function is_int;
use function is_string;

/**
 * This implements common functionality to update variables based on checks within a conditional (of an if/elseif/else/while/for/assert(), etc.)
 *
 * Classes implementing this must also implement ConditionVisitorInterface
 *
 * @see ConditionVisitor
 * @see NegatedConditionVisitor
 * @see ConditionVisitorInterface
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
 */
trait ConditionVisitorUtil
{
    /** @var CodeBase The code base within which we're operating */
    protected $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    protected $context;

    /**
     * Remove any types which are definitely truthy from that variable (objects, TrueType, ResourceType, etc.)
     * E.g. if (empty($x)) {} would result in this.
     * Note that Phan can't know some scalars are not an int/string/float, since 0/""/"0"/0.0/[] are empty.
     * (Remove arrays anyway)
     */
    final protected function removeTruthyFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $type) : bool {
                return $type->containsTruthy();
            },
            static function (UnionType $type) : UnionType {
                return $type->nonTruthyClone();
            },
            $suppress_issues
        );
    }

    // Remove any types which are definitely falsey from that variable (NullType, FalseType)
    final protected function removeFalseyFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $type) : bool {
                return $type->containsFalsey();
            },
            static function (UnionType $type) : UnionType {
                return $type->nonFalseyClone();
            },
            $suppress_issues
        );
    }


    final protected function removeNullFromVariable(Node $var_node, Context $context, bool $suppress_issues) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $type) : bool {
                return $type->containsNullable();
            },
            static function (UnionType $type) : UnionType {
                return $type->nonNullableClone();
            },
            $suppress_issues
        );
    }

    /**
     * @param int|string|float $value
     */
    final protected function removeLiteralScalarFromVariable(
        Node $var_node,
        Context $context,
        $value,
        bool $strict_equality
    ) : Context {
        if (!is_int($value) && !is_string($value)) {
            return $context;
        }
        if ($strict_equality) {
            if (is_int($value)) {
                $cb = static function (Type $type) use ($value) : bool {
                    return $type instanceof LiteralIntType && $type->getValue() === $value;
                };
            } else { // string
                $cb = static function (Type $type) use ($value) : bool {
                    return $type instanceof LiteralStringType && $type->getValue() === $value;
                };
            }
        } else {
            $cb = static function (Type $type) use ($value) : bool {
                return $type instanceof LiteralTypeInterface && $type->getValue() == $value;
            };
        }
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $union_type) use ($cb) : bool {
                return $union_type->hasTypeMatchingCallback($cb);
            },
            static function (UnionType $union_type) use ($cb) : UnionType {
                $has_nullable = false;
                foreach ($union_type->getTypeSet() as $type) {
                    if ($cb($type)) {
                        $union_type = $union_type->withoutType($type);
                        $has_nullable = $has_nullable || $type->getIsNullable();
                    }
                }
                if ($has_nullable) {
                    if ($union_type->isEmpty()) {
                        return NullType::instance(false)->asUnionType();
                    }
                    return $union_type->nullableClone();
                }
                return $union_type;
            },
            false
        );
    }

    final protected function removeFalseFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $type) : bool {
                return $type->containsFalse();
            },
            static function (UnionType $type) : UnionType {
                return $type->nonFalseClone();
            },
            false
        );
    }

    final protected function removeTrueFromVariable(Node $var_node, Context $context) : Context
    {
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $context,
            static function (UnionType $type) : bool {
                return $type->containsTrue();
            },
            static function (UnionType $type) : UnionType {
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
     *
     * @param Node $var_node a node of kind ast\AST_VAR, ast\AST_PROP, or ast\AST_DIM
     * @param Closure(UnionType):bool $should_filter_cb
     * @param Closure(UnionType):UnionType $filter_union_type_cb
     */
    final protected function updateVariableWithConditionalFilter(
        Node $var_node,
        Context $context,
        Closure $should_filter_cb,
        Closure $filter_union_type_cb,
        bool $suppress_issues
    ) : Context {
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($var_node, $context);
            if (\is_null($variable)) {
                if ($var_node->kind === ast\AST_DIM) {
                    return $this->updateDimExpressionWithConditionalFilter($var_node, $context, $should_filter_cb, $filter_union_type_cb, $suppress_issues);
                } elseif ($var_node->kind === ast\AST_PROP) {
                    return $this->updatePropertyExpressionWithConditionalFilter($var_node, $context, $should_filter_cb, $filter_union_type_cb, $suppress_issues);
                }
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
        } catch (\Exception $_) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @param Node $node a node of kind ast\AST_DIM
     */
    final protected function updateDimExpressionWithConditionalFilter(
        Node $node,
        Context $context,
        Closure $should_filter_cb,
        Closure $filter_union_type_cb,
        bool $suppress_issues
    ) : Context {
        $var_name = self::getVarNameOfDimNode($node->children['expr']);
        if (!is_string($var_name)) {
            return $context;
        }
        try {
            // Get the type of the field we're operating on
            $old_field_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $node);
            if (!$should_filter_cb($old_field_type)) {
                return $context;
            }

            // Give the field an unused stub name and compute the new type
            $new_field_type = $filter_union_type_cb($old_field_type);
            if ($old_field_type->isEqualTo($new_field_type)) {
                return $context;
            }

            return (new AssignmentVisitor(
                $this->code_base,
                // We clone the original context to avoid affecting the original context for the elseif.
                // AssignmentVisitor modifies the provided context in place.
                //
                // There is a difference between `if (is_string($x['field']))` and `$x['field'] = remove_string_types($x['field'])` for the way the `elseif` should be analyzed.
                $context->withClonedScope(),
                $node,
                $new_field_type
            ))->__invoke($node);
        } catch (IssueException $exception) {
            if (!$suppress_issues) {
                Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
            }
        } catch (\Exception $_) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @param Node|mixed $node
     */
    protected static function isThisVarNode($node) : bool
    {
        return $node instanceof Node && $node->kind === ast\AST_VAR &&
            $node->children['name'] === 'this';
    }

    /**
     * Analyze an expression such as `assert(!is_int($this->prop_name))`
     * and infer the effects on $this->prop_name in the local scope.
     *
     * @param Node $node a node of kind ast\AST_PROP
     */
    final protected function updatePropertyExpressionWithConditionalFilter(
        Node $node,
        Context $context,
        Closure $should_filter_cb,
        Closure $filter_union_type_cb,
        bool $unused_suppress_issues
    ) : Context {
        if (!self::isThisVarNode($node->children['expr'])) {
            return $context;
        }
        $property_name = $node->children['prop'];
        if (!is_string($property_name)) {
            return $context;
        }
        return $this->modifyPropertyOfThisSimple(
            $node,
            static function (UnionType $type) use ($should_filter_cb, $filter_union_type_cb) : UnionType {
                if (!$should_filter_cb($type)) {
                    return $type;
                }
                return $filter_union_type_cb($type);
            },
            $context
        );
    }

    final protected function updateVariableWithNewType(
        Node $var_node,
        Context $context,
        UnionType $new_union_type,
        bool $suppress_issues
    ) : Context {
        if ($var_node->kind === ast\AST_PROP) {
            return $this->modifyPropertySimple($var_node, static function (UnionType $unused) use ($new_union_type) : UnionType {
                return $new_union_type;
            }, $context);
        }
        // TODO: Support ast\AST_DIM
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
        } catch (\Exception $_) {
            // Swallow it
        }
        return $context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     * @suppress PhanUnreferencedPublicMethod referenced in ConditionVisitorInterface
     */
    final public function updateVariableToBeIdentical(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context {
        $context = $context ?? $this->context;
        try {
            $expr_type = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $context, $expr);
            if (!$expr_type) {
                return $context;
            }
        } catch (\Exception $_) {
            return $context;
        }
        return $this->updateVariableWithNewType($var_node, $context, $expr_type, true);
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @return Context - Constant after inferring type from an expression such as `if ($x === 'literal')`
     * @suppress PhanUnreferencedPublicMethod referenced in ConditionVisitorInterface
     */
    final public function updateVariableToBeCompared(
        Node $var_node,
        $expr,
        int $flags
    ) : Context {
        $context = $this->context;
        $var_name = $var_node->children['name'] ?? null;
        // Don't analyze variables such as $$a
        if (\is_string($var_name) && $var_name) {
            try {
                $expr_type = UnionTypeVisitor::unionTypeFromLiteralOrConstant($this->code_base, $context, $expr);
                if (!$expr_type) {
                    return $context;
                }
                $expr_value = $expr_type->asSingleScalarValueOrNullOrSelf();
                if (\is_object($expr_value)) {
                    return $context;
                }
                // Get the variable we're operating on
                $variable = $this->getVariableFromScope($var_node, $context);
                if (\is_null($variable)) {
                    return $context;
                }

                // Make a copy of the variable
                $variable = clone($variable);

                // TODO: Filter out nullable types
                $union_type = $variable->getUnionType()->makeFromFilter(static function (Type $type) use ($expr_value, $flags) : bool {
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    return $type->canSatisfyComparison($expr_value, $flags);
                });
                if ($union_type->containsNullable()) {
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    if (!Type::performComparison(null, $expr_value, $flags)) {
                        // E.g. $x > 0 will remove the type null.
                        $union_type = $union_type->nonNullableClone();
                    }
                }
                $variable->setUnionType($union_type);

                // Overwrite the variable with its new type in this
                // scope without overwriting other scopes
                $context = $context->withScopeVariable(
                    $variable
                );
                return $context;
            } catch (\Exception $_) {
                // Swallow it (E.g. IssueException for undefined variable)
            }
        }
        return $context;
    }

    /**
     * @param Node $var_node a node of type ast\AST_VAR, ast\AST_DIM (planned), or ast\AST_PROP
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x !== 'literal')`
     * @suppress PhanUnreferencedPublicMethod referenced in ConditionVisitorInterface
     */
    final public function updateVariableToBeNotIdentical(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context {
        $context = $context ?? $this->context;
        try {
            if ($expr instanceof Node) {
                if ($expr->kind === ast\AST_CONST) {
                    $expr_name_node = $expr->children['name'];
                    if ($expr_name_node->kind === ast\AST_NAME) {
                        // Currently, only add this inference when we're absolutely sure this is a check rejecting null/false/true
                        $expr_name = $expr_name_node->children['name'];
                        switch (\strtolower($expr_name)) {
                            case 'null':
                                return $this->removeNullFromVariable($var_node, $context, false);
                            case 'false':
                                return $this->removeFalseFromVariable($var_node, $context);
                            case 'true':
                                return $this->removeTrueFromVariable($var_node, $context);
                        }
                    }
                }
            } else {
                return $this->removeLiteralScalarFromVariable($var_node, $context, $expr, true);
            }
        } catch (\Exception $_) {
            // Swallow it (E.g. IssueException for undefined variable)
        }
        return $context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|float|string $expr
     * @return Context - Constant after inferring type from an expression such as `if ($x != 'literal')`
     * @suppress PhanUnreferencedPublicMethod referenced in ConditionVisitorInterface
     */
    final public function updateVariableToBeNotEqual(
        Node $var_node,
        $expr,
        Context $context = null
    ) : Context {
        $context = $context ?? $this->context;

        $var_name = $var_node->children['name'] ?? null;
        // http://php.net/manual/en/types.comparisons.php#types.comparisions-loose @phan-suppress-current-line PhanPluginPossibleTypoComment, UnusedSuppression
        if (\is_string($var_name)) {
            try {
                if ($expr instanceof Node) {
                    if ($expr->kind === ast\AST_CONST) {
                        $expr_name_node = $expr->children['name'];
                        if ($expr_name_node->kind === ast\AST_NAME) {
                            // Currently, only add this inference when we're absolutely sure this is a check rejecting null/false/true
                            $expr_name = $expr_name_node->children['name'];
                            switch (\strtolower($expr_name)) {
                                case 'null':
                                case 'false':
                                    return $this->removeFalseyFromVariable($var_node, $context, false);
                                case 'true':
                                    return $this->removeTrueFromVariable($var_node, $context);
                            }
                        }
                    }
                    return $context;
                }
                // Remove all of the types which are loosely equal
                if (is_int($expr) || is_string($expr)) {
                    $context = $this->removeLiteralScalarFromVariable($var_node, $context, $expr, false);
                }

                if ($expr == false) {
                    if ($expr == null) {
                        return $this->removeFalseyFromVariable($var_node, $context, false);
                    }
                    return $this->removeFalseFromVariable($var_node, $context);
                } elseif ($expr == null) {
                    $context = $this->removeNullFromVariable($var_node, $context, false);
                } elseif ($expr == true) {  // e.g. 1, "1", -1
                    return $this->removeTrueFromVariable($var_node, $context);
                }
            } catch (\Exception $_) {
                // Swallow it (E.g. IssueException for undefined variable)
            }
        }
        return $context;
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x !== false)`
     *
     * TODO: Could improve analysis by adding analyzeAndUpdateToBeEqual for `==`
     */
    protected function analyzeAndUpdateToBeIdentical($left, $right) : Context
    {
        return $this->analyzeBinaryConditionPattern(
            $left,
            $right,
            new IdenticalCondition()
        );
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x !== false)`
     */
    protected function analyzeAndUpdateToBeNotIdentical($left, $right) : Context
    {
        return $this->analyzeBinaryConditionPattern(
            $left,
            $right,
            new NotIdenticalCondition()
        );
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @param BinaryCondition $condition
     */
    protected function analyzeBinaryConditionPattern($left, $right, BinaryCondition $condition) : Context
    {
        if ($left instanceof Node) {
            $result = $this->analyzeBinaryConditionSide($left, $right, $condition);
            if ($result !== null) {
                return $result;
            }
        }
        if ($right instanceof Node) {
            $result = $this->analyzeBinaryConditionSide($right, $left, $condition);
            if ($result !== null) {
                return $result;
            }
        }
        return $this->context;
    }

    /**
     * @param Node $var_node
     * @param Node|int|string|float $expr_node
     * @param BinaryCondition $condition
     * @return ?Context
     * @suppress PhanPartialTypeMismatchArgument
     */
    private function analyzeBinaryConditionSide(Node $var_node, $expr_node, BinaryCondition $condition)
    {
        '@phan-var ConditionVisitorUtil|ConditionVisitorInterface $this';
        $kind = $var_node->kind;
        if ($kind === ast\AST_VAR || $kind === ast\AST_DIM) {
            return $condition->analyzeVar($this, $var_node, $expr_node);
        }
        if ($kind === ast\AST_PROP) {
            if (self::isThisVarNode($var_node->children['expr']) && is_string($var_node->children['prop'])) {
                return $condition->analyzeVar($this, $var_node, $expr_node);
            }
            return null;
        }
        if ($kind === ast\AST_CALL) {
            $name = $var_node->children['expr']->children['name'] ?? null;
            if (\is_string($name)) {
                $name = \strtolower($name);
                if ($name === 'get_class') {
                    return $condition->analyzeClassCheck($this, $var_node->children['args']->children[0] ?? null, $expr_node);
                }
                return $condition->analyzeCall($this, $var_node, $expr_node);
            }
        }
        $tmp = $var_node;
        while (\in_array($kind, [ast\AST_ASSIGN, ast\AST_ASSIGN_OP, ast\AST_ASSIGN_REF], true)) {
            $var = $tmp->children['var'] ?? null;
            if (!$var instanceof Node) {
                break;
            }
            $kind = $var->kind;
            if ($kind === ast\AST_VAR) {
                $this->context = (new BlockAnalysisVisitor($this->code_base, $this->context))->__invoke($tmp);
                return $condition->analyzeVar($this, $var, $expr_node);
            }
            $tmp = $var;
        }
        return null;
    }

    /**
     * Returns a context where the variable for $object_node has the class found in $expr_node
     *
     * @param Node|string|int|float $object_node
     * @param Node|string|int|float|bool $expr_node
     * @return ?Context
     * @suppress PhanUnreferencedPublicMethod referenced in ConditionVisitorInterface
     */
    public function analyzeClassAssertion($object_node, $expr_node)
    {
        if (!($object_node instanceof Node)) {
            return null;
        }
        if ($expr_node instanceof Node) {
            $expr_value = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr_node)->asSingleScalarValueOrNull();
        } else {
            $expr_value = $expr_node;
        }
        if (!is_string($expr_value)) {
            $expr_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr_node);
            if (!$expr_type->canCastToUnionType(UnionType::fromFullyQualifiedString('string|false'))) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeComparisonToInvalidClassType,
                    $this->context->getLineNumberStart(),
                    $expr_type,
                    'false|string'
                );
            }
            // TODO: Could warn about invalid assertions
            return null;
        }
        $fqsen_string = '\\' . $expr_value;
        try {
            $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($fqsen_string);
        } catch (FQSENException $_) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::TypeComparisonToInvalidClass,
                $this->context->getLineNumberStart(),
                StringUtil::encodeValue($expr_value)
            );

            return null;
        }
        $expr_type = $fqsen->asType()->asUnionType();

        $var_name = $object_node->children['name'] ?? null;
        // Don't analyze variables such as $$a
        if (!(\is_string($var_name) && $var_name)) {
            return null;
        }
        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($object_node, $this->context);
            if (\is_null($variable)) {
                return null;
            }
            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType($expr_type);

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            return $this->context->withScopeVariable(
                $variable
            );
        } catch (\Exception $_) {
            // Swallow it (E.g. IssueException for undefined variable)
        }
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x == 'literal')`
     */
    protected function analyzeAndUpdateToBeNotEqual($left, $right) : Context
    {
        return $this->analyzeBinaryConditionPattern(
            $left,
            $right,
            new NotEqualsCondition()
        );
    }

    /**
     * @param Node|int|float|string $left
     * @param Node|int|float|string $right
     * @return Context - Constant after inferring type from an expression such as `if ($x > 0)`
     */
    protected function analyzeAndUpdateToBeCompared($left, $right, int $flags) : Context
    {
        return $this->analyzeBinaryConditionPattern(
            $left,
            $right,
            new ComparisonCondition($flags)
        );
    }


    /**
     * @return ?Variable - Returns null if the variable is undeclared and ignore_undeclared_variables_in_global_scope applies.
     *                     or if assertions won't be applied?
     * @throws IssueException if variable is undeclared and not ignored.
     * @see UnionTypeVisitor::visitVar()
     *
     * TODO: support assertions on superglobals, within the current file scope?
     */
    final public function getVariableFromScope(Node $var_node, Context $context)
    {
        if ($var_node->kind !== ast\AST_VAR) {
            return null;
        }
        $var_name_node = $var_node->children['name'] ?? null;

        if ($var_name_node instanceof Node) {
            // This is nonsense. Give up, but check if it's a type other than int/string.
            // (e.g. to catch typos such as $$this->foo = bar;)
            $name_node_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $var_name_node, true);
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType([
                    StringType::instance(false),
                    IntType::instance(false),
                    NullType::instance(false),
                ]);
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
            if (!($context->isInGlobalScope() && Config::getValue('ignore_undeclared_variables_in_global_scope'))) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $context->getFile(),
                        $var_node->lineno ?? 0,
                        [$var_name],
                        IssueFixSuggester::suggestVariableTypoFix($this->code_base, $context, $var_name)
                    )
                );
            }
            $variable = new Variable(
                $context,
                $var_name,
                UnionType::empty(),
                0
            );
            $context->addScopeVariable($variable);
            return $variable;
        }
        return $context->getScope()->getVariableByName(
            $var_name
        );
    }

    /**
     * @param array<mixed,Node|mixed> $args
     */
    final protected static function isArgumentListWithVarAsFirstArgument(array $args) : bool
    {
        if (\count($args) >= 1) {
            $arg = $args[0];
            // Phan also supports `if (!is_array($x['field']))` and `if (!is_array($this->propName))`
            return ($arg instanceof Node) && (\in_array($arg->kind, [ast\AST_VAR, ast\AST_DIM, ast\AST_PROP], true));
        }
        return false;
    }

    /**
     * Fetches the function name. Does not check for function uses or namespaces.
     * @return ?string (null if function name could not be found)
     */
    final protected static function getFunctionName(Node $node)
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

    /**
     * Generate a union type by excluding matching types in $excluded_type from $affected_type
     */
    public static function excludeMatchingTypes(CodeBase $code_base, UnionType $affected_type, UnionType $excluded_type) : UnionType
    {
        if ($affected_type->isEmpty() || $excluded_type->isEmpty()) {
            return $affected_type;
        }

        foreach ($excluded_type->getTypeSet() as $type) {
            if ($type instanceof NullType) {
                $affected_type = $affected_type->nonNullableClone();
            } elseif ($type instanceof FalseType) {
                $affected_type = $affected_type->nonFalseClone();
            } elseif ($type instanceof TrueType) {
                $affected_type = $affected_type->nonTrueClone();
            } else {
                continue;
            }
            // TODO: Do a better job handling LiteralStringType and LiteralIntType
            $excluded_type = $excluded_type->withoutType($type);
        }
        if ($excluded_type->isEmpty()) {
            return $affected_type;
        }
        return $affected_type->makeFromFilter(static function (Type $type) use ($code_base, $excluded_type) : bool {
            return $type instanceof MixedType || !$type->asExpandedTypes($code_base)->canCastToUnionType($excluded_type);
        });
    }

    /**
     * Returns this ConditionVisitorUtil's CodeBase.
     * This is needed by subclasses of BinaryCondition.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getCodeBase() : CodeBase
    {
        return $this->code_base;
    }

    /**
     * Returns this ConditionVisitorUtil's Context.
     * This is needed by subclasses of BinaryCondition.
     */
    public function getContext() : Context
    {
        return $this->context;
    }

    /**
     * @param Node|mixed $node
     * @param Closure(CodeBase,Context,Variable,array<int,mixed>):void $type_modification_callback
     *        A closure acting on a Variable instance (not really a variable) to modify its type
     * @param Context $context
     * @param array<int,mixed> $args
     * @return Context
     */
    protected function modifyComplexExpression($node, Closure $type_modification_callback, Context $context, array $args) : Context
    {
        if (!$node instanceof Node) {
            return $context;
        }
        if ($node->kind === ast\AST_DIM) {
            return $this->modifyComplexDimExpression($node, $type_modification_callback, $context, $args);
        } elseif ($node->kind === ast\AST_PROP) {
            if (self::isThisVarNode($node->children['expr'])) {
                return $this->modifyPropertyOfThis($node, $type_modification_callback, $context, $args);
            }
        }
        return $context;
    }

    /**
     * @param Node $node a node of kind ast\AST_DIM (e.g. the argument of is_array($x['field']))
     * @param Closure(CodeBase,Context,Variable,array<int,mixed>):void $type_modification_callback
     *        A closure acting on a Variable instance (not really a variable) to modify its type
     *
     *        This is a function such as is_array, is_null (questionable), etc.
     * @param Context $context
     * @param array<int,mixed> $args
     */
    protected function modifyComplexDimExpression(Node $node, Closure $type_modification_callback, Context $context, array $args) : Context
    {
        $var_name = $this->getVarNameOfDimNode($node->children['expr']);
        if (!is_string($var_name)) {
            return $context;
        }
        // Give the field an unused stub name and compute the new type
        $old_field_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $node);
        $field_variable = new Variable($context, "__phan", $old_field_type, 0);
        $type_modification_callback($this->code_base, $context, $field_variable, $args);
        $new_field_type = $field_variable->getUnionType();
        if ($new_field_type->isEqualTo($old_field_type)) {
            return $context;
        }
        // Treat if (is_array($x['field'])) similarly to `$x['field'] = some_function_returning_array()
        // (But preserve anything known about array types of $x['field'])
        return (new AssignmentVisitor(
            $this->code_base,
            // We clone the original context to avoid affecting the original context for the elseif.
            // AssignmentVisitor modifies the provided context in place.
            //
            // There is a difference between `if (is_string($x['field']))` and `$x['field'] = (some string)` for the way the `elseif` should be analyzed.
            $context->withClonedScope(),
            $node,
            $new_field_type
        ))->__invoke($node);
    }

    /**
     * Return a context with overrides for the type of a property in the local scope.
     *
     * @param Node $node a node of kind ast\AST_PROP (e.g. the argument of is_array($this->prop_name))
     * @param Closure(CodeBase,Context,Variable,array<int,mixed>):void $type_modification_callback
     *        A closure acting on a Variable instance (not really a variable) to modify its type
     *
     *        This is a function such as is_array, is_null, etc.
     * @param Context $context
     * @param array<int,mixed> $args
     */
    protected function modifyPropertyOfThis(Node $node, Closure $type_modification_callback, Context $context, array $args) : Context
    {
        $property_name = $node->children['prop'];
        if (!is_string($property_name)) {
            return $context;
        }
        // Give the property a type and compute the new type
        $old_property_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $node);
        $property_variable = new Variable($context, "__phan", $old_property_type, 0);
        $type_modification_callback($this->code_base, $context, $property_variable, $args);
        $new_property_type = $property_variable->getUnionType();
        if ($new_property_type->isEqualTo($old_property_type)) {
            // This didn't change anything
            return $context;
        }
        return $context->withThisPropertySetToTypeByName($property_name, $new_property_type);
    }

    /**
     * Return a context with overrides for the type of a property in the local scope.
     *
     * @param Node $node a node of kind ast\AST_PROP (e.g. the argument of is_array($this->prop_name))
     * @param Closure(UnionType):UnionType $type_mapping_callback
     *        Given a union type, returns the resulting union type.
     * @param Context $context
     */
    protected function modifyPropertyOfThisSimple(Node $node, Closure $type_mapping_callback, Context $context) : Context
    {
        $property_name = $node->children['prop'];
        if (!is_string($property_name)) {
            return $context;
        }
        // Give the property a type and compute the new type
        $old_property_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $node);
        $new_property_type = $type_mapping_callback($old_property_type);
        if ($new_property_type->isEqualTo($old_property_type)) {
            // This didn't change anything
            return $context;
        }
        return $context->withThisPropertySetToTypeByName($property_name, $new_property_type);
    }

    /**
     * @param Node $node a node of kind ast\AST_PROP (e.g. the argument of is_array($this->prop_name))
     *                   This is a no-op of the expression is not $this.
     * @param Closure(UnionType):UnionType $type_mapping_callback
     *        Given a union type, returns the resulting union type.
     * @param Context $context
     */
    protected function modifyPropertySimple(Node $node, Closure $type_mapping_callback, Context $context) : Context
    {
        if (!self::isThisVarNode($node->children['expr'])) {
            return $context;
        }
        return self::modifyPropertyOfThisSimple($node, $type_mapping_callback, $context);
    }

    /**
     * @param Node|mixed $node
     * @return ?string the name of the variable in a chain of field accesses such as $varName['field'][$i]
     */
    private static function getVarNameOfDimNode($node)
    {
        // Loop to support getting the var name in is_array($x['field'][0])
        while (true) {
            if (!($node instanceof Node)) {
                return null;
            }
            if ($node->kind === ast\AST_VAR) {
                break;
            }
            if ($node->kind === ast\AST_DIM) {
                $node = $node->children['expr'];
                if (!$node instanceof Node) {
                    return null;
                }
                continue;
            }

            // TODO: Handle more than one level of nesting
            return null;
        }
        $var_name = $node->children['name'];
        return is_string($var_name) ? $var_name : null;
    }
}
