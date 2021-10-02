<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\flags;
use ast\Node;
use Exception;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\ClosureDeclarationType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;

/**
 * Determines the UnionType associated with a given node as a fallback,
 * for cases that aren't constant expressions (those get resolved by UnionTypeVisitor::unionTypeFromNode).
 *
 * This is useful for finding out possible variable types when analyzing loops, because not all assignments (from later in the loop) are seen yet.
 * This is very conservative in UnionTypes it infers.
 *
 * @see UnionTypeVisitor for what should be used for the vast majority of use cases
 * @see FallbackMethodTypesVisitor for the code using this.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal, PhanPartialTypeMismatchArgument node is complicated
 */
class FallbackUnionTypeVisitor extends KindVisitorImplementation
{
    /**
     * @var CodeBase
     * The code base within which we're operating
     * @phan-read-only
     */
    protected $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exists.
     */
    protected $context;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        // Inlined to be more efficient.
        // parent::__construct($code_base, $context);
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param int|float|string|Node $node
     * The node which is having the type be determined
     *
     * @return UnionType
     * The conservatively chosen UnionType associated with the given node
     * in the given Context within the given CodeBase
     */
    public static function unionTypeFromNode(
        CodeBase $code_base,
        Context $context,
        $node
    ): UnionType {
        if ($node instanceof Node) {
            return (new self($code_base, $context))->__invoke($node);
        }
        return Type::fromObject($node)->asRealUnionType();
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node (@phan-unused-param)
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return UnionType
     * The set of types associated with the given node
     */
    public function visit(Node $node): UnionType
    {
        return UnionType::empty();
    }

    // Real types aren't certain, since these don't throw even if the expression
    // being incremented is an object or array.
    //
    // TODO: Check if union type is sane (string/int)
    // public function visitPostInc(Node $node) : UnionType
    // public function visitPostDec(Node $node) : UnionType
    // public function visitPreInc(Node $node) : UnionType
    // public function visitPreDec(Node $node) : UnionType

    /**
     * Visit a node with kind `\ast\AST_CLONE`
     *
     * @param Node $node @unused-param
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClone(Node $node): UnionType
    {
        return ObjectType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_EMPTY`
     *
     * @param Node $node (@phan-unused-param)
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEmpty(Node $node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ISSET`
     *
     * @param Node $node (@phan-unused-param)
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIsset(Node $node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_INCLUDE_OR_EVAL`
     *
     * @param Node $node (@phan-unused-param)
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIncludeOrEval(Node $node): UnionType
    {
        // require() can return arbitrary objects. Lets just
        // say that we don't know what it is and move on
        return UnionType::empty();
    }

    /**
     * Visit a node with kind `\ast\AST_SHELL_EXEC`
     *
     * @param Node $node (@phan-unused-param)
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitShellExec(Node $node): UnionType
    {
        return StringType::instance(true)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CONDITIONAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConditional(Node $node): UnionType
    {
        $cond_node = $node->children['cond'];
        $cond_truthiness = UnionTypeVisitor::checkCondUnconditionalTruthiness($cond_node);
        // For the shorthand $a ?: $b, the cond node will be the truthy value.
        // Note: an ast node will never be null(can be unset), it will be a const AST node with the name null.
        $true_node = $node->children['true'] ?? $cond_node;

        // Rarely, a conditional will always be true or always be false.
        if ($cond_truthiness !== null) {
            // TODO: Add no-op checks in another PR, if they don't already exist for conditional.
            if ($cond_truthiness) {
                // The condition is unconditionally true
                return self::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $true_node
                );
            } else {
                // The condition is unconditionally false

                // Add the type for the 'false' side
                return self::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $node->children['false']
                );
            }
        }

        // Postcondition: This is (cond_expr) ? (true_expr) : (false_expr)

        $true_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $true_node
        );

        $false_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['false']
        );

        // Add the type for the 'true' side to the 'false' side
        $union_type = $true_type->withUnionType($false_type);

        // If one side has an unknown type but the other doesn't
        // we can't let the unseen type get erased. Unfortunately,
        // we need to add 'mixed' in so that we know it could be
        // anything at all.
        //
        // See Issue #104
        if ($true_type->isEmpty() xor $false_type->isEmpty()) {
            $union_type = $union_type->withType(
                MixedType::instance(false)
            );
        }

        return $union_type;
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     *
     * @param Node $node @unused-param
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitArray(Node $node): UnionType
    {
        // TODO: More precise
        return ArrayType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP` (or `\ast\AST_ASSIGN_OP`)
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitBinaryOp(Node $node): UnionType
    {
        switch ($node->flags) {
            case flags\BINARY_ADD:
                return UnionType::fromFullyQualifiedRealString('int|float|array');
            case flags\BINARY_BITWISE_AND:
            case flags\BINARY_BITWISE_OR:
            case flags\BINARY_BITWISE_XOR:
                return UnionType::fromFullyQualifiedRealString('int|string');
            case flags\BINARY_BOOL_XOR:
            case flags\BINARY_IS_EQUAL:
            case flags\BINARY_IS_IDENTICAL:
            case flags\BINARY_IS_NOT_EQUAL:
            case flags\BINARY_IS_NOT_IDENTICAL:
            case flags\BINARY_IS_SMALLER:
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
            case flags\BINARY_BOOL_AND:
            case flags\BINARY_BOOL_OR:
            case flags\BINARY_IS_GREATER:
            case flags\BINARY_IS_GREATER_OR_EQUAL:
                return UnionType::fromFullyQualifiedRealString('bool');
            case flags\BINARY_CONCAT:
                return UnionType::fromFullyQualifiedRealString('string');
            case flags\BINARY_DIV:
            case flags\BINARY_MUL:
            case flags\BINARY_POW:
            case flags\BINARY_SUB:
                return UnionType::fromFullyQualifiedRealString('int|float');
            case flags\BINARY_MOD:
            case flags\BINARY_SHIFT_LEFT:
            case flags\BINARY_SHIFT_RIGHT:
                return UnionType::fromFullyQualifiedRealString('int');
            case flags\BINARY_SPACESHIP:
                return UnionType::fromFullyQualifiedRealString('-1|0|1');
            case flags\BINARY_COALESCE:
                return $this->analyzeCoalesce($node);
        }
        return UnionType::empty();
    }

    /**
     * @param Node $node a node of kind ast\AST_ASSIGN_OP or ast\AST_BINARY_OP with flags ast\flags\BINARY_COALESCE
     */
    private function analyzeCoalesce(Node $node): UnionType
    {
        $left = self::unionTypeFromNode($this->code_base, $this->context, $node->children['left'] ?? $node->children['var']);
        if ($left->isEmpty()) {
            return UnionType::empty();
        }
        $right = self::unionTypeFromNode($this->code_base, $this->context, $node->children['right'] ?? $node->children['expr']);
        if ($right->isEmpty()) {
            return UnionType::empty();
        }
        return $left->nonNullableClone()->withUnionType($right);
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_OP` (E.g. $x .= 'suffix')
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssignOp(Node $node): UnionType
    {
        // TODO: Refactor if this depends on $node->children in the future.
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_CAST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     *
     * @throws NodeException if the flags are a value we aren't expecting
     * @suppress PhanThrowTypeMismatchForCall
     */
    public function visitCast(Node $node): UnionType
    {
        // This calls unionTypeFromNode to trigger any warnings
        // TODO: Check if the cast would throw an error at runtime, based on the type (e.g. casting object to string/int)

        // RedundantConditionCallPlugin contains unrelated checks of whether this is redundant.
        switch ($node->flags) {
            case \ast\flags\TYPE_NULL:
                return NullType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_BOOL:
                return BoolType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_LONG:
                return IntType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_DOUBLE:
                return FloatType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_STRING:
                return StringType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_ARRAY:
                return ArrayType::instance(false)->asRealUnionType();
            case \ast\flags\TYPE_OBJECT:
                // TODO: Handle values that are already objects
                return Type::fromFullyQualifiedString('\stdClass')->asRealUnionType();
            case \ast\flags\TYPE_STATIC:
                return StaticType::instance(false)->asRealUnionType();
            default:
                throw new NodeException(
                    $node,
                    'Unknown type (' . $node->flags . ') in cast'
                );
        }
    }

    /**
     * Visit a node with kind `\ast\AST_NEW`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitNew(Node $node): UnionType
    {
        static $object_type;
        if ($object_type === null) {
            $object_type = ObjectType::instance(false)->asRealUnionType();
        }
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return $object_type;
        }
        return $this->visitClassNameNode($class_node) ?? $object_type;
    }

    /**
     * Visit a node with kind `\ast\AST_INSTANCEOF`
     *
     * @param Node $node @unused-param
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitInstanceOf(Node $node): UnionType
    {
        return BoolType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClosure(Node $node): UnionType
    {
        // The type of a closure is the fqsen pointing
        // at its definition
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context,
                $node
            );

        if ($this->code_base->hasFunctionWithFQSEN($closure_fqsen)) {
            $func = $this->code_base->getFunctionByFQSEN($closure_fqsen);
        } else {
            $func = null;
        }

        return ClosureType::instanceWithClosureFQSEN(
            $closure_fqsen,
            $func
        )->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ARROW_FUNC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitArrowFunc(Node $node): UnionType
    {
        return $this->visitClosure($node);
    }

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     *
     * @param Node $node (@phan-unused-param)
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEncapsList(Node $node): UnionType
    {
        return StringType::instance(false)->asRealUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConst(Node $node): UnionType
    {
        // Figure out the name of the constant if it's
        // a string.
        $constant_name = $node->children['name']->children['name'] ?? '';

        // If the constant is referring to the current
        // class, return that as a type
        if (Type::isSelfTypeString($constant_name) || Type::isStaticTypeString($constant_name)) {
            return Type::fromStringInContext($constant_name, $this->context, Type::FROM_NODE, $this->code_base)->asRealUnionType();
        }

        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getConst();
        } catch (Exception $_) {
            return UnionType::empty();
        }

        return $constant->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClassConst(Node $node): UnionType
    {
        $class_node = $node->children['class'];
        if (!$class_node instanceof Node || $class_node->kind !== ast\AST_NAME) {
            // ignore nonsense like (0)::class, and dynamic accesses such as $var::CLASS
            return UnionType::empty();
        }
        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConst();
            $union_type = $constant->getUnionType();
            if (\strcasecmp($class_node->children['name'], 'static') === 0) {
                if ($this->context->isInClassScope() && $this->context->getClassInScope($this->code_base)->isFinal()) {
                    // static::X should be treated like self::X in a final class.
                    return $union_type;
                }
                return $union_type->eraseRealTypeSet();
            }
            return $union_type;
        } catch (NodeException $_) {
            // ignore, this should warn elsewhere
        }

        return UnionType::empty();
    }

    // TODO: Support AST_STATIC_PROP

    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the function name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCall(Node $node): UnionType
    {
        $expression = $node->children['expr'];
        if (!($expression instanceof Node && $expression->kind === ast\AST_NAME)) {
            // Give up on closures, callables
            return UnionType::empty();
        }
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode(true);

            $possible_types = null;
            foreach ($function_list_generator as $function) {
                $function->analyzeReturnTypes($this->code_base);  // For daemon/server mode, call this to consistently ensure accurate return types.

                // NOTE: Deliberately do not use the closure for $function->hasDependentReturnType().
                // Most plugins expect the context to have variables, which this won't provide.
                $function_types = self::getDependentFallbackReturnTypeOfCall($function, $node);
                if ($possible_types instanceof UnionType) {
                    $possible_types = $possible_types->withUnionType($function_types);
                } else {
                    $possible_types = $function_types;
                }
            }

            return $possible_types ?? UnionType::empty();
        } catch (Exception $_) {
            return UnionType::empty();
        }
    }

    /**
     * @return UnionType - the union type of the result of the call, or of the closure generated by first-class callable conversion
     */
    private static function getDependentFallbackReturnTypeOfCall(FunctionInterface $function, Node $node): UnionType
    {
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            if ($function instanceof ClosureDeclarationType) {
                return $function->asRealUnionType();
            } else {
                return ClosureType::instanceWithClosureFQSEN($function->getFQSEN(), $function)->asRealUnionType();
            }
        }
        return $function->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticCall(Node $node): UnionType
    {
        ['class' => $class_node, 'method' => $method_name] = $node->children;
        if (!\is_string($method_name) || !($class_node instanceof Node) || $class_node->kind !== ast\AST_NAME) {
            // Give up on dynamic calls
            return UnionType::empty();
        }
        try {
            $possible_types = null;
            foreach (UnionTypeVisitor::classListFromNodeAndContext($this->code_base, $this->context, $class_node) as $class) {
                if (!$class->hasMethodWithName($this->code_base, $method_name, true)) {
                    return UnionType::empty();
                }
                $method = $class->getMethodByName($this->code_base, $method_name);
                $method_types = self::getDependentFallbackReturnTypeOfCall($method, $node);
                if ($possible_types instanceof UnionType) {
                    $possible_types = $possible_types->withUnionType($method_types);
                } else {
                    $possible_types = $method_types;
                }
            }
            return $possible_types ?? UnionType::empty();
        } catch (Exception $_) {
            return UnionType::empty();
        }
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`.
     *
     * Conservatively try to infer the returned union type of calls such
     * as $this->someMethod(...)
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMethodCall(Node $node): UnionType
    {
        ['expr' => $expr_node, 'method' => $method_name] = $node->children;
        if (!\is_string($method_name) || !($expr_node instanceof Node) || $expr_node->kind !== ast\AST_VAR) {
            // Give up on dynamic calls
            return UnionType::empty();
        }
        // Only attempt to handle $this->method()
        // Don't attempt to handle possibility of closures being rebound.
        if ($expr_node->children['name'] !== 'this') {
            return UnionType::empty();
        }
        if (!$this->context->isInClassScope()) {
            return UnionType::empty();
        }
        try {
            $class = $this->context->getClassInScope($this->code_base);
            if (!$class->hasMethodWithName($this->code_base, $method_name, true)) {
                return UnionType::empty();
            }
            $method = $class->getMethodByName($this->code_base, $method_name);
            return $method->getUnionType();
        } catch (Exception $_) {
            return UnionType::empty();
        }
    }

    /**
     * Visit a node with kind `\ast\AST_NULLSAFE_METHOD_CALL`.
     *
     * Conservatively try to infer the returned union type of calls such
     * as $this?->someMethod(...)
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitNullsafeMethodCall(Node $node): UnionType
    {
        return $this->visitMethodCall($node)->nullableClone();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssign(Node $node): UnionType
    {
        // XXX typed properties/references will change the type of the result from the right hand side
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryOp(Node $node): UnionType
    {
        // Shortcut some easy operators
        $flags = $node->flags;
        if ($flags === \ast\flags\UNARY_BOOL_NOT) {
            return BoolType::instance(false)->asRealUnionType();
        }

        if ($flags === \ast\flags\UNARY_MINUS || $flags === ast\flags\UNARY_PLUS) {
            return UnionType::fromFullyQualifiedRealString('int|float');
        } elseif ($flags === \ast\flags\UNARY_BITWISE_NOT) {
            return UnionType::fromFullyQualifiedRealString('int|string');
        }
        // UNARY_SILENCE
        return self::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
    }

    /**
     * `print($str)` always returns 1.
     * See https://secure.php.net/manual/en/function.print.php#refsect1-function.print-returnvalues
     * @param Node $node @phan-unused-param
     */
    public function visitPrint(Node $node): UnionType
    {
        return LiteralIntType::instanceForValue(1, false)->asRealUnionType();
    }

    /**
     * @param Node $node
     * A node holding a class name
     *
     * @return ?UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    private function visitClassNameNode(Node $node): ?UnionType
    {
        // Things of the form `new $className()`, `new $obj()`, `new (foo())()`, etc.
        if ($node->kind !== \ast\AST_NAME) {
            return null;
        }

        // Get the name of the class
        $class_name = $node->children['name'];

        // If this is a straight-forward class name, recurse into the
        // class node and get its type
        if (Type::isStaticTypeString($class_name)) {
            return StaticType::instance(false)->asRealUnionType();
        }
        if (!Type::isSelfTypeString($class_name)) {
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            return UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node
            );
        }

        // This node references `self` or `static`
        if (!$this->context->isInClassScope()) {
            return UnionType::empty();
        }

        // Reference to a parent class
        if ($class_name === 'parent') {
            $class = $this->context->getClassInScope(
                $this->code_base
            );

            $parent_type_option = $class->getParentTypeOption();
            if (!$parent_type_option->isDefined()) {
                return UnionType::empty();
            }

            return $parent_type_option->get()->asRealUnionType();
        }

        return $this->context->getClassFQSEN()->asType()->asRealUnionType();
    }

    // TODO: visitVar for $this is Object or the current class.
}
