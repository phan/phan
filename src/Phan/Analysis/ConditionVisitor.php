<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\flags;
use ast\Node;
use Closure;
use Phan\Analysis\ConditionVisitor\HasTypeCondition;
use Phan\Analysis\ConditionVisitor\NotHasTypeCondition;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;
use Phan\Library\StringUtil;
use ReflectionMethod;

/**
 * A visitor that takes a Context and a Node for a condition and returns a Context that has been updated with that condition.
 *
 * @phan-file-suppress PhanUnusedClosureParameter
 * TODO: if (a || b || c || d) might get really slow, due to creating both ConditionVisitor and NegatedConditionVisitor
 */
class ConditionVisitor extends KindVisitorImplementation implements ConditionVisitorInterface
{
    use ConditionVisitorUtil;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    protected $context;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context
    {
        $this->checkVariablesDefined($node);
        return $this->context;
    }

    /**
     * Check if variables from within a generic condition are defined.
     * @param Node $node
     * A node to parse
     * @return void
     */
    private function checkVariablesDefined(Node $node)
    {
        while ($node->kind === ast\AST_UNARY_OP) {
            $node = $node->children['expr'];
            if (!($node instanceof Node)) {
                return;
            }
        }
        // Get the type just to make sure everything
        // is defined.
        UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node,
            true
        );
    }

    /**
     * Check if variables from within isset are defined.
     * @param Node $node
     * A node to parse
     * @return void
     */
    private function checkVariablesDefinedInIsset(Node $node)
    {
        while ($node->kind === ast\AST_UNARY_OP) {
            $node = $node->children['expr'];
            if (!($node instanceof Node)) {
                return;
            }
        }
        if ($node->kind === ast\AST_DIM) {
            $this->checkArrayAccessDefined($node);
            return;
        }
        // Get the type just to make sure everything
        // is defined.
        UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node,
            true
        );
    }

    /**
     * Analyzes (isset($x['field']))
     * @return void
     *
     * TODO: Add to NegatedConditionVisitor
     */
    private function checkArrayAccessDefined(Node $node)
    {
        $code_base = $this->code_base;
        $context = $this->context;

        // TODO: Infer that the offset exists after this check
        UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['dim'],
            true
        );
        // Check the array type to trigger TypeArraySuspicious
        /* $array_type = */
        UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['expr'],
            true
        );
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitBinaryOp(Node $node) : Context
    {
        $flags = $node->flags;
        switch ($flags) {
            case flags\BINARY_BOOL_AND:
                return $this->analyzeShortCircuitingAnd($node->children['left'], $node->children['right']);
            case flags\BINARY_BOOL_OR:
                return $this->analyzeShortCircuitingOr($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_IDENTICAL:
            case flags\BINARY_IS_EQUAL:
                // TODO: Could be more precise, and preserve 0, [], etc. for `$x == null`
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeIdentical($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_NOT_IDENTICAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeNotIdentical($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_NOT_EQUAL:
                return $this->analyzeAndUpdateToBeNotEqual($node->children['left'], $node->children['right']);
            case flags\BINARY_IS_GREATER:
            case flags\BINARY_IS_GREATER_OR_EQUAL:
            case flags\BINARY_IS_SMALLER:
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
                $this->checkVariablesDefined($node);
                return $this->analyzeAndUpdateToBeCompared($node->children['left'], $node->children['right'], $flags);
            default:
                $this->checkVariablesDefined($node);
                return $this->context;
        }
    }

    /**
     * Helper method
     * @param Node|mixed $left
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @param Node|mixed $right
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    private function analyzeShortCircuitingAnd($left, $right) : Context
    {
        // Aside: If left/right is not a node, left/right is a literal such as a number/string, and is either always truthy or always falsey.
        // Inside of this conditional may be dead or redundant code.
        if ($left instanceof Node) {
            $this->context = $this->__invoke($left);
        }
        // TODO: Warn if !$left
        if ($right instanceof Node) {
            return $this->__invoke($right);
        }
        return $this->context;
    }

    /**
     * Helper method
     * @param Node|mixed $left
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @param Node|mixed $right
     * a Node or non-node to parse (possibly an AST literal)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    private function analyzeShortCircuitingOr($left, $right) : Context
    {
        // Aside: If left/right is not a node, left/right is a literal such as a number/string, and is either always truthy or always falsey.
        // Inside of this conditional may be dead or redundant code.
        if (!($left instanceof Node)) {
            if ($left) {
                return $this->context;
            }
            return $this->__invoke($right);
        }
        if (!($right instanceof Node)) {
            if ($right) {
                return $this->context;
            }
            return $this->__invoke($left);
        }
        $code_base = $this->code_base;
        $context = $this->context;
        $left_false_context = (new NegatedConditionVisitor($code_base, $context))->__invoke($left);
        $left_true_context = (new ConditionVisitor($code_base, $context))->__invoke($left);
        // We analyze the right-hand side of `cond($x) || cond2($x)` as if `cond($x)` was false.
        $right_true_context = (new ConditionVisitor($code_base, $left_false_context))->__invoke($right);
        // When the ConditionVisitor is true, at least one of the left or right contexts must be true.
        return (new ContextMergeVisitor($context, [$left_true_context, $right_true_context]))->combineChildContextList();
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUnaryOp(Node $node) : Context
    {
        $expr_node = $node->children['expr'];
        $flags = $node->flags;
        if ($flags !== flags\UNARY_BOOL_NOT) {
            // TODO: Emit dead code issue for non-nodes
            if ($expr_node instanceof Node) {
                if ($flags === flags\UNARY_SILENCE) {
                    return $this->__invoke($expr_node);
                }
                $this->checkVariablesDefined($expr_node);
            }
            return $this->context;
        }
        // TODO: Emit dead code issue for non-nodes
        if ($expr_node instanceof Node) {
            return (new NegatedConditionVisitor($this->code_base, $this->context))->__invoke($expr_node);
        }
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIsset(Node $node) : Context
    {
        $var_node = $node->children['var'];
        if (!($var_node instanceof Node)) {
            return $this->context;
        }
        if ($var_node->kind !== ast\AST_VAR) {
            return $this->checkComplexIsset($var_node);
        }

        $var_name = $var_node->children['name'];
        if (!\is_string($var_name)) {
            $this->checkVariablesDefinedInIsset($var_node);
            return $this->context;
        }
        return $this->withSetVariable($var_name, $var_node);
    }

    /**
     * From isset($var), infer that $var is non-null
     * From isset($obj->prop['field']), infer that $obj is non-null
     * Also infer that $obj is an object (don't do that for $obj['field']->prop)
     */
    private function withSetVariable(string $var_name, Node $var_node) : Context
    {
        $context = $this->context;
        $is_object = $var_node->kind === ast\AST_PROP;

        $scope = $context->getScope();
        if (!$scope->hasVariableWithName($var_name)) {
            // Support analyzing cases such as `if (isset($x)) { use($x); }`, or `assert(isset($x))`
            return $context->withScopeVariable(new Variable(
                $context->withLineNumberStart($var_node->lineno ?? 0),
                $var_name,
                $is_object ? ObjectType::instance(false)->asUnionType() : UnionType::empty(),
                0
            ));
        }
        if ($is_object) {
            $variable = clone($context->getScope()->getVariableByName($var_name));
            $this->analyzeIsObjectAssertion($variable);
            $context = $this->modifyPropertySimple($var_node, static function (UnionType $type) : UnionType {
                return $type->nonNullableClone();
            }, $context);
            return $context->withScopeVariable($variable);
        }
        return $this->removeNullFromVariable($var_node, $context, true);
    }

    /**
     * @param Node $node a node that is NOT of type ast\AST_VAR
     */
    private function checkComplexIsset(Node $node) : Context
    {
        // Loop to support getting the var name in is_array($x['field'][0])
        $has_prop_access = false;
        $context = $this->context;
        $var_node = $node;
        $parent_node = $node;
        while (true) {
            $kind = $var_node->kind;
            if ($kind === ast\AST_VAR) {
                break;
            }
            $parent_node = $var_node;
            if ($kind === ast\AST_DIM) {
                $var_node = $var_node->children['expr'];
                if (!$var_node instanceof Node) {
                    return $context;
                }
                continue;
            } elseif ($kind == ast\AST_PROP) {
                $has_prop_access = true;
                $var_node = $var_node->children['expr'];
                if (!$var_node instanceof Node) {
                    return $context;
                }
                continue;
            }

            // TODO: Handle more than one level of nesting
            return $context;
        }
        $var_name = $var_node->children['name'];
        if (!is_string($var_name)) {
            return $context;
        }
        if ($has_prop_access) {
            // For `$x->prop['field'][0]`, $parent_node would be `$x->prop`.
            // And for that expression, phan would infer that $var_name was non-null AND an object.
            return $this->withSetVariable($var_name, $parent_node);
        }

        // This is $x['field'] or $x[$i][something]

        if (!$context->getScope()->hasVariableWithName($var_name)) {
            // Support analyzing cases such as `if (isset($x['key'])) { use($x); }`, or `assert(isset($x['key']))`
            return $context->withScopeVariable(new Variable(
                $context->withLineNumberStart($node->lineno ?? 0),
                $var_name,
                ArrayType::instance(false)->asUnionType(),
                0
            ));
        }
        $context = $this->removeNullFromVariable($var_node, $context, true);

        $variable = $context->getScope()->getVariableByName($var_name);
        $var_node_union_type = $variable->getUnionType();

        if ($var_node_union_type->hasTopLevelArrayShapeTypeInstances()) {
            $new_union_type = $this->withSetArrayShapeTypes($var_node_union_type, $parent_node->children['dim'], $context, true);
            if ($new_union_type !== $var_node_union_type) {
                $variable = clone($variable);
                $variable->setUnionType($new_union_type);
                $context = $context->withScopeVariable($variable);
            }
        }
        $this->context = $context;
        return $context;
    }

    /**
     * @param UnionType $union_type the type being modified by inferences from isset or array_key_exists
     * @param Node|string|float|int|bool $dim_node represents the dimension being accessed. (E.g. can be a literal or an AST_CONST, etc.
     * @param Context $context the context with inferences made prior to this condition
     *
     * @param bool $non_nullable if an offset is created, will it be non-nullable?
     */
    private function withSetArrayShapeTypes(UnionType $union_type, $dim_node, Context $context, bool $non_nullable) : UnionType
    {
        $dim_value = $dim_node instanceof Node ? (new ContextNode($this->code_base, $context, $dim_node))->getEquivalentPHPScalarValue() : $dim_node;
        // TODO: detect and warn about null
        if (!\is_scalar($dim_value)) {
            return $union_type;
        }

        $dim_union_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($union_type, $dim_value);
        if (!$dim_union_type) {
            // There are other types, this dimension does not exist yet
            if (!$union_type->hasTopLevelArrayShapeTypeInstances()) {
                return $union_type;
            }
            return ArrayType::combineArrayShapeTypesWithField($union_type, $dim_value, MixedType::instance(false)->asUnionType());
        } elseif ($dim_union_type->containsNullableOrUndefined()) {
            if (!$non_nullable) {
                // The offset in question already exists in the array shape type, and we won't be changing it.
                // (E.g. array_key_exists('key', $x) where $x is array{key:?int,other:string})
                return $union_type;
            }

            return ArrayType::combineArrayShapeTypesWithField($union_type, $dim_value, $dim_union_type->nonNullableClone());
        }
        return $union_type;
    }

    /**
     * @param Node $node
     * A node to parse, with kind ast\AST_VAR
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node) : Context
    {
        $this->checkVariablesDefined($node);
        return $this->removeFalseyFromVariable($node, $this->context, false);
    }

    /**
     * @param Node $node
     * A node to parse, with kind ast\AST_PROP (e.g. `if ($this->prop_name)`)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitProp(Node $node) : Context
    {
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            return $this->context;
        }
        if ($expr_node->kind !== ast\AST_VAR || $expr_node->children['name'] !== 'this') {
            return $this->context;
        }
        if (!\is_string($node->children['prop'])) {
            return $this->context;
        }
        return $this->modifyPropertyOfThisSimple(
            $node,
            static function (UnionType $type) : UnionType {
                return $type->nonFalseyClone();
            },
            $this->context
        );
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node) : Context
    {
        //$this->checkVariablesDefined($node);
        // Only look at things of the form
        // `$variable instanceof ClassName`
        $context = $this->context;
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return $context;
        }
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node) || $expr_node->kind !== ast\AST_VAR) {
            return $this->modifyComplexExpression(
                $expr_node,
                /**
                 * @param array<int,mixed> $args
                 * @return void
                 */
                function (CodeBase $code_base, Context $context, Variable $variable, array $args) use ($class_node) {
                    $this->setInstanceofVariableType($variable, $class_node);
                },
                $context,
                []
            );
        }


        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($expr_node, $context);
            if (\is_null($variable)) {
                return $context;
            }
            // Make a copy of the variable
            $variable = clone($variable);
            $this->setInstanceofVariableType($variable, $class_node);
            // Overwrite the variable with its new type
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $_) {
            // Swallow it
        }

        return $context;
    }

    /**
     * Modifies the union type of $variable in place
     */
    private function setInstanceofVariableType(Variable $variable, Node $class_node)
    {
        // Get the type that we're checking it against
        $type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $class_node
        );
        $object_types = $type->objectTypes();
        if (!$object_types->isEmpty()) {
            // We know that the variable is the provided object type (or a subclass)
            // See https://secure.php.net/instanceof -

            // Add the type to the variable
            $variable->setUnionType(self::calculateNarrowedUnionType($this->code_base, $variable->getUnionType(), $object_types));
        } else {
            // We know that variable is some sort of object if this condition is true.
            if ($class_node->kind !== ast\AST_NAME &&
                    !$type->canCastToUnionType(StringType::instance(false)->asUnionType())) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeInvalidInstanceof,
                    $this->context->getLineNumberStart(),
                    (string)$type->asNonLiteralType()
                );
            }
            self::analyzeIsObjectAssertion($variable);
        }
    }

    /**
     * E.g. Given subclass1|subclass2|false and base_class/base_interface, returns subclass1|subclass2
     * E.g. Given subclass1|mixed|false and base_class/base_interface, returns base_class/base_interface
     */
    private static function calculateNarrowedUnionType(CodeBase $code_base, UnionType $old_type, UnionType $asserted_object_type) : UnionType
    {
        $result = UnionType::empty();
        foreach ($old_type->getTypeSet() as $type) {
            if ($type instanceof MixedType) {
                return $asserted_object_type;
            }
            if (!$type->isObject()) {
                // ignore non-object types
                continue;
            }
            if (!$type->isObjectWithKnownFQSEN()) {
                return $asserted_object_type;
            }
            $type = $type->withIsNullable(false);
            if (!$type->asExpandedTypes($code_base)->canCastToUnionType($asserted_object_type)) {
                return $asserted_object_type;
            }
            $result = $result->withType($type);
        }
        if ($result->isEmpty()) {
            return $asserted_object_type;
        }
        return $result;
    }

    /**
     * @param Variable $variable (Node argument in a call to is_object)
     * @return void
     */
    private static function analyzeIsObjectAssertion(Variable $variable)
    {
        // Change the type to match is_object relationship
        // If we already have the `object` type or generic object types, then keep those
        // (E.g. T|false becomes T, T[]|iterable|null becomes Traversable, object|bool becomes object)
        $new_type_builder = new UnionTypeBuilder();
        foreach ($variable->getUnionType()->getTypeSet() as $type) {
            if ($type->isObject()) {
                $new_type_builder->addType($type->withIsNullable(false));
                continue;
            }
            if (\get_class($type) === IterableType::class) {
                // An iterable is either an array or a Traversable.
                // @phan-suppress-next-line PhanThrowTypeAbsentForCall
                $new_type_builder->addType(Type::traversableInstance());
            }
        }
        $variable->setUnionType($new_type_builder->isEmpty() ? ObjectType::instance(false)->asUnionType() : $new_type_builder->getUnionType());
    }

    /**
     * This function is called once, and returns closures to modify the types of variables.
     *
     * This contains Phan's logic for inferring the resulting union types of variables, e.g. in \is_array($x).
     *
     * @return array<string,Closure> - The closures to call for a given global function
     * @phan-return array<string,Closure(CodeBase, Context, Variable, array):void>
     */
    private static function initTypeModifyingClosuresForVisitCall() : array
    {
        $make_basic_assertion_callback = static function (string $union_type_string) : Closure {
            $asserted_union_type = UnionType::fromFullyQualifiedString(
                $union_type_string
            );
            $asserted_union_type_set = $asserted_union_type->getTypeSet();
            $empty_type = UnionType::empty();

            /**
             * @param array<int,Node|mixed> $args
             * @return void
             */
            return static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $args) use ($asserted_union_type, $asserted_union_type_set, $empty_type) {
                $new_types = $empty_type;
                foreach ($variable->getUnionType()->getTypeSet() as $type) {
                    $type = $type->withIsNullable(false);
                    if ($type->canCastToAnyTypeInSet($asserted_union_type_set)) {
                        $new_types = $new_types->withType($type);
                    }
                }

                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType($new_types->isEmpty() ? $asserted_union_type : $new_types);
            };
        };
        $make_direct_assertion_callback = static function (string $union_type_string) : Closure {
            $asserted_union_type = UnionType::fromFullyQualifiedString(
                $union_type_string
            );
            /**
             * @param array<int,Node|mixed> $args
             * @return void
             */
            return static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $args) use ($asserted_union_type) {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType($asserted_union_type);
            };
        };

        $array_type = ArrayType::instance(false);
        /**
         * @param array<int,Node|mixed> $args
         * @return void
         */
        $array_callback = static function (CodeBase $code_base, Context $context, Variable $variable, array $args) use ($array_type) {
            // Change the type to match the is_a relationship
            // If we already have generic array types, then keep those
            // (E.g. T[]|false becomes T[], ?array|null becomes array)
            $new_type_builder = new UnionTypeBuilder();
            foreach ($variable->getUnionType()->getTypeSet() as $type) {
                if ($type instanceof ArrayType) {
                    $new_type_builder->addType($type->withIsNullable(false));
                    continue;
                }
                if (\get_class($type) === IterableType::class) {
                    // An iterable is either an array or a Traversable.
                    $new_type_builder->addType($array_type);
                }
            }
            $variable->setUnionType($new_type_builder->isEmpty() ? $array_type->asUnionType() : $new_type_builder->getUnionType());
        };

        /**
         * @param array<int,Node|mixed> $args
         * @return void
         */
        $object_callback = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $args) {
            self::analyzeIsObjectAssertion($variable);
        };
        /**
         * @param array<int,Node|mixed> $args
         * @return void
         */
        $is_a_callback = static function (CodeBase $code_base, Context $context, Variable $variable, array $args) use ($object_callback) {
            $class_name = $args[1] ?? null;
            if ($class_name instanceof Node) {
                $class_name = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $class_name)->asSingleScalarValueOrNull();
            }
            if (!\is_string($class_name)) {
                // Limit the types of $variable to an object if we can't infer the class name.
                $object_callback($code_base, $context, $variable, $args);
                return;
            }
            try {
                $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
            } catch (FQSENException $_) {
                throw new IssueException(Issue::fromType(Issue::TypeComparisonToInvalidClass)(
                    $context->getFile(),
                    $context->getLineNumberStart(),
                    [StringUtil::encodeValue($class_name)]
                ));
            }
            // TODO: validate argument
            $class_type = $fqsen->asType()->asUnionType();
            $variable->setUnionType(self::calculateNarrowedUnionType($code_base, $variable->getUnionType(), $class_type));
        };

        /**
         * @param array<int,Node|mixed> $args
         * @return void
         */
        $scalar_callback = static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $args) {
            // Change the type to match the is_a relationship
            // If we already have possible scalar types, then keep those
            // (E.g. T|false becomes bool, T becomes int|float|bool|string|null)
            $new_type = $variable->getUnionType()->scalarTypes();
            if ($new_type->containsNullable()) {
                $new_type = $new_type->nonNullableClone();
            }
            if ($new_type->isEmpty()) {
                // If there are no inferred types, or the only type we saw was 'null',
                // assume there this can be any possible scalar.
                // (Excludes `resource`, which is technically a scalar)
                $new_type = UnionType::fromFullyQualifiedString('int|float|bool|string');
            }
            $variable->setUnionType($new_type);
        };
        /**
         * @param string $extract_types
         * @param UnionType $default_if_empty
         * @return Closure(CodeBase,Context,Variable,array):void
         */
        $make_callback = static function (string $extract_types, UnionType $default_if_empty) : Closure {
            $method = new ReflectionMethod(UnionType::class, $extract_types);
            /**
             * @param array<int,Node|mixed> $args
             * @return void
             */
            return static function (CodeBase $unused_code_base, Context $unused_context, Variable $variable, array $args) use ($method, $default_if_empty) {
                // Change the type to match the is_a relationship
                // If we already have possible callable types, then keep those
                // (E.g. Closure|false becomes Closure)
                $new_type = $method->invoke($variable->getUnionType());
                if ($new_type->isEmpty()) {
                    // If there are no inferred types, or the only type we saw was 'null',
                    // assume there this can be any possible scalar.
                    // (Excludes `resource`, which is technically a scalar)
                    $new_type = $default_if_empty;
                } elseif ($new_type->containsNullable()) {
                    $new_type = $new_type->nonNullableClone();
                }
                $variable->setUnionType($new_type);
            };
        };
        /** @return void */
        $callable_callback = $make_callback('callableTypes', CallableType::instance(false)->asUnionType());
        $bool_callback = $make_callback('getTypesInBoolFamily', BoolType::instance(false)->asUnionType());
        $int_callback = $make_callback('intTypes', IntType::instance(false)->asUnionType());
        $string_callback = $make_callback('stringTypes', StringType::instance(false)->asUnionType());
        $numeric_callback = $make_callback('numericTypes', UnionType::fromFullyQualifiedString('string|int|float'));

        // Note: LiteralIntType exists, but LiteralFloatType doesn't, which is why these are different.
        $float_callback = $make_direct_assertion_callback('float');
        $null_callback = $make_direct_assertion_callback('null');
        // Note: isset() is handled in visitIsset()

        return [
            'is_a' => $is_a_callback,
            'is_array' => $array_callback,
            'is_bool' => $bool_callback,
            'is_callable' => $callable_callback,
            'is_double' => $float_callback,
            'is_float' => $float_callback,
            'is_int' => $int_callback,
            'is_integer' => $int_callback,
            'is_iterable' => $make_basic_assertion_callback('iterable'),  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $int_callback,
            'is_null' => $null_callback,
            'is_numeric' => $numeric_callback,
            'is_object' => $object_callback,
            'is_real' => $float_callback,
            'is_resource' => $make_direct_assertion_callback('resource'),
            'is_scalar' => $scalar_callback,
            'is_string' => $string_callback,
            'empty' => $null_callback,
        ];
    }

    /**
     * Look at elements of the form `is_array($v)` and modify
     * the type of the variable.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context
    {
        $raw_function_name = self::getFunctionName($node);
        if (!\is_string($raw_function_name)) {
            return $this->context;
        }
        $args = $node->children['args']->children;
        $first_arg = $args[0] ?? null;

        // Translate the function name into the UnionType it asserts
        static $map = null;

        if ($map === null) {
            $map = self::initTypeModifyingClosuresForVisitCall();
        }

        // Only look at things of the form
        // `\is_string($variable)`
        if (!($first_arg instanceof Node && $first_arg->kind === ast\AST_VAR)) {
            if (\strcasecmp($raw_function_name, 'array_key_exists') === 0 && \count($args) === 2) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                return $this->analyzeArrayKeyExists($args);
            }
            $type_modification_callback = $map[\strtolower($raw_function_name)] ?? null;
            if (!$type_modification_callback) {
                return $this->context;
            }
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            return $this->modifyComplexExpression($first_arg, $type_modification_callback, $this->context, $args);
        }

        if (\count($args) !== 1) {
            if (!(\strcasecmp($raw_function_name, 'is_a') === 0 && \count($args) === 2)) {
                return $this->context;
            }
        }

        $function_name = \strtolower($raw_function_name);
        $type_modification_callback = $map[$function_name] ?? null;
        if ($type_modification_callback === null) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = $this->getVariableFromScope($first_arg, $context);

            if (\is_null($variable)) {
                return $context;
            }

            // Make a copy of the variable
            $variable = clone($variable);

            // Modify the types of that variable.
            $type_modification_callback($this->code_base, $context, $variable, $args);

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $context, $exception->getIssueInstance());
        } catch (\Exception $_) {
            // Swallow it (E.g. IssueException for undefined variable)
        }

        return $context;
    }

    /**
     * @param array<int,Node|string|int|float> $args
     */
    private function analyzeArrayKeyExists(array $args) : Context
    {
        if (\count($args) !== 2) {
            return $this->context;
        }
        $var_node = $args[1];
        if (!($var_node instanceof Node)) {
            return $this->context;
        }
        return $this->updateVariableWithConditionalFilter(
            $var_node,
            $this->context,
            static function (UnionType $_) : bool {
                return true;
            },
            function (UnionType $type) use ($args) : UnionType {
                $type = $type->nonNullableClone();
                if ($type->hasTopLevelArrayShapeTypeInstances()) {
                    return $this->withSetArrayShapeTypes($type, $args[0], $this->context, false);
                }
                return $type;
            },
            true
        );
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEmpty(Node $node) : Context
    {
        $var_node = $node->children['expr'];
        if (!($var_node instanceof Node)) {
            return $this->context;
        }
        // Should always be a node for valid ASTs, tolerant-php-parser may produce invalid nodes
        if (\in_array($var_node->kind, [ast\AST_VAR, ast\AST_PROP, ast\AST_DIM], true)) {
            // Don't emit notices for if (empty($x)) {}, etc.
            return $this->removeTruthyFromVariable($var_node, $this->context, true);
        }
        $this->checkVariablesDefinedInIsset($var_node);
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node) : Context
    {
        $children = $node->children;
        $count = \count($children);
        if ($count > 1) {
            foreach ($children as $sub_node) {
                --$count;
                if ($count > 0 && $sub_node instanceof Node) {
                    $this->checkVariablesDefined($sub_node);
                }
            }
        }
        // Only analyze the last expression in the expression list for conditions.
        $last_expression = \end($node->children);
        if ($last_expression instanceof Node) {
            return $this->__invoke($last_expression);
        } else {
            // Other code should warn about this invalid AST
            return $this->context;
        }
    }

    /**
     * Useful for analyzing `if ($x = foo() && $x->method())`
     * TODO: Remove empty/false/null types from $x
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node) : Context
    {
        $context = (new BlockAnalysisVisitor($this->code_base, $this->context))->visitAssign($node);
        $left = $node->children['var'];
        if (!($left instanceof Node)) {
            // Other code should warn about this invalid AST
            return $context;
        }
        return (new self($this->code_base, $context))->__invoke($left);
    }

    /**
     * Useful for analyzing `if ($x = foo() && $x->method())`
     * TODO: Remove empty/false/null types from $x
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssignRef(Node $node) : Context
    {
        $context = (new BlockAnalysisVisitor($this->code_base, $this->context))->visitAssignRef($node);
        $left = $node->children['var'];
        if (!($left instanceof Node)) {
            // TODO: Ensure this always warns
            return $context;
        }
        return (new self($this->code_base, $context))->__invoke($left);
    }

    /**
     * Update the variable represented by $expression to have the type $type.
     */
    public static function updateToHaveType(CodeBase $code_base, Context $context, Node $expression, UnionType $type) : Context
    {
        $cv = new ConditionVisitor($code_base, $context);
        return $cv->analyzeBinaryConditionPattern(
            $expression,
            0,
            new HasTypeCondition($type)
        );
    }

    /**
     * Update the variable represented by $expression to not have the type $type.
     */
    public static function updateToNotHaveType(CodeBase $code_base, Context $context, Node $expression, UnionType $type) : Context
    {
        $cv = new ConditionVisitor($code_base, $context);
        return $cv->analyzeBinaryConditionPattern(
            $expression,
            0,
            new NotHasTypeCondition($type)
        );
    }
}
