<?php declare(strict_types=1);

namespace Phan\AST;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Phan\Analysis\AssignOperatorFlagVisitor;
use Phan\Analysis\BinaryOperatorFlagVisitor;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\EmptyFQSENException;
use Phan\Exception\FQSENException;
use Phan\Exception\InvalidFQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\RecursionDepthException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClassStringType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\SelfType;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;
use Phan\Library\StringUtil;
use TypeError;
use function is_scalar;
use function is_string;

/**
 * Determines the UnionType associated with a given node.
 *
 * @see UnionTypeVisitor::unionTypeFromNode()
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument node is complicated
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal node is complicated
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
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
        // Inlined to be more efficient.
        // parent::__construct($code_base, $context);
        $this->code_base = $code_base;
        $this->context = $context;

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
            if ($node === null) {
                // NOTE: Parameter default checks expect this to return empty
                return UnionType::empty();
            }
            return Type::fromObject($node)->asUnionType();
        }
        $node_id = \spl_object_id($node);

        $cached_union_type = $context->getUnionTypeOfNodeIfCached($node_id, $should_catch_issue_exception);
        if ($cached_union_type !== null) {
            return $cached_union_type;
        }

        if ($should_catch_issue_exception) {
            try {
                $union_type = (new self(
                    $code_base,
                    $context,
                    $should_catch_issue_exception
                ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'visit'}($node);
                $context->setCachedUnionTypeOfNode($node_id, $union_type, true);
                return $union_type;
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $context,
                    $exception->getIssueInstance()
                );
                return UnionType::empty();
            }
        }

        $union_type = (new self(
            $code_base,
            $context,
            $should_catch_issue_exception
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'visit'}($node);

        $context->setCachedUnionTypeOfNode($node_id, $union_type, false);
        return $union_type;
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
        return UnionType::empty();
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
        // TODO: Check if union type is sane (string/int)
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        )->asNonLiteralType();
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
        // TODO: Check if union type is sane (string/int)
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        )->asNonLiteralType();
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
        // TODO: Check if union type is sane (string/int)
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        )->asNonLiteralType()->getTypeAfterIncOrDec();
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
     *
     * TODO: in PostOrderAnalysisVisitor, set the type to unknown for ++/--
     */
    public function visitPreInc(Node $node) : UnionType
    {
        // TODO: Check if union type is sane (string/int)
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        )->asNonLiteralType()->getTypeAfterIncOrDec();
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
        // TODO: Check if union type is sane (Any object type)
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
        return UnionType::empty();
    }

    private static function literalIntUnionType(int $value) : UnionType
    {
        return LiteralIntType::instanceForValue($value, false)->asUnionType();
    }

    private static function literalStringUnionType(string $value) : UnionType
    {
        return LiteralStringType::instanceForValue($value, false)->asUnionType();
    }

    const MAGIC_CONST_NAME_MAP = [
        ast\flags\MAGIC_LINE => '__LINE__',
        ast\flags\MAGIC_FILE => '__FILE__',
        ast\flags\MAGIC_DIR => '__DIR__',
        ast\flags\MAGIC_NAMESPACE => '__NAME__',
        ast\flags\MAGIC_FUNCTION => '__FUNCTION__',
        ast\flags\MAGIC_METHOD => '__METHOD__',
        ast\flags\MAGIC_CLASS => '__CLASS__',
        ast\flags\MAGIC_TRAIT => '__TRAIT__',
    ];

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
        $flags = $node->flags;
        switch ($flags) {
            case ast\flags\MAGIC_CLASS:
                if ($this->context->isInClassScope()) {
                    // Works in classes, traits, and interfaces
                    return self::literalStringUnionType(\ltrim($this->context->getClassFQSEN()->__toString(), '\\'));
                }
                break;
            case ast\flags\MAGIC_FUNCTION:
                if ($this->context->isInFunctionLikeScope()) {
                    $fqsen = $this->context->getFunctionLikeFQSEN();
                    return self::literalStringUnionType($fqsen->isClosure() ? '{closure}' : $fqsen->getName());
                }
                break;
            case ast\flags\MAGIC_METHOD:
                if ($this->context->isInFunctionLikeScope()) {
                    // Emits method or function FQSEN.
                    $fqsen = $this->context->getFunctionLikeFQSEN();
                    return self::literalStringUnionType($fqsen->isClosure() ? '{closure}' : \ltrim($fqsen->__toString(), '\\'));
                }
                break;
            case ast\flags\MAGIC_DIR:
                return self::literalStringUnionType(\dirname(Config::projectPath($this->context->getFile())));
            case ast\flags\MAGIC_FILE:
                return self::literalStringUnionType(Config::projectPath($this->context->getFile()));
            case ast\flags\MAGIC_LINE:
                return self::literalIntUnionType($node->lineno);
            case ast\flags\MAGIC_NAMESPACE:
                return self::literalStringUnionType(\ltrim($this->context->getNamespace(), '\\'));
            case ast\flags\MAGIC_TRAIT:
                // TODO: Could check if in trait, low importance.
                if (!$this->context->isInClassScope()) {
                    break;
                }
                $fqsen = $this->context->getClassFQSEN();
                if ($this->code_base->hasClassWithFQSEN($fqsen)) {
                    if (!$this->code_base->getClassByFQSEN($fqsen)->isTrait()) {
                        break;
                    }
                }
                return self::literalStringUnionType((string)\ltrim($this->context->getClassFQSEN()->__toString(), '\\'));
            default:
                return StringType::instance(false)->asUnionType();
        }
        $this->emitIssue(
            Issue::UndeclaredMagicConstant,
            $node->lineno,
            self::MAGIC_CONST_NAME_MAP[$flags]
        );

        return self::literalStringUnionType('');
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_REF`
     * @see self::visitAssign()
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
        // (e.g. should subsequent assignments affect the right-hand Node?)
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
     * @return ?Type
     * @throws IssueException if the parent type could not be resolved
     */
    public static function findParentType(Context $context, CodeBase $code_base)
    {
        if (!$context->isInClassScope()) {
            throw new IssueException(
                Issue::fromType(Issue::ContextNotObject)(
                    $context->getFile(),
                    $context->getLineNumberStart(),
                    ['parent']
                )
            );
        }
        $class = $context->getClassInScope($code_base);

        $parent_type_option = $class->getParentTypeOption();
        if ($parent_type_option->isDefined()) {
            return $parent_type_option->get();
        }

        // Using `parent` in a class or interface without a parent is always invalid.
        // Doing this in a trait may or not be valid.
        if (!$class->isTrait()) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::ParentlessClass,
                $context->getLineNumberStart(),
                (string)$class->getFQSEN()
            );
        }

        return null;
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
        $name = $node->children['name'];
        try {
            if ($node->flags & \ast\flags\NAME_NOT_FQ) {
                if (\strcasecmp('parent', $name) === 0) {
                    $parent_type = self::findParentType($this->context, $this->code_base);
                    return $parent_type ? $parent_type->asUnionType() : UnionType::empty();
                }

                return Type::fromStringInContext(
                    $name,
                    $this->context,
                    Type::FROM_NODE
                )->asUnionType();
            }

            if ($node->flags & \ast\flags\NAME_RELATIVE) {  // $x = new namespace\Foo();
                $name = \rtrim($this->context->getNamespace(), '\\') . '\\' . $name;
                return Type::fromFullyQualifiedString(
                    $name
                )->asUnionType();
            }
            // Sometimes 0 for a fully qualified name?

            // @phan-suppress-next-line PhanThrowTypeAbsentForCall hopefully impossible
            return Type::fromFullyQualifiedString(
                '\\' . $name
            )->asUnionType();
        } catch (FQSENException $e) {
            $this->emitIssue(
                $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInClasslike : Issue::InvalidFQSENInClasslike,
                $node->lineno,
                $e->getFQSEN()
            );
            return UnionType::empty();
        }
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
     *
     * @throws AssertionError if the type flags were unknown
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
                throw new AssertionError("All flags must match. Found "
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
        $union_type = $this->__invoke($node->children['type']);

        // Make each nullable
        return $union_type->asMappedUnionType(static function (Type $type) : Type {
            return $type->withIsNullable(true);
        });
    }

    /**
     * @param int|float|string|Node $node
     * @return ?UnionType
     */
    public static function unionTypeFromLiteralOrConstant(CodeBase $code_base, Context $context, $node)
    {
        if ($node instanceof Node) {
            // TODO: Could check for arrays of constants or literals, and convert those to the generic array types
            if ($node->kind === \ast\AST_CONST || $node->kind === \ast\AST_CLASS_CONST) {
                try {
                    return UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node, false);
                } catch (IssueException $_) {
                    return null;
                }
            }
            return null;
        }
        // Otherwise, this is an int/float/string.
        if (!is_scalar($node)) {
            throw new TypeError('node must be Node or scalar');
        }
        return Type::fromObject($node)->asUnionType();
    }

    /**
     * Returns the union type from a type in a parameter/return signature of a function-like.
     * This preserves `self` and `static`
     * @param Node $node
     */
    public function fromTypeInSignature($node) : UnionType
    {
        $is_nullable = $node->kind === ast\AST_NULLABLE_TYPE;
        if ($is_nullable) {
            $node = $node->children['type'];
            if (!$node instanceof Node) {
                // Work around bug (in polyfill parser?)
                return UnionType::empty();
            }
        }
        $kind = $node->kind;
        if ($kind === ast\AST_TYPE) {
            $result = $this->visitType($node);
        } else {
            if ($kind !== ast\AST_NAME) {
                throw new AssertionError("Expected either a type or a name in the signature: node: " . Debug::nodeToString($node));
            }
            if ($this->context->getScope()->isInTraitScope()) {
                $name = \strtolower($node->children['name']);
                if ($name === 'self') {
                    return SelfType::instance($is_nullable)->asUnionType();
                } elseif ($name === 'static') {
                    return StaticType::instance($is_nullable)->asUnionType();
                }
            }
            $result = $this->visitName($node);
        }
        if ($is_nullable) {
            return $result->nullableClone();
        }
        return $result;
    }

    /**
     * @param int|float|string|Node $cond
     * @return ?bool
     */
    public static function checkCondUnconditionalTruthiness($cond)
    {
        if ($cond instanceof Node) {
            if ($cond->kind === \ast\AST_CONST) {
                $name = $cond->children['name'];
                if ($name->kind === \ast\AST_NAME) {
                    switch (\strtolower($name->children['name'])) {
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
        if (!is_scalar($cond)) {
            throw new TypeError('$cond must be Node or scalar');
        }
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
            ))->__invoke($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))->__invoke($cond_node);

            if (!isset($node->children['true'])) {
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
                $true_type_is_empty = $true_type->isEmpty();
                if (!$false_type->isEmpty()) {
                    // E.g. `foo() ?: 2` where foo is nullable or possibly false.
                    if ($true_type->containsFalsey()) {
                        $true_type = $true_type->nonFalseyClone();
                    }
                }

                // Add the type for the 'true' side to the 'false' side
                $union_type = $true_type->withUnionType($false_type);

                // If one side has an unknown type but the other doesn't
                // we can't let the unseen type get erased. Unfortunately,
                // we need to add 'mixed' in so that we know it could be
                // anything at all.
                //
                // See Issue #104
                if ($true_type_is_empty xor $false_type->isEmpty()) {
                    $union_type = $union_type->withType(
                        MixedType::instance(false)
                    );
                }

                return $union_type;
            }
        } else {
            $true_context = $this->context;
            $false_context = $this->context;
        }
        // Postcondition: This is (cond_expr) ? (true_expr) : (false_expr)

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
        $children = $node->children;
        if (\count($children) > 0) {
            $value_types_builder = new UnionTypeBuilder();

            $key_set = $this->getEquivalentArraySet($node);
            if (\is_array($key_set) && \count($key_set) === \count($children)) {
                return $this->createArrayShapeType($children, $key_set)->asUnionType();
            }

            foreach ($children as $child) {
                if (!($child instanceof Node)) {
                    // Skip this, we already emitted a syntax error.
                    continue;
                }
                $value = $child->children['value'];
                if ($value instanceof Node) {
                    $element_value_type = UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $this->context,
                        $value,
                        $this->should_catch_issue_exception
                    );
                    if ($element_value_type->isEmpty()) {
                        $value_types_builder->addType(MixedType::instance(false));
                    } else {
                        $value_types_builder->addUnionType($element_value_type);
                    }
                } else {
                    $value_types_builder->addType(Type::fromObject($value));
                }
            }
            // TODO: Normalize value_types, e.g. false+true=bool, array<int,T>+array<string,T>=array<mixed,T>
            $key_type_enum = GenericArrayType::getKeyTypeOfArrayNode($this->code_base, $this->context, $node, $this->should_catch_issue_exception);
            return $value_types_builder->getUnionType()->asNonEmptyGenericArrayTypes($key_type_enum);
        }

        // TODO: Also return types such as array<int, mixed>?
        // TODO: Fix or suppress false positives PhanTypeArraySuspicious caused by loops...
        return ArrayShapeType::empty(false)->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_YIELD`
     *
     * @param Node $unused_node
     * A yield node. Does not affect the union type
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitYield(Node $unused_node) : UnionType
    {
        $context = $this->context;
        if (!$context->isInFunctionLikeScope()) {
            return UnionType::empty();
        }

        // Get the method/function/closure we're in
        $method = $context->getFunctionLikeInScope($this->code_base);
        $method_generator_type = $method->getReturnTypeAsGeneratorTemplateType();
        $type_list = $method_generator_type->getTemplateParameterTypeList();
        if (\count($type_list) < 3 || \count($type_list) > 4) {
            return UnionType::empty();
        }
        // Return TSend of Generator<TKey,TValue,TSend[,TReturn]>
        return $type_list[2];
    }

    /**
     * @return ?array<int|string,true>
     * Caller should check if the result size is too small and handle it (for duplicate keys)
     * Returns null if one or more keys could not be resolved
     *
     * @see ContextNode::getEquivalentPHPArrayElements()
     */
    private function getEquivalentArraySet(Node $node)
    {
        $elements = [];
        $context_node = null;
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                ContextNode::warnAboutEmptyArrayElements($this->code_base, $this->context, $node);
                continue;
            }
            $key_node = $child_node->children['key'];
            // NOTE: this has some overlap with DuplicateKeyPlugin
            if ($key_node === null) {
                $elements[] = true;
            } elseif (is_scalar($key_node)) {
                $elements[$key_node] = true;  // Check for float?
            } else {
                if ($context_node === null) {
                    $context_node = new ContextNode($this->code_base, $this->context, null);
                }
                $key = $context_node->getEquivalentPHPValueForNode($key_node, ContextNode::RESOLVE_CONSTANTS);
                if (is_scalar($key)) {
                    $elements[$key] = true;
                } else {
                    return null;
                }
            }
        }
        return $elements;
    }

    /**
     * @param array<int,Node> $children
     * @param array<int|string,true> $key_set
     */
    private function createArrayShapeType(array $children, array $key_set) : ArrayShapeType
    {
        \reset($key_set);
        $field_types = [];

        foreach ($children as $child) {
            $value = $child->children['value'];
            $key = \key($key_set);
            \next($key_set);

            if ($value instanceof Node) {
                $element_value_type = UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $value,
                    $this->should_catch_issue_exception
                );
                $field_types[$key] = $element_value_type->isEmpty() ? MixedType::instance(false)->asUnionType() : $element_value_type;
            } else {
                $field_types[$key] = Type::fromObject($value)->asUnionType();
            }
        }
        return ArrayShapeType::fromFieldTypes($field_types, false);
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
            $this->context,
            $this->should_catch_issue_exception
        ))->__invoke($node);
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
        ))->__invoke($node);
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
     */
    public function visitCast(Node $node) : UnionType
    {
        // TODO: Check if the cast is allowed based on the right side type
        $expr_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
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
                if ($expr_type->isExclusivelyArray()) {
                    return UnionType::fromFullyQualifiedString('\stdClass');
                }
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
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            $this->emitIssue(
                Issue::InvalidNode,
                $node->lineno,
                "Invalid ClassName for new ClassName()"
            );
            return ObjectType::instance(false)->asUnionType();
        }
        $union_type = $this->visitClassNameNode($class_node);
        if ($union_type->isEmpty()) {
            return ObjectType::instance(false)->asUnionType();
        }

        // TODO: re-use the underlying type set in the common case
        // Maybe UnionType::fromMap

        // For any types that are templates, map them to concrete
        // types based on the parameters passed in.
        return UnionType::of(\array_map(function (Type $type) use ($node) : Type {

            // Get a fully qualified name for the type
            // TODO: Add a test of `new $closure()` warning.
            $fqsen = FullyQualifiedClassName::fromType($type);

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

            // Map each argument to its type
            /** @param Node|string|int|float $arg_node */
            $arg_type_list = \array_map(function ($arg_node) : UnionType {
                return UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $arg_node
                );
            }, $node->children['args']->children);

            // Get closures to extract template types based on the types of the constructor
            // so that we can figure out what template types we're going to be mapping
            $template_type_resolvers = $class->getGenericConstructorBuilder($this->code_base);

            // And use those closures to infer the (possibly transformed) types
            $template_type_list = [];
            foreach ($template_type_resolvers as $template_type_resolver) {
                $template_type_list[] = $template_type_resolver($arg_type_list, $this->context);
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
        $code_base = $this->code_base;
        $context = $this->context;
        // Check to make sure the left side is valid
        UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node->children['expr']);
        // Get the type that we're checking it against, check if it is valid.
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return BoolType::instance(false)->asUnionType();
        }
        $type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $class_node
        );
        // TODO: Unify UnionTypeVisitor, AssignmentVisitor, and PostOrderAnalysisVisitor
        if (!$type->isEmpty() && !$type->hasObjectTypes()) {
            if ($class_node->kind !== \ast\AST_NAME &&
                    !$type->canCastToUnionType(StringType::instance(false)->asUnionType())) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeInvalidInstanceof,
                    $context->getLineNumberStart(),
                    (string)$type
                );
            }
        }

        return BoolType::instance(false)->asUnionType();
    }

    /** @internal - Duplicated for performance. Use PhanAnnotationAdder instead */
    const FLAG_IGNORE_NULLABLE = 1 << 29;

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
     *
     * @throws IssueException
     * if the dimension access is invalid
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

        // If none of the types we found were arrays with elements,
        // then check for ArrayAccess
        static $array_access_type;
        static $simple_xml_element_type;  // SimpleXMLElement doesn't `implement` ArrayAccess, but can be accessed that way. See #542
        static $null_type;
        static $string_type;
        static $int_union_type;
        static $int_or_string_union_type;

        if ($array_access_type === null) {
            // array offsets work on strings, unfortunately
            // Double check that any classes in the type don't
            // have ArrayAccess
            $array_access_type =
                Type::fromNamespaceAndName('\\', 'ArrayAccess', false);
            $simple_xml_element_type =
                Type::fromNamespaceAndName('\\', 'SimpleXMLElement', false);
            $null_type = NullType::instance(false);
            $string_type = StringType::instance(false);
            $int_union_type = IntType::instance(false)->asUnionType();
            $int_or_string_union_type = new UnionType([IntType::instance(false), StringType::instance(false)], true);
        }

        if ($union_type->hasTopLevelArrayShapeTypeInstances()) {
            $element_type = $this->resolveArrayShapeElementTypes($node, $union_type);
            if ($element_type !== null) {
                return $element_type;
            }
        }

        $dim_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['dim'],
            true
        );

        // Figure out what the types of accessed array
        // elements would be.
        $generic_types = $union_type->genericArrayElementTypes();

        // If we have generics, we're all set
        if (!$generic_types->isEmpty()) {
            if (!($node->flags & self::FLAG_IGNORE_NULLABLE) && self::isSuspiciousNullable($union_type)) {
                $this->emitIssue(
                    Issue::TypeArraySuspiciousNullable,
                    $node->lineno,
                    (string)$union_type
                );
            }

            if (!$dim_type->isEmpty()) {
                try {
                    $should_check = !$union_type->hasMixedType() && !$union_type->asExpandedTypes($this->code_base)->hasArrayAccess();
                } catch (RecursionDepthException $_) {
                    $should_check = false;
                }
                if ($should_check) {
                    if (Config::getValue('scalar_array_key_cast')) {
                        $expected_key_type = $int_or_string_union_type;
                    } else {
                        $expected_key_type = GenericArrayType::unionTypeForKeyType(
                            GenericArrayType::keyTypeFromUnionTypeKeys($union_type),
                            GenericArrayType::CONVERT_KEY_MIXED_TO_INT_OR_STRING_UNION_TYPE
                        );
                    }

                    if (!$dim_type->canCastToUnionType($expected_key_type)) {
                        $issue_type = Issue::TypeMismatchDimFetch;

                        if ($dim_type->containsNullable() && $dim_type->nonNullableClone()->canCastToUnionType($expected_key_type)) {
                            $issue_type = Issue::TypeMismatchDimFetchNullable;
                        }

                        if ($this->should_catch_issue_exception) {
                            $this->emitIssue(
                                $issue_type,
                                $node->lineno,
                                (string)$union_type,
                                (string)$dim_type,
                                (string)$expected_key_type
                            );
                            return $generic_types;
                        }

                        throw new IssueException(
                            Issue::fromType($issue_type)(
                                $this->context->getFile(),
                                $node->lineno,
                                [(string)$union_type, (string)$dim_type, (string)$expected_key_type]
                            )
                        );
                    }
                }
            }
            return $generic_types;
        }

        // If the only type is null, we don't know what
        // accessed items will be
        if ($union_type->isType($null_type)) {
            return UnionType::empty();
        }

        $element_types = UnionType::empty();

        // You can access string characters via array index,
        // so we'll add the string type to the result if we're
        // indexing something that could be a string
        if ($union_type->isNonNullStringType()
            || ($union_type->canCastToUnionType($string_type->asUnionType()) && !$union_type->hasMixedType())
        ) {
            if (Config::get_closest_target_php_version_id() < 70100 && $union_type->isNonNullStringType()) {
                $this->analyzeNegativeStringOffsetCompatibility($node, $dim_type);
            }

            if (!$dim_type->isEmpty() && !$dim_type->canCastToUnionType($int_union_type)) {
                // TODO: Efficient implementation of asExpandedTypes()->hasArrayAccess()?
                if (!$union_type->isEmpty() && !$union_type->asExpandedTypes($this->code_base)->hasArrayLike()) {
                    $this->emitIssue(
                        Issue::TypeMismatchDimFetch,
                        $node->lineno,
                        $union_type,
                        (string)$dim_type,
                        $int_union_type
                    );
                }
            }
            $element_types = $element_types->withType($string_type);
        }

        if ($element_types->isEmpty()) {
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
            } catch (CodeBaseException $_) {
            } catch (RecursionDepthException $_) {
            }

            if (!$union_type->hasArrayLike()) {
                $this->emitIssue(
                    Issue::TypeArraySuspicious,
                    $node->lineno,
                    (string)$union_type
                );
            }
        }

        return $element_types;
    }

    private static function isSuspiciousNullable(UnionType $union_type) : bool
    {
        foreach ($union_type->getTypeSet() as $type) {
            if ($type->getIsNullable() && ($type instanceof ArrayType || $type instanceof StringType)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return ?UnionType
     */
    private function resolveArrayShapeElementTypes(Node $node, UnionType $union_type)
    {
        $dim_node = $node->children['dim'];
        $dim_value = $dim_node instanceof Node ? (new ContextNode($this->code_base, $this->context, $dim_node))->getEquivalentPHPScalarValue() : $dim_node;
        // TODO: detect and warn about null
        if (!is_scalar($dim_value)) {
            return null;
        }

        $resulting_element_type = self::resolveArrayShapeElementTypesForOffset($union_type, $dim_value);

        if ($resulting_element_type === null) {
            return null;
        }
        if ($resulting_element_type === false) {
            $this->emitIssue(
                Issue::TypeInvalidDimOffset,
                $dim_node->lineno ?? $node->lineno,
                StringUtil::jsonEncode($dim_value),
                (string)$union_type
            );
            // $union_type is exclusively array shape types, but those don't contain the field $dim_value.
            // It's undefined (which becomes null)
            return NullType::instance(false)->asUnionType();
        }
        return $resulting_element_type;
    }

    /**
     * @param UnionType $union_type a union type with at least one top-level array shape type
     * @param int|string|float|bool $dim_value a scalar dimension. TODO: Warn about null?
     * @return ?UnionType|?false
     *  returns false if there the offset was invalid and there are no ways to get that offset
     *  returns null if the dim_value offset could not be found, but there were other generic array types
     */
    public static function resolveArrayShapeElementTypesForOffset(UnionType $union_type, $dim_value)
    {
        /**
         * @var bool $has_non_array_shape_type this will be true if there are types that support array access
         *           but have unknown array shapes in $union_type
         */
        $has_non_array_shape_type = false;
        $resulting_element_type = null;
        foreach ($union_type->getTypeSet() as $type) {
            if (!($type instanceof ArrayShapeType)) {
                if ($type instanceof StringType) {
                    if (\is_int($dim_value)) {
                        // If we request a string offset from a string, that's not valid. Only accept integer dimensions as valid.
                        // in php, indices of strings can be negative
                        if ($resulting_element_type !== null) {
                            $resulting_element_type = $resulting_element_type->withType(StringType::instance(false));
                        } else {
                            $resulting_element_type = StringType::instance(false)->asUnionType();
                        }
                        $has_non_array_shape_type = true;
                    } else {
                        // TODO: Warn about string indices of strings?
                    }
                } elseif ($type->isArrayLike() || $type->isObject() || $type instanceof MixedType) {
                    // TODO: Could be more precise about check for ArrayAccess
                    $has_non_array_shape_type = true;
                    continue;
                }
                continue;
            }
            $element_type = $type->getFieldTypes()[$dim_value] ?? null;
            if ($element_type !== null) {
                // $element_type may be non-null but $element_type->isEmpty() may be true.
                // So, we use null to indicate failure below
                if ($resulting_element_type !== null) {
                    $resulting_element_type = $resulting_element_type->withUnionType($element_type);
                } else {
                    $resulting_element_type = $element_type;
                }
            }
        }
        if ($resulting_element_type === null) {
            if (!$has_non_array_shape_type) {
                // This is exclusively array shape types.
                // Return false to indicate that the offset doesn't exist in any of those array shape types.
                return false;
            }
            return null;
        }
        return $resulting_element_type;
    }

    /**
     * Visit a node with kind `\ast\AST_UNPACK`
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
     * if the unpack is on an invalid expression
     */
    public function visitUnpack(Node $node) : UnionType
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
        // TODO: Account for Traversable once there are generics for Traversable
        $generic_types =
            $union_type->genericArrayElementTypes();

        // If we have generics, we're all set
        if ($generic_types->isEmpty()) {
            if (!$union_type->asExpandedTypes($this->code_base)->hasIterable() && !$union_type->hasType(MixedType::instance(false))) {
                throw new IssueException(
                    Issue::fromType(Issue::TypeMismatchUnpackValue)(
                        $this->context->getFile(),
                        $node->lineno,
                        [(string)$union_type]
                    )
                );
            }
            return $generic_types;
        }
        // TODO: Once we have generic template types for Traversable and subclasses, rewrite this check to account for `new ArrayObject([2])`, etc.
        if (GenericArrayType::KEY_STRING === GenericArrayType::keyTypeFromUnionTypeKeys($union_type)) {
            throw new IssueException(
                Issue::fromType(Issue::TypeMismatchUnpackKey)(
                    $this->context->getFile(),
                    $node->lineno,
                    [(string)$union_type, 'string']
                )
            );
        }
        return $generic_types;
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

        if ($this->code_base->hasFunctionWithFQSEN($closure_fqsen)) {
            $func = $this->code_base->getFunctionByFQSEN($closure_fqsen);
        } else {
            $func = null;
        }

        return ClosureType::instanceWithClosureFQSEN(
            $closure_fqsen,
            $func
        )->asUnionType();
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
     *
     * @throws IssueException
     * if variable is undefined and being fetched
     */
    public function visitVar(Node $node) : UnionType
    {
        // $$var or ${...} (whose idea was that anyway?)
        $name_node = $node->children['name'];
        if (($name_node instanceof Node)) {
            // This is nonsense. Give up.
            $name_node_type = $this->__invoke($name_node);
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType([
                    StringType::instance(false),
                    IntType::instance(false),
                    NullType::instance(false)
                ]);
            }
            if (!$name_node_type->canCastToUnionType($int_or_string_type)) {
                Issue::maybeEmit($this->code_base, $this->context, Issue::TypeSuspiciousIndirectVariable, $name_node->lineno, (string)$name_node_type);
                return MixedType::instance(false)->asUnionType();
            }
            $name_node = $name_node_type->asSingleScalarValueOrNull();
            if ($name_node === null) {
                return MixedType::instance(false)->asUnionType();
            }
            // fall through
        }

        // foo(${42}) is technically valid PHP code, avoid TypeError
        $variable_name =
            (string)$name_node;

        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                // @phan-suppress-next-line PhanTypeMismatchReturnNullable variable existence was checked
                return Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name);
            }
            if (!Config::getValue('ignore_undeclared_variables_in_global_scope')
                || !$this->context->isInGlobalScope()
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $this->context->getFile(),
                        $node->lineno,
                        [$variable_name],
                        IssueFixSuggester::suggestVariableTypoFix($this->code_base, $this->context, $variable_name)
                    )
                );
            }
        } else {
            $variable = $this->context->getScope()->getVariableByName(
                $variable_name
            );

            return $variable->getUnionType();
        }

        return UnionType::empty();
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
        $result = '';
        foreach ($node->children as $part) {
            $part_string = $part instanceof Node ? UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $part
            )->asSingleScalarValueOrNullOrSelf() : $part;
            if (\is_object($part_string)) {
                return StringType::instance(false)->asUnionType();
            }
            $result .= $part_string;
        }
        return LiteralStringType::instanceForValue($result, false)->asUnionType();
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

            // Figure out the name of the constant if it's
            // a string.
            $constant_name = $name ?? '';

            // If the constant is referring to the current
            // class, return that as a type
            if (Type::isSelfTypeString($constant_name) || Type::isStaticTypeString($constant_name)) {
                return Type::fromStringInContext($constant_name, $this->context, Type::FROM_NODE)->asUnionType();
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
                return UnionType::empty();
            }

            return $constant->getUnionType();
        }

        return UnionType::empty();
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
        } catch (NodeException $_) {
            // ignore, this should warn elsewhere
        }

        return UnionType::empty();
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
            ))->getProperty($is_static);

            if ($property->isWriteOnly()) {
                $this->emitIssue(
                    $property->isFromPHPDoc() ? Issue::AccessWriteOnlyMagicProperty : Issue::AccessWriteOnlyProperty,
                    $node->lineno,
                    $property->asPropertyFQSENString(),
                    $property->getContext()->getFile(),
                    $property->getContext()->getLineNumberStart()
                );
            }

            $expr_node = $node->children['expr'] ?? null;
            if ($expr_node instanceof Node &&
                    $expr_node->kind === ast\AST_VAR &&
                    $expr_node->children['name'] === 'this') {
                $override_union_type = $this->context->getThisPropertyIfOverridden($property->getName());
                if ($override_union_type) {
                    // There was an earlier expression such as `$this->prop = 2;`
                    // fwrite(STDERR, "Saw override '$override_union_type' for $property\n");
                    return $override_union_type;
                }
            }

            $union_type = $property->getUnionType()->withStaticResolvedInContext($property->getContext());
            // Map template types to concrete types
            if ($union_type->hasTemplateTypeRecursive()) {
                // Get the type of the object calling the property
                $expression_type = UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $expr_node
                );

                $union_type = $union_type->withTemplateParameterTypeMap(
                    $expression_type->getTemplateParameterTypeMap($this->code_base)
                );

                return $union_type;
            }

            return $union_type;
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (CodeBaseException $exception) {
            $exception_fqsen = $exception->getFQSEN();
            $suggestion = null;
            $property_name = $node->children['prop'];
            if ($exception_fqsen instanceof FullyQualifiedClassName && $this->code_base->hasClassWithFQSEN($exception_fqsen)) {
                $suggestion_class = $this->code_base->getClassByFQSEN($exception_fqsen);
                $suggestion = IssueFixSuggester::suggestSimilarProperty(
                    $this->code_base,
                    $this->context,
                    $suggestion_class,
                    $property_name,
                    false
                );
            }
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredProperty,
                $node->lineno,
                ["{$exception_fqsen}->{$property_name}"],
                $suggestion
            );
        } catch (UnanalyzableException $_) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        } catch (NodeException $_) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        }

        return UnionType::empty();
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
     *
     * @throws FQSENException if the fqsen for the called function is empty/invalid
     */
    public function visitCall(Node $node) : UnionType
    {
        $expression = $node->children['expr'];
        $function_list_generator = (new ContextNode(
            $this->code_base,
            $this->context,
            $expression
        ))->getFunctionFromNode();

        $possible_types = UnionType::empty();
        foreach ($function_list_generator as $function) {
            $function->analyzeReturnTypes($this->code_base);  // For daemon/server mode, call this to consistently ensure accurate return types.

            if ($function->hasDependentReturnType()) {
                $function_types = $function->getDependentReturnType($this->code_base, $this->context, $node->children['args']->children);
            } else {
                $function_types = $function->getUnionType();
            }
            $possible_types = $possible_types->withUnionType($function_types);
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
        //
        // TODO:
        if ($method_name instanceof Node) {
            return UnionType::empty();
        }

        // Method names can some times turn up being
        // other method calls.
        if (!is_string($method_name)) {
            $method_name = (string)$method_name;
        }

        try {
            $class_node = $node->children['class'] ?? $node->children['expr'];
            if (!($class_node instanceof Node)) {
                // E.g. `'string_literal'->method()`
                // Other places will also emit NonClassMethodCall for the same node
                $this->emitIssue(
                    Issue::NonClassMethodCall,
                    $node->lineno,
                    $method_name,
                    UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $class_node)
                );
                return UnionType::empty();
            }
            foreach ($this->classListFromNode($class_node) as $class) {
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
                    $method->analyzeReturnTypes($this->code_base);  // For daemon/server mode, call this to consistently ensure accurate return types.

                    if ($method->hasTemplateType()) {
                        try {
                            $method = $method->resolveTemplateType(
                                $this->code_base,
                                UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $class_node)
                            );
                        } catch (RecursionDepthException $_) {
                        }
                    }

                    if ($method->hasDependentReturnType()) {
                        $union_type = $method->getDependentReturnType($this->code_base, $this->context, $node->children['args']->children);
                    } else {
                        $union_type = $method->getUnionType();
                    }

                    // Map template types to concrete types
                    // TODO: When the template types are part of the method doc comment, don't look it up in the class union type
                    if (isset($node->children['expr']) && $union_type->hasTemplateTypeRecursive()) {
                        // Get the type of the object calling the method
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
                    // TODO: Convert static[] to array or object[]
                    foreach ($union_type->getTypeSet() as $type) {
                        if ($type->hasStaticOrSelfTypesRecursive($this->code_base)) {
                            $union_type = $union_type->withoutType($type);
                        }
                    }

                    return $union_type;
                } catch (IssueException $_) {
                    return UnionType::empty();
                }
            }
        } catch (IssueException $_) {
            // Swallow it
        } catch (CodeBaseException $exception) {
            $exception_fqsen = $exception->getFQSEN();
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredClassMethod,
                $node->lineno,
                [$method_name, (string)$exception->getFQSEN()],
                ($exception_fqsen instanceof FullyQualifiedClassName
                    ? IssueFixSuggester::suggestSimilarClassForMethod($this->code_base, $this->context, $exception_fqsen, $method_name, $node->kind === \ast\AST_STATIC_CALL)
                    : null)
            );
        }

        return UnionType::empty();
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
        $flags = $node->flags;
        if ($flags === \ast\flags\UNARY_BOOL_NOT) {
            return BoolType::instance(false)->asUnionType();
        }

        $result = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
        if ($flags === \ast\flags\UNARY_MINUS) {
            $this->warnAboutInvalidUnaryOp(
                $node,
                static function (Type $type) : bool {
                    return $type->isValidNumericOperand();
                },
                $result,
                '-',
                Issue::TypeInvalidUnaryOperandNumeric
            );
            return $result->applyUnaryMinusOperator();
        } elseif ($flags === \ast\flags\UNARY_PLUS) {
            $this->warnAboutInvalidUnaryOp(
                $node,
                static function (Type $type) : bool {
                    // NOTE: Don't be as strict because this is a way to cast to a number
                    return $type->isValidNumericOperand() || \get_class($type) === StringType::class;
                },
                $result,
                '+',
                Issue::TypeInvalidUnaryOperandNumeric
            );
            return $result->applyUnaryPlusOperator();
        } elseif ($flags === \ast\flags\UNARY_BITWISE_NOT) {
            $this->warnAboutInvalidUnaryOp(
                $node,
                static function (Type $type) : bool {
                    // Adding $type instanceof StringType in case it becomes necessary later
                    return $type->isValidNumericOperand() || $type instanceof StringType;
                },
                $result,
                '~',
                Issue::TypeInvalidUnaryOperandBitwiseNot
            );
            return $result->applyUnaryBitwiseNotOperator();
        }
        // UNARY_SILENCE
        return $result;
    }

    /**
     * @param Node $node with type AST_BINARY_OP
     * @param Closure(Type):bool $is_valid_type
     * @return void
     */
    private function warnAboutInvalidUnaryOp(
        Node $node,
        Closure $is_valid_type,
        UnionType $type,
        string $operator,
        string $issue_type
    ) {
        if ($type->isEmpty()) {
            return;
        }
        if (!$type->hasTypeMatchingCallback($is_valid_type)) {
            $this->emitIssue(
                $issue_type,
                $node->children['left']->lineno ?? $node->lineno,
                $operator,
                $type
            );
        }
    }

    /**
     * `print($str)` always returns 1.
     * See https://secure.php.net/manual/en/function.print.php#refsect1-function.print-returnvalues
     * @param Node $node @phan-unused-param
     */
    public function visitPrint(Node $node) : UnionType
    {
        return IntType::instance(false)->asUnionType();
    }

    /*
     * @param Node $node
     * A node holding a class name
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function visitClassNameNode(Node $node) : UnionType
    {
        $kind = $node->kind;
        // Anonymous class of form `new class { ... }`
        if ($kind === \ast\AST_CLASS
            && ($node->flags & \ast\flags\CLASS_ANONYMOUS)
        ) {
            // Generate a stable name for the anonymous class
            $anonymous_class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();

            // Turn that into a fully qualified name, and that into a union type
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            $fqsen = FullyQualifiedClassName::fromStringInContext(
                $anonymous_class_name,
                $this->context
            );

            // Turn that into a union type
            return $fqsen->asUnionType();
        }

        // Things of the form `new $className()`, `new $obj()`, `new (foo())()`, etc.
        if ($kind !== \ast\AST_NAME) {
            return $this->classTypesForNonName($node);
        }

        // Get the name of the class
        $class_name = $node->children['name'];

        // If this is a straight-forward class name, recurse into the
        // class node and get its type
        $is_static_type_string = Type::isStaticTypeString($class_name);
        if (!($is_static_type_string || Type::isSelfTypeString($class_name))) {
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            return self::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node
            );
        }

        // This node references `self` or `static`
        if (!$this->context->isInClassScope()) {
            $this->emitIssue(
                Issue::ContextNotObject,
                $node->lineno,
                $class_name
            );

            return UnionType::empty();
        }

        // Reference to a parent class
        if ($class_name === 'parent') {
            $class = $this->context->getClassInScope(
                $this->code_base
            );

            $parent_type_option = $class->getParentTypeOption();
            if (!$parent_type_option->isDefined()) {
                $this->emitIssue(
                    Issue::ParentlessClass,
                    $node->lineno,
                    (string)$class->getFQSEN()
                );

                return UnionType::empty();
            }

            return $parent_type_option->get()->asUnionType();
        }

        $result = $this->context->getClassFQSEN()->asUnionType();

        if ($is_static_type_string) {
            $result = $result->withType(StaticType::instance(false));
        }
        return $result;
    }

    private function classTypesForNonName(Node $node) : UnionType
    {
        $node_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node
        );
        if ($node_type->isEmpty()) {
            return UnionType::empty();
        }
        $result = UnionType::empty();
        $is_valid = true;
        foreach ($node_type->getTypeSet() as $sub_type) {
            if ($sub_type instanceof LiteralStringType) {
                $value = $sub_type->getValue();
                if (!\preg_match('/\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\]*/', $value)) {
                    $is_valid = false;
                    continue;
                }
                try {
                    $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($value);
                } catch (FQSENException $e) {
                    $this->emitIssue(
                        $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInClasslike : Issue::InvalidFQSENInClasslike,
                        $node->lineno,
                        $e->getFQSEN()
                    );
                    continue;
                }
                if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
                    $is_valid = false;
                    continue;
                }
                $result = $result->withType($fqsen->asType());
            } elseif (\get_class($sub_type) === Type::class || $sub_type instanceof ClosureType) {
                $result = $result->withType($sub_type);
            } elseif ($is_valid) {
                if ($sub_type instanceof StringType) {
                    if ($sub_type instanceof ClassStringType) {
                        $result = $result->withUnionType($sub_type->getClassUnionType());
                    }
                    continue;
                }
                if (!($sub_type instanceof MixedType)) {
                    $is_valid = false;
                }
            }
        }
        if ($result->isEmpty() && !$is_valid) {
            // See https://github.com/phan/phan/issues/1926 - `new $obj()` is valid PHP and documented in the manual.
            $this->emitIssue(
                Issue::TypeExpectedObjectOrClassName,
                $node->lineno,
                $node_type
            );
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
     * The node which we'd like to determine the type of.
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     *
     * @throws FQSENException
     * An exception is thrown if we can find a class name,
     * but it is empty/invalid
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
            $union_type = UnionType::empty();
            foreach ($node->children as $child_node) {
                $union_type = $union_type->withUnionType(
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
        if (!($node instanceof Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return self::unionTypeFromNode(
                $code_base,
                $context,
                $node
            );
        }

        $class_name = (string)$node->children['name'];

        if (\strcasecmp('parent', $class_name) === 0) {
            if (!$context->isInClassScope()) {
                throw new IssueException(
                    Issue::fromType(Issue::ContextNotObject)(
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart(),
                        [$class_name]
                    )
                );
            }

            $class = $context->getClassInScope($code_base);

            if ($class->isTrait()) {
                throw new IssueException(
                    Issue::fromType(Issue::TraitParentReference)(
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart(),
                        [(string)$context->getClassFQSEN() ]
                    )
                );
            }

            if (!$class->hasParentType()) {
                throw new IssueException(
                    Issue::fromType(Issue::ParentlessClass)(
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart(),
                        [ (string)$context->getClassFQSEN() ]
                    )
                );
            }

            $parent_class_fqsen = $class->getParentClassFQSEN();

            if (!$code_base->hasClassWithFQSEN($parent_class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClass)(
                        $context->getFile(),
                        $node->lineno ?? $context->getLineNumberStart(),
                        [ (string)$parent_class_fqsen ],
                        IssueFixSuggester::suggestSimilarClass($code_base, $context, $parent_class_fqsen)
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

        // Check to see if the name is fully qualified
        if ($node->flags & \ast\flags\NAME_NOT_FQ) {
            self::checkValidClassFQSEN($class_name);
            $type = Type::fromStringInContext(
                $class_name,
                $context,
                Type::FROM_NODE
            );
        } elseif ($node->flags & \ast\flags\NAME_RELATIVE) {
            // Relative to current namespace
            if (0 !== \strpos($class_name, '\\')) {
                $class_name = '\\' . $class_name;
            }

            $type = Type::fromFullyQualifiedString(
                $context->getNamespace() . $class_name
            );
        } else {
            // Fully qualified
            if (0 !== \strpos($class_name, '\\')) {
                $class_name = '\\' . $class_name;
            }

            self::checkValidClassFQSEN($class_name);
            $type = Type::fromFullyQualifiedString(
                $class_name
            );
        }

        return $type->asUnionType();
    }

    /**
     * @throws FQSENException if invalid
     */
    private static function checkValidClassFQSEN(string $class_name)
    {
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        if (\preg_match(FullyQualifiedGlobalStructuralElement::VALID_STRUCTURAL_ELEMENT_REGEX, $class_name)) {
            return;
        }
        if ($class_name === '\\') {
            throw new EmptyFQSENException("empty fqsen", $class_name);
        } else {
            throw new InvalidFQSENException("invalid fqsen", $class_name);
        }
    }

    /**
     * @return \Generator|Clazz[]
     */
    public static function classListFromNodeAndContext(CodeBase $code_base, Context $context, Node $node)
    {
        return (new UnionTypeVisitor($code_base, $context, true))->classListFromNode($node);
    }

    /**
     * @phan-return \Generator<Clazz>
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
        )->withStaticResolvedInContext($this->context);

        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($union_type->nonNativeTypes()->getTypeSet() as $class_type) {
            // Get the class FQSEN
            $class_fqsen = FullyQualifiedClassName::fromType($class_type);

            // See if the class exists
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClassReference)(
                        $this->context->getFile(),
                        $node->lineno,
                        [ (string)$class_fqsen ]
                    )
                );
            }

            yield $this->code_base->getClassByFQSEN($class_fqsen);
        }
    }

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param int|string|float|Node $node the node to fetch CallableType instances for.
     * @param bool $log_error whether or not to log errors while searching @phan-unused-param
     * @return array<int,FunctionInterface>
     * TODO: use log_error
     */
    public static function functionLikeListFromNodeAndContext(CodeBase $code_base, Context $context, $node, bool $log_error) : array
    {
        try {
            $function_fqsens = (new UnionTypeVisitor($code_base, $context, true))->functionLikeFQSENListFromNode($node);
        } catch (FQSENException $e) {
            Issue::maybeEmit(
                $code_base,
                $context,
                $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInCallable : Issue::InvalidFQSENInCallable,
                $context->getLineNumberStart(),
                $e->getFQSEN()
            );
            return [];
        } catch (\InvalidArgumentException $_) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::InvalidFQSENInCallable,
                $context->getLineNumberStart(),
                '(unknown)'
            );
            return [];
        }
        $functions = [];
        foreach ($function_fqsens as $fqsen) {
            if ($fqsen instanceof FullyQualifiedMethodName) {
                if (!$code_base->hasMethodWithFQSEN($fqsen)) {
                    // TODO: error PhanArrayMapClosure
                    continue;
                }
                $functions[] = $code_base->getMethodByFQSEN($fqsen);
            } else {
                if (!($fqsen instanceof FullyQualifiedFunctionName)) {
                    throw new TypeError('Expected fqsen to be a FullyQualifiedFunctionName or FullyQualifiedMethodName');
                }
                if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                    // TODO: error PhanArrayMapClosure
                    continue;
                }
                $functions[] = $code_base->getFunctionByFQSEN($fqsen);
            }
        }
        return $functions;
    }

    /**
     * Fetch known classes for a place where a class name was provided as a string or string expression.
     * Warn if this is an invalid class name.
     * @param \ast\Node|string|int|float $node
     * @return array<int,Clazz>
     */
    public static function classListFromClassNameNode(CodeBase $code_base, Context $context, $node) : array
    {
        $results = [];
        $strings = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node)->asStringScalarValues();
        foreach ($strings as $string) {
            try {
                $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($string);
            } catch (FQSENException $e) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInClasslike : Issue::InvalidFQSENInClasslike,
                    $context->getLineNumberStart(),
                    $e->getFQSEN()
                );
                continue;
            } catch (\InvalidArgumentException $_) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::InvalidFQSENInClasslike,
                    $context->getLineNumberStart(),
                    '(unknown)'
                );
                continue;
            }
            if (!$code_base->hasClassWithFQSEN($fqsen)) {
                // TODO: Different issue type?
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::UndeclaredClassReference,
                    $context->getLineNumberStart(),
                    (string)$fqsen
                );
                continue;
            }
            $results[] = $code_base->getClassByFQSEN($fqsen);
        }
        return $results;
    }

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param string|Node $node the node to fetch CallableType instances for.
     * @return array<int,FullyQualifiedFunctionLikeName>
     * @suppress PhanUnreferencedPublicMethod may be used in the future.
     */
    public static function functionLikeFQSENListFromNodeAndContext(CodeBase $code_base, Context $context, $node) : array
    {
        return (new UnionTypeVisitor($code_base, $context, true))->functionLikeFQSENListFromNode($node);
    }

    /**
     * @param string|Node $class_or_expr
     * @param string $method_name
     *
     * @return array<int,FullyQualifiedMethodName>
     * A list of CallableTypes associated with the given node
     */
    private function methodFQSENListFromObjectAndMethodName($class_or_expr, $method_name) : array
    {
        $code_base = $this->code_base;
        $context = $this->context;

        $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $class_or_expr);
        if ($union_type->isEmpty()) {
            return [];
        }
        $object_types = $union_type->objectTypes();
        if ($object_types->isEmpty()) {
            if (!$union_type->canCastToUnionType(StringType::instance(false)->asUnionType())) {
                $this->emitIssue(
                    Issue::TypeInvalidCallableObjectOfMethod,
                    $context->getLineNumberStart(),
                    (string)$union_type,
                    $method_name
                );
            }
            return [];
        }
        $result_types = [];
        $class = null;
        foreach ($object_types->getTypeSet() as $object_type) {
            // TODO: support templates here.
            if ($object_type instanceof ObjectType || $object_type instanceof TemplateType) {
                continue;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($object_type);
            if ($object_type instanceof StaticOrSelfType) {
                if (!$context->isInClassScope()) {
                    $this->emitIssue(
                        Issue::ContextNotObjectInCallable,
                        $context->getLineNumberStart(),
                        (string)$class_fqsen,
                        "$class_fqsen::$method_name"
                    );
                    continue;
                }
                $class_fqsen = $context->getClassFQSEN();
            }
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                $this->emitIssue(
                    Issue::UndeclaredClassInCallable,
                    $context->getLineNumberStart(),
                    (string)$class_fqsen,
                    "$class_fqsen::$method_name"
                );
                continue;
            }
            $class = $code_base->getClassByFQSEN($class_fqsen);
            if (!$class->hasMethodWithName($code_base, $method_name)) {
                // emit error below
                continue;
            }
            $method_fqsen = FullyQualifiedMethodName::make(
                $class_fqsen,
                $method_name
            );
            $result_types[] = $method_fqsen;
        }
        if (\count($result_types) === 0 && $class instanceof Clazz) {
            // TODO: Include suggestion for method name
            $this->emitIssue(
                Issue::UndeclaredMethodInCallable,
                $context->getLineNumberStart(),
                $method_name,
                (string)$union_type
            );
        }
        return $result_types;
    }

    /**
     * @param string $class_name (may also be 'self', 'parent', or 'static')
     * @return ?FullyQualifiedClassName
     * @throws FQSENException
     */
    private function lookupClassOfCallableByName(string $class_name)
    {
        switch (\strtolower($class_name)) {
            case 'self':
            case 'static':
                $context = $this->context;
                if (!$context->isInClassScope()) {
                    $this->emitIssue(
                        Issue::ContextNotObject,
                        $context->getLineNumberStart(),
                        \strtolower($class_name)
                    );
                    return null;
                }
                return $context->getClassFQSEN();
            case 'parent':
                $context = $this->context;
                if (!$context->isInClassScope()) {
                    $this->emitIssue(
                        Issue::ContextNotObject,
                        $context->getLineNumberStart(),
                        \strtolower($class_name)
                    );
                    return null;
                }
                $class = $context->getClassInScope($this->code_base);
                if ($class->isTrait()) {
                    $this->emitIssue(
                        Issue::TraitParentReference,
                        $context->getLineNumberStart(),
                        (string)$class->getFQSEN()
                    );
                    return null;
                }
                if (!$class->hasParentType()) {
                    $this->emitIssue(
                        Issue::ParentlessClass,
                        $context->getLineNumberStart(),
                        (string)$class->getFQSEN()
                    );
                    return null;
                }
                return $class->getParentClassFQSEN();  // may or may not exist.
            default:
                // TODO: Reject invalid/empty class names earlier
                return FullyQualifiedClassName::fromFullyQualifiedString($class_name);
        }
    }

    /**
     * @param string $class_name
     * @param string $method_name
     * @return void
     */
    private function emitNonObjectContextInCallableIssue(string $class_name, string $method_name)
    {
        $this->emitIssue(
            Issue::ContextNotObjectInCallable,
            $this->context->getLineNumberStart(),
            $class_name,
            "$class_name::$method_name"
        );
    }

    /**
     * @param string|Node $class_or_expr
     * @param string|Node $method_name
     *
     * @return array<int,FullyQualifiedMethodName>
     * A list of CallableTypes associated with the given node
     */
    private function methodFQSENListFromParts($class_or_expr, $method_name) : array
    {
        $code_base = $this->code_base;
        $context = $this->context;

        if (!is_string($method_name)) {
            if (!($method_name instanceof Node)) {
                $method_name = UnionTypeVisitor::anyStringLiteralForNode($this->code_base, $this->context, $method_name);
            }
            $method_name = (new ContextNode($code_base, $context, $method_name))->getEquivalentPHPScalarValue();
            if (!is_string($method_name)) {
                $method_name_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $method_name);
                if (!$method_name_type->canCastToUnionType(StringType::instance(false)->asUnionType())) {
                    Issue::maybeEmit(
                        $this->code_base,
                        $this->context,
                        Issue::TypeInvalidCallableMethodName,
                        $method_name->lineno ?? $this->context->getLineNumberStart(),
                        $method_name_type
                    );
                }
                return [];
            }
        }
        try {
            if (is_string($class_or_expr)) {
                if (\in_array(\strtolower($class_or_expr), ['static', 'self', 'parent'], true)) {
                    // Allow 'static' but not '\static'
                    if (!$context->isInClassScope()) {
                        $this->emitNonObjectContextInCallableIssue($class_or_expr, $method_name);
                        return [];
                    }
                    $class_fqsen = $context->getClassFQSEN();
                } else {
                    $class_fqsen = $this->lookupClassOfCallableByName($class_or_expr);
                    if (!$class_fqsen) {
                        return [];
                    }
                }
            } else {
                $class_fqsen = (new ContextNode($code_base, $context, $class_or_expr))->resolveClassNameInContext();
                if (!$class_fqsen) {
                    return $this->methodFQSENListFromObjectAndMethodName($class_or_expr, $method_name);
                }
                if (\in_array(\strtolower($class_fqsen->getName()), ['static', 'self', 'parent'], true)) {
                    if (!$context->isInClassScope()) {
                        $this->emitNonObjectContextInCallableIssue((string)$class_fqsen, $method_name);
                        return [];
                    }
                    $class_fqsen = $context->getClassFQSEN();
                }
            }
        } catch (FQSENException $e) {
            $this->emitIssue(
                $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInClasslike : Issue::InvalidFQSENInClasslike,
                $context->getLineNumberStart(),
                $e->getFQSEN()
            );
            return [];
        }
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            $this->emitIssue(
                Issue::UndeclaredClassInCallable,
                $context->getLineNumberStart(),
                (string)$class_fqsen,
                "$class_fqsen::$method_name"
            );
            return [];
        }
        $class = $code_base->getClassByFQSEN($class_fqsen);
        if (!$class->hasMethodWithName($code_base, $method_name)) {
            $this->emitIssue(
                Issue::UndeclaredStaticMethodInCallable,
                $context->getLineNumberStart(),
                "$class_fqsen::$method_name"
            );
            return [];
        }
        $method = $class->getMethodByName($code_base, $method_name);
        if (!$method->isStatic()) {
            $this->emitIssue(
                Issue::StaticCallToNonStatic,
                $context->getLineNumberStart(),
                (string)$method->getFQSEN(),
                $method->getFileRef()->getFile(),
                (string)$method->getFileRef()->getLineNumberStart()
            );
        }
        return [$method->getFQSEN()];
    }

    /**
     * @see ContextNode::getFunction() for a similar function
     * @return array<int,FullyQualifiedFunctionName>
     */
    private function functionFQSENListFromFunctionName(string $function_name) : array
    {
        // TODO: Catch invalid code such as call_user_func('\\\\x\\\\y')
        try {
            $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
        } catch (FQSENException $e) {
            $this->emitIssue(
                $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInCallable : Issue::InvalidFQSENInCallable,
                $this->context->getLineNumberStart(),
                $function_name
            );
            return [];
        }
        if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
            $this->emitIssue(
                Issue::UndeclaredFunctionInCallable,
                $this->context->getLineNumberStart(),
                $function_name
            );
            return [];
        }
        return [$function_fqsen];
    }

    /**
     * @param string|Node $node
     *
     * @return array<int,FullyQualifiedFunctionLikeName>
     * A list of CallableTypes associated with the given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function functionLikeFQSENListFromNode($node) : array
    {
        $orig_node = $node;
        if ($node instanceof Node) {
            $node = (new ContextNode($this->code_base, $this->context, $node))->getEquivalentPHPValue();
        }
        if (is_string($node)) {
            if (\stripos($node, '::') !== false) {
                list($class_name, $method_name) = \explode('::', $node, 2);
                return $this->methodFQSENListFromParts($class_name, $method_name);
            }
            return $this->functionFQSENListFromFunctionName($node);
        }
        if (\is_array($node)) {
            if (\count($node) !== 2) {
                $this->emitIssue(
                    Issue::TypeInvalidCallableArraySize,
                    $orig_node->lineno ?? $this->context->getLineNumberStart(),
                    \count($node)
                );
                return [];
            }
            $i = 0;
            foreach ($node as $key => $_) {
                if ($key !== $i) {
                    $this->emitIssue(
                        Issue::TypeInvalidCallableArrayKey,
                        $orig_node->lineno ?? $this->context->getLineNumberStart(),
                        $i
                    );
                    return [];
                }
                $i++;
            }
            return $this->methodFQSENListFromParts($node[0], $node[1]);
        }
        if (!($node instanceof Node)) {
            // TODO: Warn?
            return [];
        }

        // Get the types associated with the node
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node
        );

        $closure_types = [];
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof ClosureType && $type->hasKnownFQSEN()) {
                // TODO: Support class instances with __invoke()
                $fqsen = $type->asFQSEN();
                if (!($fqsen instanceof FullyQualifiedFunctionLikeName)) {
                    throw new AssertionError('Expected fqsen of closure to be a FullyQualifiedFunctionLikeName');
                }
                $closure_types[] = $fqsen;
            }
        }
        return $closure_types;
    }

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string|float|int $node
     *
     * @return ?UnionType (Returns null when mixed)
     * TODO: Add an equivalent for Traversable and subclasses, once we have template support for Traversable<Key,T>
     */
    public static function unionTypeOfArrayKeyForNode(CodeBase $code_base, Context $context, $node)
    {
        $arg_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node);
        return self::arrayKeyUnionTypeOfUnionType($arg_type);
    }

    /**
     * @return ?UnionType (Returns null when mixed)
     * TODO: Add an equivalent for Traversable and subclasses, once we have template support for Traversable<Key,T>
     * TODO: Move into UnionType?
     */
    public static function arrayKeyUnionTypeOfUnionType(UnionType $union_type)
    {
        if ($union_type->isEmpty()) {
            return null;
        }
        static $int_type;
        static $string_type;
        static $int_or_string_type;
        if ($int_type === null) {
            $int_type = IntType::instance(false);
            $string_type = StringType::instance(false);
            $int_or_string_type = new UnionType([$int_type, $string_type], true);
        }
        $key_enum_type = GenericArrayType::keyTypeFromUnionTypeKeys($union_type);
        switch ($key_enum_type) {
            case GenericArrayType::KEY_INT:
                return $int_type->asUnionType();
            case GenericArrayType::KEY_STRING:
                return $string_type->asUnionType();
            default:
                foreach ($union_type->getTypeSet() as $type) {
                    // The exact class Type is potentially invalid (includes objects) but not the subclass NativeType.
                    // The subclass IterableType of Native type is invalid, but ArrayType is a valid subclass of IterableType.
                    // And we just ignore scalars.
                    // And mixed could be a Traversable.
                    // So, don't infer anything if the union type contains any instances of the four classes.
                    // TODO: Check the expanded union type instead of anything with a class of exactly Type, searching for Traversable?
                    if (\in_array(\get_class($type), [Type::class, IterableType::class, TemplateType::class, MixedType::class])) {
                        return null;
                    }
                }
                return $int_or_string_type;
        }
    }

    /**
     * @param Node|array|string|bool|float|int|null $node
     * @return ?string - One of the values for the LiteralStringType, or null
     */
    public static function anyStringLiteralForNode(
        CodeBase $code_base,
        Context $context,
        $node
    ) {
        if (!($node instanceof Node)) {
            return is_string($node) ? $node : null;
        }
        $node_type = self::unionTypeFromNode(
            $code_base,
            $context,
            $node
        );
        foreach ($node_type->getTypeSet() as $type) {
            if ($type instanceof LiteralStringType) {
                // Arbitrarily return only the first value.
                // TODO: Rewrite code using this to work with lists of possible values?
                return $type->getValue();
            }
        }
        return null;
    }

    /**
     * @param Node $node
     * @return void
     */
    private function analyzeNegativeStringOffsetCompatibility(Node $node, UnionType $dim_type)
    {
        $dim_value = $dim_type->asSingleScalarValueOrNull();
        if (!\is_int($dim_value) || $dim_value >= 0) {
            return;
        }
        $this->emitIssue(
            Issue::CompatibleNegativeStringOffset,
            $node->children['dim']->lineno ?? $node->lineno
        );
    }
}
