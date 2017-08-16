<?php declare(strict_types=1);
namespace Phan\AST;

use Phan\Analysis\AssignOperatorFlagVisitor;
use Phan\Analysis\BinaryOperatorFlagVisitor;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\TypeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\ArraySet;
use ast\Node;

/**
 * Determine the UnionType associated with a
 * given node
 */
class UnionTypeVisitor extends AnalysisVisitor
{
    /**
     * @var bool
     * Set to true to cause loggable issues to be thrown
     * instead of emitted as issues to the log.
     */
    private $should_catch_issue_exception = false;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param bool $should_catch_issue_exception
     * Set to true to cause loggable issues to be thrown
     * instead of emitted as issues to the log.
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        bool $should_catch_issue_exception = true
    ) {
        parent::__construct($code_base, $context);

        $this->should_catch_issue_exception =
            $should_catch_issue_exception;
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|string|bool|int|float|null $node
     * The node for which we'd like to determine its type
     *
     * @param bool $should_catch_issue_exception
     * Set to true to cause loggable issues to be thrown
     * instead
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @throws IssueException
     * If $should_catch_issue_exception is false an IssueException may
     * be thrown for optional issues.
     */
    public static function unionTypeFromNode(
        CodeBase $code_base,
        Context $context,
        $node,
        bool $should_catch_issue_exception = true
    ) : UnionType {
        if (!($node instanceof Node)) {
            if ($node === null || $node === 'null') {
                return new UnionType();
            }

            return Type::fromObject($node)->asUnionType();
        }

        if ($should_catch_issue_exception) {
            try {
                return (new self(
                    $code_base,
                    $context,
                    $should_catch_issue_exception
                ))($node);
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $context,
                    $exception->getIssueInstance()
                );
                return new UnionType();
            }
        }

        return (new self(
            $code_base,
            $context,
            $should_catch_issue_exception
        ))($node);
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
    public function visit(Node $node) : UnionType
    {
        /*
        throw new NodeException($node,
            'Visitor not implemented for node of type '
            . Debug::nodeName($node)
        );
        */
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_POST_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostInc(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_POST_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostDec(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreDec(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreInc(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_CLONE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClone(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
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
    public function visitEmpty(Node $node) : UnionType
    {
        return BoolType::instance(false)->asUnionType();
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
    public function visitIsset(Node $node) : UnionType
    {
        return BoolType::instance(false)->asUnionType();
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
    public function visitIncludeOrEval(Node $node) : UnionType
    {
        // require() can return arbitrary objects. Lets just
        // say that we don't know what it is and move on
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_MAGIC_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMagicConst(Node $node) : UnionType
    {
        if ($node->flags === \ast\flags\MAGIC_LINE) {
            return IntType::instance(false)->asUnionType();
        }
        // This is for things like __METHOD__
        return StringType::instance(false)->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_REF`
     * @see $this->visitAssign
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssignRef(Node $node) : UnionType
    {
        // TODO: Is there any way this should differ from analysis
        // (e.g. should subsequent assignments affect the right hand Node?)
        return $this->visitAssign($node);
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
    public function visitShellExec(Node $node) : UnionType
    {
        return StringType::instance(false)->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_NAME`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitName(Node $node) : UnionType
    {
        if ($node->flags & \ast\flags\NAME_NOT_FQ) {
            if ('parent' === $node->children['name']) {
                if (!$this->context->isInClassScope()) {
                    throw new IssueException(
                        Issue::fromType(Issue::ContextNotObject)(
                            $this->context->getFile(),
                            $this->context->getLineNumberStart(),
                            [
                                'parent'
                            ]
                        )
                    );
                }
                $class = $this->context->getClassInScope($this->code_base);

                if ($class->hasParentType()) {
                    return Type::fromFullyQualifiedString(
                        (string)$class->getParentClassFQSEN()
                    )->asUnionType();
                } else {
                    if (!$class->isTrait()) {
                        $this->emitIssue(
                            Issue::ParentlessClass,
                            $node->lineno ?? 0,
                            (string)$class->getFQSEN()
                        );
                    }

                    return new UnionType();
                }
            }

            return Type::fromStringInContext(
                $node->children['name'],
                $this->context,
                Type::FROM_NODE
            )->asUnionType();
        }

        return Type::fromFullyQualifiedString(
            '\\' . $node->children['name']
        )->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_TYPE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitType(Node $node) : UnionType
    {
        switch ($node->flags) {
            case \ast\flags\TYPE_ARRAY:
                return ArrayType::instance(false)->asUnionType();
            case \ast\flags\TYPE_BOOL:
                return BoolType::instance(false)->asUnionType();
            case \ast\flags\TYPE_CALLABLE:
                return CallableType::instance(false)->asUnionType();
            case \ast\flags\TYPE_DOUBLE:
                return FloatType::instance(false)->asUnionType();
            case \ast\flags\TYPE_ITERABLE:
                return IterableType::instance(false)->asUnionType();
            case \ast\flags\TYPE_LONG:
                return IntType::instance(false)->asUnionType();
            case \ast\flags\TYPE_NULL:
                return NullType::instance(false)->asUnionType();
            case \ast\flags\TYPE_OBJECT:
                return ObjectType::instance(false)->asUnionType();
            case \ast\flags\TYPE_STRING:
                return StringType::instance(false)->asUnionType();
            case \ast\flags\TYPE_VOID:
                return VoidType::instance(false)->asUnionType();
            default:
                throw new \AssertionError("All flags must match. Found "
                    . Debug::astFlagDescription($node->flags ?? 0, $node->kind));
        }
    }

    /**
     * Visit a node with kind `\ast\AST_TYPE` representing
     * a nullable type such as `?string`.
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitNullableType(Node $node) : UnionType
    {
        // Get the type
        $union_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['type'],
            $this->should_catch_issue_exception
        );

        // Make each nullable
        return $union_type->asMappedUnionType(function (Type $type) : Type {
            return $type->withIsNullable(true);
        });
    }

    /**
     * @param int|float|string|Node $node
     * @return ?UnionType
     */
    public static function unionTypeFromLiteralOrConstant(CodeBase $code_base, Context $context, $node) {
        if ($node instanceof Node) {
            // TODO: Could check for arrays of constants or literals, and convert those to the generic array types
            if ($node->kind === \ast\AST_CONST || $node->kind === \ast\AST_CLASS_CONST) {
                try {
                    return UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node, false);
                } catch(IssueException $e) {
                    return null;
                }
            }
            return null;
        }
        // Otherwise, this is an int/float/string.
        \assert(\is_scalar($node), 'node must be Node or scalar');
        return Type::fromObject($node)->asUnionType();
    }

    /**
     * @param int|float|string|Node $cond
     * @return ?bool
     */
    public static function checkCondUnconditionalTruthiness($cond) {
        if ($cond instanceof Node) {
            if ($cond->kind === \ast\AST_CONST) {
                $name = $cond->children['name'];
                if ($name->kind === \ast\AST_NAME) {
                    switch(strtolower($name->children['name'])) {
                    case 'true':
                        return true;
                    case 'false':
                        return false;
                    case 'null':
                        return false;
                    default:
                        // Could add heuristics based on internal/user-defined constant values, but that is unreliable.
                        // (E.g. feature flags for an extension may be true or false, depending on the environment)
                        // (and Phan doesn't store constant values for user-defined constants, only the types)
                        return null;
                    }
                }
            }
            return null;
        }
        // Otherwise, this is an int/float/string.
        // Use the exact same truthiness rules as PHP to check if the conditional is truthy.
        // (e.g. "0" and 0.0 and '' are false)
        \assert(\is_scalar($cond), 'cond must be Node or scalar');
        return (bool)$cond;
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
    public function visitConditional(Node $node) : UnionType
    {
        $cond_node = $node->children['cond'];
        $cond_truthiness = self::checkCondUnconditionalTruthiness($cond_node);
        // For the shorthand $a ?: $b, the cond node will be the truthy value.
        // Note: an ast node will never be null(can be unset), it will be a const AST node with the name null.
        $true_node = $node->children['true'] ?? $cond_node;

        // Rarely, a conditional will always be true or always be false.
        if ($cond_truthiness !== null) {
            // TODO: Add no-op checks in another PR, if they don't already exist for conditional.
            if ($cond_truthiness === true) {
                // The condition is unconditionally true
                return UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $true_node
                );
            } else {
                // The condition is unconditionally false

                // Add the type for the 'false' side
                return UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $node->children['false'] ?? ''
                );
            }
        }
        if ($true_node !== $cond_node) {
            // Visit the condition to check for undefined variables.
            UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $cond_node
            );
        }

        // TODO: emit no-op if $cond_node is a literal, such as `if (2)`
        // - Also note that some things such as `true` and `false` are \ast\AST_NAME nodes.

        if ($cond_node instanceof Node) {
            $base_context = $this->context;
            // TODO: Use different contexts and merge those, in case there were assignments or assignments by reference in both sides of the conditional?
            // Reuse the BranchScope (sort of unintuitive). The ConditionVisitor returns a clone and doesn't modify the original.
            $base_context_scope = $this->context->getScope();
            if ($base_context_scope instanceof GlobalScope) {
                $base_context = $base_context->withScope(new BranchScope($base_context_scope));
            }
            $true_context = (new ConditionVisitor(
                $this->code_base,
                isset($node->children['true']) ? $base_context : $this->context  // special case: $c = (($d = foo()) ?: 'fallback')
            ))($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))($cond_node);
        } else {
            $true_context = $this->context;
            $false_context = $this->context;
        }

        $true_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $true_context,
            $true_node
        );

        $false_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $false_context,
            $node->children['false'] ?? ''
        );

        $union_type = new UnionType();

        // Add the type for the 'true' side
        $union_type->addUnionType($true_type);

        // Add the type for the 'false' side
        $union_type->addUnionType($false_type);

        // If one side has an unknown type but the other doesn't
        // we can't let the unseen type get erased. Unfortunately,
        // we need to add 'mixed' in so that we know it could be
        // anything at all.
        //
        // See Issue #104
        if ($true_type->isEmpty() xor $false_type->isEmpty()) {
            $union_type->addUnionType(
                MixedType::instance(false)->asUnionType()
            );
        }

        return $union_type;
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitArray(Node $node) : UnionType
    {
        if (!empty($node->children)
            && $node->children[0] instanceof Node
            && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            $element_types = [];

            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            for ($i=0; $i<5; $i++) {
                // Check to see if we're out of elements
                if (empty($node->children[$i])) {
                    break;
                }

                if ($node->children[$i]->children['value'] instanceof Node) {
                    $element_types[] = UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $this->context,
                        $node->children[$i]->children['value'],
                        $this->should_catch_issue_exception
                    );
                } else {
                    $element_types[] = Type::fromObject(
                        $node->children[$i]->children['value']
                    )->asUnionType();
                }
            }

            $element_types =
                \array_unique($element_types);

            if (\count($element_types) === 1) {
                return \reset($element_types)->asGenericArrayTypes();
            }
        }

        return ArrayType::instance(false)->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitBinaryOp(Node $node) : UnionType
    {
        return (new BinaryOperatorFlagVisitor(
            $this->code_base,
            $this->context
        ))($node);
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
    public function visitAssignOp(Node $node) : UnionType
    {
        return (new AssignOperatorFlagVisitor(
            $this->code_base,
            $this->context
        ))($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreater(Node $node) : UnionType
    {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreaterEqual(Node $node) : UnionType
    {
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
     */
    public function visitCast(Node $node) : UnionType
    {
        // TODO: Check if the cast is allowed based on the right side type
        UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        switch ($node->flags) {
            case \ast\flags\TYPE_NULL:
                return NullType::instance(false)->asUnionType();
            case \ast\flags\TYPE_BOOL:
                return BoolType::instance(false)->asUnionType();
            case \ast\flags\TYPE_LONG:
                return IntType::instance(false)->asUnionType();
            case \ast\flags\TYPE_DOUBLE:
                return FloatType::instance(false)->asUnionType();
            case \ast\flags\TYPE_STRING:
                return StringType::instance(false)->asUnionType();
            case \ast\flags\TYPE_ARRAY:
                return ArrayType::instance(false)->asUnionType();
            case \ast\flags\TYPE_OBJECT:
                return ObjectType::instance(false)->asUnionType();
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
    public function visitNew(Node $node) : UnionType
    {
        $union_type = $this->visitClassNode($node->children['class']);

        // TODO: re-use the underlying type set in the common case
        // Maybe UnionType::fromMap

        // For any types that are templates, map them to concrete
        // types based on the parameters passed in.
        return new UnionType(\array_map(function (Type $type) use ($node) {

            // Get a fully qualified name for the type
            $fqsen = $type->asFQSEN();

            // If this isn't a class, its fine as is
            if (!($fqsen instanceof FullyQualifiedClassName)) {
                return $type;
            }

            // If we don't have the class, we'll catch that problem
            // elsewhere
            if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
                return $type;
            }


            $class = $this->code_base->getClassByFQSEN($fqsen);

            // If this class doesn't have any generics on it, we're
            // fine as we are with this Type
            if (!$class->isGeneric()) {
                return $type;
            }

            // Now things are interesting. We need to map the
            // arguments to the generic types and return a special
            // kind of type.

            // Get the constructor so that we can figure out what
            // template types we're going to be mapping
            $constructor_method =
                $class->getMethodByName($this->code_base, '__construct');

            // Map each argument to its type
            $arg_type_list = \array_map(function($arg_node) {
                return UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $arg_node
                );
            }, $node->children['args']->children ?? []);

            // Map each template type o the argument's concrete type
            $template_type_list = [];
            foreach ($constructor_method->getParameterList() as $i => $parameter) {
                if (isset($arg_type_list[$i])) {
                    $template_type_list[] = $arg_type_list[$i];
                }
            }

            // Create a new type that assigns concrete
            // types to template type identifiers.
            return Type::fromType($type, $template_type_list);
        }, $union_type->getTypeSet()));
    }

    /**
     * Visit a node with kind `\ast\AST_INSTANCEOF`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitInstanceOf(Node $node) : UnionType
    {
        // Check to make sure the left side is valid
        UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        try {
            // Confirm that the right-side exists
            // Compute the union type but don't use it.
            $this->visitClassNode(
                $node->children['class']
            );
        } catch (TypeException $exception) {
            // TODO: log it?
        }

        return BoolType::instance(false)->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitDim(Node $node) : UnionType
    {
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            $this->should_catch_issue_exception
        );

        if ($union_type->isEmpty()) {
            return $union_type;
        }

        // Figure out what the types of accessed array
        // elements would be
        $generic_types =
            $union_type->genericArrayElementTypes();

        // If we have generics, we're all set
        if (!$generic_types->isEmpty()) {
            return $generic_types;
        }

        // If the only type is null, we don't know what
        // accessed items will be
        if ($union_type->isType(NullType::instance(false))) {
            return new UnionType();
        }

        $element_types = new UnionType();

        // You can access string characters via array index,
        // so we'll add the string type to the result if we're
        // indexing something that could be a string
        if ($union_type->isType(StringType::instance(false))
            || $union_type->canCastToUnionType(StringType::instance(false)->asUnionType())
        ) {
            $element_types->addType(StringType::instance(false));
        }

        if ($element_types->isEmpty()) {
            // If none of the types we found were arrays with elements,
            // then check for ArrayAccess
            static $array_access_type;
            static $simple_xml_element_type;  // SimpleXMLElement doesn't `implement` ArrayAccess, but can be accessed that way. See #542
            if ($array_access_type === null) {
                // array offsets work on strings, unfortunately
                // Double check that any classes in the type don't
                // have ArrayAccess
                $array_access_type =
                    Type::fromNamespaceAndName('\\', 'ArrayAccess', false);
                $simple_xml_element_type =
                    Type::fromNamespaceAndName('\\', 'SimpleXMLElement', false);
            }

            // Hunt for any types that are viable class names and
            // see if they inherit from ArrayAccess
            try {
                foreach ($union_type->asClassList($this->code_base, $this->context) as $class) {
                    $expanded_types = $class->getUnionType()->asExpandedTypes($this->code_base);
                    if ($expanded_types->hasType($array_access_type) ||
                            $expanded_types->hasType($simple_xml_element_type)) {
                        return $element_types;
                    }
                }
            } catch (CodeBaseException $exception) {}

            $this->emitIssue(
                Issue::TypeArraySuspicious,
                $node->lineno ?? 0,
                (string)$union_type
            );
        }

        return $element_types;
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
    public function visitClosure(Node $node) : UnionType
    {
        // The type of a closure is the fqsen pointing
        // at its definition
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context,
                $node
            );

        $type = ClosureType::instanceWithClosureFQSEN(
            $closure_fqsen
        )->asUnionType();

        return $type;
    }

    /**
     * Visit a node with kind `\ast\AST_VAR`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitVar(Node $node) : UnionType
    {
        // $$var or ${...} (whose idea was that anyway?)
        $name_node = $node->children['name'];
        if (($name_node instanceof Node)) {
            // This is nonsense. Give up.
            $name_node_type = $this($name_node);
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType();
                $int_or_string_type->addType(StringType::instance(false));
                $int_or_string_type->addType(IntType::instance(false));
                $int_or_string_type->addType(NullType::instance(false));
            }
            if (!$name_node_type->canCastToUnionType($int_or_string_type)) {
                Issue::maybeEmit($this->code_base, $this->context, Issue::TypeSuspiciousIndirectVariable, $name_node->lineno ?? 0, (string)$name_node_type);
            }

            return MixedType::instance(false)->asUnionType();
        }

        // foo(${42}) is technically valid PHP code, avoid TypeError
        $variable_name =
            (string)$name_node;

        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                return Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name);
            }
            if (!Config::getValue('ignore_undeclared_variables_in_global_scope')
                || !$this->context->isInGlobalScope()
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        [$variable_name]
                    )
                );
            }
        } else {
            $variable = $this->context->getScope()->getVariableByName(
                $variable_name
            );

            return $variable->getUnionType();
        }

        return new UnionType();
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
    public function visitEncapsList(Node $node) : UnionType
    {
        return StringType::instance(false)->asUnionType();
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
    public function visitConst(Node $node) : UnionType
    {
        if ($node->children['name']->kind == \ast\AST_NAME) {
            $name = $node->children['name']->children['name'];
            if (defined($name)) {
                // This constant is internal to php
                $result = Type::fromReservedConstantName($name);
                if ($result->isDefined()) {
                    // And it's a reserved keyword such as false, null, E_ALL, etc.
                    return $result->get()->asUnionType();
                }
                // TODO: use the CodeBase for all internal constants.
                // defined() doesn't account for use statements in the codebase (`use ... as aliased_name`)
                // TODO: The below code will act as though some constants from Phan exist in other codebases (e.g. EXIT_STATUS).
                return Type::fromObject(
                    constant($name)
                )->asUnionType();
            }

            // Figure out the name of the constant if it's
            // a string.
            $constant_name = $name ?? '';

            // If the constant is referring to the current
            // class, return that as a type
            if (Type::isSelfTypeString($constant_name) || Type::isStaticTypeString($constant_name)) {
                return $this->visitClassNode($node);
            }

            try {
                $constant = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getConst();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return new UnionType;
            }

            return $constant->getUnionType();
        }

        return new UnionType();
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
     *
     * @throws IssueException
     * An exception is thrown if we can't find the constant
     */
    public function visitClassConst(Node $node) : UnionType
    {
        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConst();

            return $constant->getUnionType();
        } catch (NodeException $exception) {
            $this->emitIssue(
                Issue::Unanalyzable,
                $node->lineno ?? 0
            );
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitProp(Node $node) : UnionType
    {
        return $this->analyzeProp($node, false);
    }

    /**
     * Analyzes a node with kind `\ast\AST_PROP` or `\ast\AST_STATIC_PROP`
     *
     * @param Node $node
     * The instance/static property access node.
     *
     * @param bool $is_static
     * True if this is a static property fetch,
     * false if this is an instance property fetch.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    private function analyzeProp(Node $node, bool $is_static) : UnionType
    {
        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getProperty($node->children['prop'], $is_static);

            // Map template types to concrete types
            if ($property->getUnionType()->hasTemplateType()) {

                // Get the type of the object calling the property
                $expression_type = UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $node->children['expr']
                );

                $union_type = $property->getUnionType()->withTemplateParameterTypeMap(
                    $expression_type->getTemplateParameterTypeMap($this->code_base)
                );

                return $union_type;
            }

            return $property->getUnionType();
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (CodeBaseException $exception) {
            $property_name = $node->children['prop'];
            $this->emitIssue(
                Issue::UndeclaredProperty,
                $node->lineno ?? 0,
                "{$exception->getFQSEN()}->{$property_name}"
            );
        } catch (UnanalyzableException $exception) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        } catch (NodeException $exception) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticProp(Node $node) : UnionType
    {
        return $this->analyzeProp($node, true);
    }


    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCall(Node $node) : UnionType
    {
        $expression = $node->children['expr'];
        $function_list_generator = (new ContextNode(
            $this->code_base,
            $this->context,
            $expression
        ))->getFunctionFromNode();

        $possible_types = new UnionType();
        foreach ($function_list_generator as $function) {
            assert($function instanceof FunctionInterface);
            $possible_types->addUnionType($function->getUnionType());
        }

        return $possible_types;
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
    public function visitStaticCall(Node $node) : UnionType
    {
        return $this->visitMethodCall($node);
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMethodCall(Node $node) : UnionType
    {
        $method_name = $node->children['method'] ?? '';

        // Give up on any complicated nonsense where the
        // method name is a variable such as in
        // `$variable->$function_name()`.
        if ($method_name instanceof Node) {
            return new UnionType();
        }

        // Method names can some times turn up being
        // other method calls.
        \assert(
            \is_string($method_name),
            "Method name must be a string. Something else given."
        );

        try {
            foreach ($this->classListFromNode(
                    $node->children['class'] ?? $node->children['expr']
                ) as $class
            ) {
                if (!$class->hasMethodWithName(
                    $this->code_base,
                    $method_name
                )) {
                    continue;
                }

                try {
                    $method = $class->getMethodByName(
                        $this->code_base,
                        $method_name
                    );

                    $union_type = $method->getUnionType();

                    // Map template types to concrete types
                    if ($union_type->hasTemplateType()) {

                        // Get the type of the object calling the property
                        $expression_type = UnionTypeVisitor::unionTypeFromNode(
                            $this->code_base,
                            $this->context,
                            $node->children['expr']
                        );

                        // Map template types to concrete types
                        $union_type = $union_type->withTemplateParameterTypeMap(
                            $expression_type->getTemplateParameterTypeMap($this->code_base)
                        );
                    }

                    // Remove any references to \static or \static[]
                    // once we're talking about the method's return
                    // type outside of its class
                    if ($union_type->hasStaticType()) {
                        $union_type = clone($union_type);
                        $union_type->removeType(\Phan\Language\Type\StaticType::instance(false));
                    }

                    if ($union_type->genericArrayElementTypes()->hasStaticType()) {

                        $union_type = clone($union_type);

                        // Find the static type on the list
                        $static_type = ArraySet::find($union_type->getTypeSet(), function (Type $type) : bool {
                            return (
                                $type->isGenericArray()
                                && $type->genericArrayElementType()->isStaticType()
                            );
                        });

                        // Remove it from the list
                        $union_type->removeType($static_type);
                    }

                    return $union_type;
                } catch (IssueException $exception) {
                    return new UnionType();
                }
            }
        } catch (IssueException $exception) {
            // Swallow it
        } catch (CodeBaseException $exception) {
            $this->emitIssue(
                Issue::UndeclaredClassMethod,
                $node->lineno ?? 0,
                $method_name,
                (string)$exception->getFQSEN()
            );
        }

        return new UnionType();
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
    public function visitAssign(Node $node) : UnionType
    {
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
    public function visitUnaryOp(Node $node) : UnionType
    {
        // Shortcut some easy operators
        switch ($node->flags) {
            case \ast\flags\UNARY_BOOL_NOT:
                return BoolType::instance(false)->asUnionType();
        }

        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_MINUS`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryMinus(Node $node) : UnionType
    {
        return Type::fromObject($node->children['expr'])->asUnionType();
    }

    /*
     * @param Node $node
     * A node holding a class
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function visitClassNode(Node $node) : UnionType
    {
        // Things of the form `new $class_name();`
        if ($node->kind == \ast\AST_VAR) {
            return new UnionType();
        }

        // Anonymous class of form `new class { ... }`
        if ($node->kind == \ast\AST_CLASS
            && $node->flags & \ast\flags\CLASS_ANONYMOUS
        ) {
            // Generate a stable name for the anonymous class
            $anonymous_class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();

            // Turn that into a fully qualified name
            $fqsen = FullyQualifiedClassName::fromStringInContext(
                $anonymous_class_name,
                $this->context
            );

            // Turn that into a union type
            return Type::fromFullyQualifiedString((string)$fqsen)->asUnionType();
        }

        // Things of the form `new $method->name()`
        if ($node->kind !== \ast\AST_NAME) {
            return new UnionType();
        }

        // Get the name of the class
        $class_name = $node->children['name'];

        // If this is a straight-forward class name, recurse into the
        // class node and get its type
        $is_static_type_string = Type::isStaticTypeString($class_name);
        if (!($is_static_type_string || Type::isSelfTypeString($class_name))) {
            return self::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node
            );
        }

        // This is a self-referential node
        if (!$this->context->isInClassScope()) {
            $this->emitIssue(
                Issue::ContextNotObject,
                $node->lineno ?? 0,
                $class_name
            );

            return new UnionType();
        }

        // Reference to a parent class
        if ($class_name === 'parent') {
            $class = $this->context->getClassInScope(
                $this->code_base
            );

            if (!$class->hasParentType()) {
                $this->emitIssue(
                    Issue::ParentlessClass,
                    $node->lineno ?? 0,
                    (string)$class->getFQSEN()
                );

                return new UnionType();
            }

            return Type::fromFullyQualifiedString(
                (string)$class->getParentClassFQSEN()
            )->asUnionType();
        }

        $result = Type::fromFullyQualifiedString(
            (string)$this->context->getClassFQSEN()
        )->asUnionType();

        if ($is_static_type_string) {
            $result->addType(StaticType::instance(false));
        }
        return $result;
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|mixed $node
     * The node for which we'd like to determine its type
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    public static function unionTypeFromClassNode(
        CodeBase $code_base,
        Context $context,
        $node
    ) : UnionType {

        // If this is a list, build a union type by
        // recursively visiting the child nodes
        if ($node instanceof Node
            && $node->kind == \ast\AST_NAME_LIST
        ) {
            $union_type = new UnionType;
            foreach ($node->children ?? [] as $child_node) {
                $union_type->addUnionType(
                    self::unionTypeFromClassNode(
                        $code_base,
                        $context,
                        $child_node
                    )
                );
            }
            return $union_type;
        }

        // For simple nodes or very complicated nodes,
        // recurse
        if (!($node instanceof \ast\Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return self::unionTypeFromNode(
                $code_base,
                $context,
                $node
            );
        }

        $class_name = $node->children['name'];

        if ('parent' === $class_name) {
            if (!$context->isInClassScope()) {
                throw new IssueException(
                    Issue::fromType(Issue::ContextNotObject)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [$class_name]
                    )
                );
            }

            $class = $context->getClassInScope($code_base);

            if ($class->isTrait()) {
                throw new IssueException(
                    Issue::fromType(Issue::TraitParentReference)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [(string)$context->getClassFQSEN() ]
                    )
                );
            }

            if (!$class->hasParentType()) {
                throw new IssueException(
                    Issue::fromType(Issue::ParentlessClass)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$context->getClassFQSEN() ]
                    )
                );
            }

            $parent_class_fqsen = $class->getParentClassFQSEN();

            if (!$code_base->hasClassWithFQSEN($parent_class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClass)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$parent_class_fqsen ]
                    )
                );
            } else {
                $parent_class = $code_base->getClassByFQSEN(
                    $parent_class_fqsen
                );

                return $parent_class->getUnionType();
            }
        }

        // We're going to convert the class reference to a type
        $type = null;

        // Check to see if the name is fully qualified
        if (!($node->flags & \ast\flags\NAME_NOT_FQ)) {
            if (0 !== strpos($class_name, '\\')) {
                $class_name = '\\' . $class_name;
            }

            $type = Type::fromFullyQualifiedString(
                $class_name
            );
        } else {
            $type = Type::fromStringInContext(
                $class_name,
                $context,
                Type::FROM_NODE
            );
        }

        return $type->asUnionType();
    }

    /**
     * @return \Generator|Clazz
     */
    public static function classListFromNodeAndContext(CodeBase $code_base, Context $context, Node $node) {
        return (new UnionTypeVisitor($code_base, $context, true))->classListFromNode($node);
    }

    /**
     * @return \Generator|Clazz[]
     * A list of classes associated with the given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function classListFromNode(Node $node)
    {
        // Get the types associated with the node
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node
        );

        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($union_type->nonNativeTypes()->getTypeSet() as $class_type) {
            // Get the class FQSEN
            $class_fqsen = $class_type->asClassFQSEN();

            // See if the class exists
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClassReference)(
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$class_fqsen ]
                    )
                );
            }

            yield $this->code_base->getClassByFQSEN($class_fqsen);
        }
    }
}
