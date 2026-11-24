<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\flags;
use ast\Node;
use Closure;
use Exception;
use Phan\AST\AnalysisVisitor;
use Phan\AST\ASTReverter;
use Phan\AST\PipeExpression;
use Phan\AST\ContextNode;
use Phan\AST\PhanAnnotationAdder;
use Phan\AST\ScopeImpactCheckingVisitor;
use Phan\AST\PartialApplication;
use Phan\AST\ParentPropertyHookCall;
use Phan\AST\UnionTypeVisitor;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\EmptyFQSENException;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\NullsafeShortCircuitException;
use Phan\Exception\RecursionDepthException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalVariable;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralFloatType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NeverType;
use Phan\Language\Type\NonEmptyMixedType;
use Phan\Language\Type\NonNullMixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use WeakMap;

use function implode;
use function sprintf;
use function strtolower;

/**
 * PostOrderAnalysisVisitor is where we do the post-order part of the analysis
 * during Phan's analysis phase.
 *
 * This is called in post-order by BlockAnalysisVisitor
 * (i.e. this is called after visiting all children of the current node)
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 */
class PostOrderAnalysisVisitor extends AnalysisVisitor
{
    /**
     * @var list<Node> a list of parent nodes of the currently analyzed node,
     * within the current global or function-like scope
     */
    private $parent_node_list;

    /** @var array<string,bool> tracks which methods were checked for static property modifications on-demand */
    private static $checked_methods_for_static_property_modifications = [];

    /**
     * @var ?WeakMap<Node,list<array{0:Node,1:bool}>> the AST_STATIC_PROP nodes that are assigned in the body of a method,
     * each with whether the assignment may make the property null, by the node of the method
     */
    private static $assigned_static_property_nodes = null;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param list<Node> $parent_node_list
     * The parent node list of the node being analyzed
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        array $parent_node_list
    ) {
        parent::__construct($code_base, $context);
        $this->parent_node_list = $parent_node_list;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node): Context
    {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * Check if an expression has potential to narrow types when used in conditionals
     * (e.g., instanceof, is_string(), etc.)
     *
     * @param Node $node The expression node to check
     * @return bool True if the expression can narrow types
     */
    private static function exprHasTypeNarrowingPotential(Node $node): bool
    {
        switch ($node->kind) {
            case ast\AST_INSTANCEOF:
            case ast\AST_CALL:
            case ast\AST_ISSET:
            case ast\AST_EMPTY:
                // These nodes can narrow types
                return true;
            case ast\AST_BINARY_OP:
                // Binary operators like && and || may contain type-narrowing expressions
                $left = $node->children['left'];
                $right = $node->children['right'];
                return ($left instanceof Node && self::exprHasTypeNarrowingPotential($left)) ||
                       ($right instanceof Node && self::exprHasTypeNarrowingPotential($right));
            case ast\AST_UNARY_OP:
                // Unary ! operator may wrap a type-narrowing expression
                $expr = $node->children['expr'];
                return $expr instanceof Node && self::exprHasTypeNarrowingPotential($expr);
            default:
                return false;
        }
    }

    /**
     * Check if an expression references a specific variable (for detecting self-referential assignments)
     *
     * @param Node $node The expression node to check
     * @param string $var_name The variable name to look for
     * @return bool True if the expression references the variable
     */
    public static function exprReferencesVariable(Node $node, string $var_name): bool
    {
        if ($node->kind === ast\AST_VAR) {
            $name = $node->children['name'];
            return \is_string($name) && $name === $var_name;
        }

        // Recursively check children
        foreach ($node->children as $child) {
            if ($child instanceof Node && self::exprReferencesVariable($child, $var_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node, bool $redundant_condition_detection = false): Context
    {
        $var_node = $node->children['var'];

        // Get the type of the right side of the
        // assignment
        $right_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            true
        );

        if (!($var_node instanceof Node)) {
            // Give up, this should be impossible except with the fallback
            $this->emitIssue(
                Issue::InvalidNode,
                $node->lineno,
                "Expected left side of assignment to be a variable"
            );
            return $this->context;
        }
        if (AssignmentVisitor::checkWriteToNullsafeChain($this->code_base, $this->context, $var_node)) {
            return $this->context;
        }

        if ($redundant_condition_detection && $right_type->hasRealTypeSet() &&
            !\in_array($var_node->kind, [ast\AST_VAR, ast\AST_ARRAY], true)) {
            (new ConditionVisitor($this->code_base, $this->context))->checkRedundantOrImpossibleTruthyCondition($var_node, $this->context, $right_type->getRealUnionType(), false);
        }

        if ($right_type->isVoidType()) {
            $this->emitIssue(
                Issue::TypeVoidAssignment,
                $node->lineno
            );
        }

        // Handle the assignment based on the type of the
        // right side of the equation and the kind of item
        // on the left.
        // (AssignmentVisitor converts possibly undefined types to nullable)
        //
        // TODO: For assignment by reference, also check Clazz->isImmutableAtRuntime for properties
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type
        ))->__invoke($var_node);

        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node
            && $expr_node->kind === ast\AST_CLOSURE
        ) {
            $method = (new ContextNode(
                $this->code_base,
                $this->context->withLineNumberStart(
                    $expr_node->lineno
                ),
                $expr_node
            ))->getClosure();

            $method->addReference($this->context);
        }

        // Fix for issue #4854: Track conditional expressions assigned to boolean variables
        // so that type narrowing can be re-applied when the variable is tested in conditionals
        // Only store expressions that can actually narrow types (instanceof, is_string(), etc.)
        // and avoid self-referential expressions like $x = $x || something (prevents infinite recursion)
        if ($var_node->kind === ast\AST_VAR &&
            $expr_node instanceof Node &&
            self::exprHasTypeNarrowingPotential($expr_node) &&
            $right_type->isExclusivelyBoolTypes()) {
            // Get the variable name
            $var_name = $var_node->children['name'];
            if (\is_string($var_name) && !self::exprReferencesVariable($expr_node, $var_name)) {
                // Get the variable from the context and attach the conditional expression to it
                '@phan-var Context $context';  // Help fallback parser with type inference
                $variable = $context->getScope()->getVariableByNameOrNull($var_name);
                if ($variable) {
                    // Store the RHS expression node so ConditionVisitor can re-apply type narrowing
                    // when this variable is tested in an if/while condition
                    $variable->phan_condition_expr = $expr_node;
                }
            }
        }

        return $context;
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssignRef(Node $node): Context
    {
        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node && AssignmentVisitor::isShortCircuitedChain($expr_node, false)) {
            // `$x = &$c?->p` is a compile error ("Cannot take reference of a nullsafe chain"), for a bare `$c?->m()` as well.
            // Still analyze it as an assignment so that the target variable is declared.
            $this->emitIssue(
                Issue::InvalidReferenceToNullsafeChain,
                $expr_node->lineno,
                ASTReverter::toShortString($expr_node)
            );
        }
        if ($expr_node instanceof Node) {
            // `$b = &$a` defines `$a` (as null), `$b = &$a['k']` creates `$a` and the offset 'k'.
            // (An `$a` that is undefined on every path is still reported: it is probably a typo)
            $this->createReferenceTarget($expr_node, true);
        }
        return $this->visitAssign($node);
    }

    /**
     * Creates what PHP creates, without any warning, when a reference is taken to $node,
     * before the type of $node is computed:
     *
     * - `&$var` defines `$var` (as null) if it is undefined, or possibly undefined.
     *   With $report_undefined, a `$var` that is undefined on every path is reported as undeclared first:
     *   that is used for `=&`, `[&$var]` and `return $var;`, where a new variable is probably a typo,
     *   and not for arguments, where it is the usual way to receive a result (`preg_match($re, $s, $matches)`).
     * - `&$var[$k]` (also `&$var[$k][$j]`, etc.) defines `$var` the same way, and creates the offset `$k` (as null)
     *   if it does not exist. (PHP creates the array if `$var` is null.)
     *
     * This is used for `$b = &$a`, `[&$a]`, the arguments of parameters passed by reference,
     * and the expressions returned by functions returning a reference.
     */
    private function createReferenceTarget(Node $node, bool $report_undefined = false, bool $is_argument = false): void
    {
        $kind = $node->kind;
        if ($kind === ast\AST_VAR) {
            $this->createVariableForReference($node, null, $report_undefined);
            return;
        }
        if ($kind !== ast\AST_DIM) {
            return;
        }
        // Don't report the offset as invalid or the array as null.
        // (This is already done when parsing, except for arguments and returned expressions)
        PhanAnnotationAdder::markReferenceTarget($node);
        $var_node = self::getBaseVariableNodeOfDim($node);
        if (!$var_node) {
            // E.g. `&$this->prop['k']`: the offset is not added to the type of the property.
            // The expression that the dimensions start at is still checked: php creates the offset, but not an object (`&$nullable->prop['k']`).
            $root = $node;
            while ($root instanceof Node && $root->kind === ast\AST_DIM) {
                $root = $root->children['expr'];
            }
            if ($root instanceof Node && $root->kind === ast\AST_PROP && Config::get_strict_object_checking()) {
                $property_name = $root->children['prop'];
                if (\is_string($property_name)) {
                    // (The same check as for `$nullable->prop['k'] = $value;`. The flags that suppress it for reads are set on the whole chain.)
                    ContextNode::checkPossiblyUndeclaredInstanceProperty($this->code_base, $this->context, $root, $property_name);
                }
            }
            return;
        }
        // An undefined `$var` becomes an array.
        $this->createVariableForReference($var_node, ArrayShapeType::empty(false)->asPHPDocUnionType(), $report_undefined);
        $null_type = NullType::instance(false)->asPHPDocUnionType();
        if ($is_argument && self::hasAppendInDimChain($node)) {
            // `f($out[])`, `f($out[]['m'])`: the element is appended once, when the type of the parameter is assigned.
            return;
        }
        if ($node->children['dim'] === null) {
            // `$x = &$var[];` appends null
            $element_type = $null_type;
        } else {
            try {
                $element_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node, false);
            } catch (IssueException) {
                // Reported when the reference is analyzed
                return;
            }
            if ($element_type->isNull()) {
                // The offset is null until it is assigned through the reference, which Phan does not track.
                $element_type = $null_type;
            }
        }
        // Analyze this as `$var[$k] = $var[$k]`, which adds the offset if it was missing (the type of a missing offset is null).
        self::assignTypeToReferencedDim($this->code_base, $this->context, $node, $element_type);
    }

    /**
     * @param Node $node a node of kind ast\AST_DIM
     * @return bool whether one of the dimensions of `$var[...][...]` is an append (`[]`)
     */
    private static function hasAppendInDimChain(Node $node): bool
    {
        while ($node instanceof Node && $node->kind === ast\AST_DIM) {
            if ($node->children['dim'] === null) {
                return true;
            }
            $node = $node->children['expr'];
        }
        return false;
    }

    /**
     * Defines the variable $node for a reference to it, if it is undefined (as $type_if_undefined, by default null),
     * or if it is possibly undefined (as null).
     *
     * @param Node $node a node of kind ast\AST_VAR
     */
    private function createVariableForReference(Node $node, ?UnionType $type_if_undefined = null, bool $report_undefined = false): void
    {
        $variable_name = $node->children['name'];
        if (!\is_string($variable_name) || $variable_name === 'this') {
            return;
        }
        $context = $this->context;
        $scope = $context->getScope();
        if ($scope->hasVariableWithName($variable_name)) {
            $variable = $scope->getVariableByName($variable_name);
            $union_type = $variable->getUnionType();
            if (!$union_type->isPossiblyUndefined()) {
                return;
            }
            $variable = clone $variable;
            $variable->setUnionType($union_type->convertUndefinedToNullable());
        } else {
            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $context->isInGlobalScope())) {
                return;
            }
            if ($report_undefined) {
                // PHP creates the variable without a warning, but a variable that is undefined on every path
                // is more likely a typo than intended: `$ref = &$itmes;`
                $this->checkForUndeclaredVariable($node);
            }
            // The type is only null until the variable is assigned through any of the references to it, which Phan does not track.
            $variable = new Variable(
                $context->withLineNumberStart($node->lineno),
                $variable_name,
                $type_if_undefined ?? NullType::instance(false)->asPHPDocUnionType(),
                0
            );
        }
        // (So that `$b[0] = &$b;` does not warn about adding an offset to null)
        $variable->enablePhanFlagBits(\Phan\Language\Element\Flags::HAS_REFERENCE);
        $context->addScopeVariable($variable);
        // The type of the variable may have been computed and cached for the node already.
        $context->clearCachedUnionTypes();
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     * @override
     */
    public function visitAssignOp(Node $node): Context
    {
        $var_node = $node->children['var'];
        if ($var_node instanceof Node && AssignmentVisitor::checkWriteToNullsafeChain($this->code_base, $this->context, $var_node)) {
            return $this->context;
        }
        return (new AssignOperatorAnalysisVisitor($this->code_base, $this->context))->__invoke($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUnset(Node $node): Context
    {
        $context = $this->context;
        // Get the type of the thing being unset
        $var_node = $node->children['var'];
        if (!($var_node instanceof Node)) {
            return $context;
        }
        if (AssignmentVisitor::checkWriteToNullsafeChain($this->code_base, $context, $var_node)) {
            return $context;
        }

        $kind = $var_node->kind;
        if ($kind === ast\AST_VAR) {
            $var_name = $var_node->children['name'];
            if (\is_string($var_name)) {
                // TODO: Make this work in branches
                $context->unsetScopeVariable($var_name);
            }
            // I think DollarDollarPlugin already warns, so don't warn here.
        } elseif ($kind === ast\AST_DIM) {
            $this->analyzeUnsetDim($var_node);
        } elseif ($kind === ast\AST_PROP) {
            return $this->analyzeUnsetProp($var_node);
        } elseif ($kind === ast\AST_STATIC_PROP) {
            // unset(self::$prop) (or any other class) always throws Error: "Attempt to unset static property"
            $this->emitIssue(
                Issue::TypeUnsetStaticProperty,
                $var_node->lineno,
                ASTReverter::toShortString($var_node)
            );
        }
        return $context;
    }

    /**
     * Emit PhanInvalidWriteToTemporaryExpression if the dimension or property being unset is fetched from a temporary expression.
     *
     * PHP compiles the array of `unset($x['k'])` and the object of `unset($x->p)` in the same write context, recursively,
     * so `unset(self::C['k'])`, `unset((new C)->p)` and `unset((new C)->p['k'])` are compile errors
     * ("Cannot use temporary expression in write context"), while a variable, a static property or the result of a call
     * can be the base of a write (the write to a call result is a no-op).
     * Nullsafe chains were already rejected by AssignmentVisitor::checkWriteToNullsafeChain().
     *
     * @param Node $node a node of kind AST_DIM or AST_PROP in unset()
     * @return bool true if an issue was emitted
     */
    private function checkUnsetOfTemporaryExpression(Node $node): bool
    {
        $base_node = $node->children['expr'];
        $parent_node = $node;
        while ($base_node instanceof Node) {
            switch ($base_node->kind) {
                case ast\AST_DIM:
                case ast\AST_PROP:
                    $parent_node = $base_node;
                    $base_node = $base_node->children['expr'];
                    continue 2;
                case ast\AST_CONST:
                case ast\AST_CLASS_CONST:
                    // PHP 8.6+: a property of an object referenced by a constant can be written to or unset (`unset(OBJ->prop)`, `unset(A::OBJ->list['key'])`).
                    // `unset(OBJ['key'])` remains an error.
                    if ($parent_node->kind === ast\AST_PROP && Config::get_closest_minimum_target_php_version_id() >= 80600) {
                        return false;
                    }
                    break;
                case ast\AST_VAR:
                case ast\AST_STATIC_PROP:
                case ast\AST_CALL:
                case ast\AST_METHOD_CALL:
                case ast\AST_STATIC_CALL:
                    return false;
            }
            break;
        }
        $this->emitIssue(
            Issue::InvalidWriteToTemporaryExpression,
            $node->lineno,
            ASTReverter::toShortString($node),
            $base_node instanceof Node
                ? UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $base_node)
                : Type::fromObject($base_node)
        );
        return true;
    }

    /**
     * @param Node $node a node of type AST_DIM in unset()
     * @see UnionTypeVisitor::resolveArrayShapeElementTypes()
     * @see UnionTypeVisitor::visitDim()
     */
    private function analyzeUnsetDim(Node $node): void
    {
        if ($this->checkUnsetOfTemporaryExpression($node)) {
            return;
        }
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            // unreachable: already reported above
            return;
        }

        // For now, just handle a single level of dimensions for unset($x['field']);
        if ($expr_node->kind === ast\AST_VAR) {
            $var_name = $expr_node->children['name'];
            if (!\is_string($var_name)) {
                return;
            }

            $context = $this->context;
            $scope = $context->getScope();
            if (!$scope->hasVariableWithName($var_name)) {
                // TODO: Warn about potentially pointless unset in function scopes?
                return;
            }
            // TODO: Could warn about invalid offsets for isset
            $variable = $scope->getVariableByName($var_name);
            $union_type = $variable->getUnionType();
            if ($union_type->isEmpty()) {
                return;
            }
            $resolved_union_type = $union_type->withStaticResolvedInContext($context);
            if (!$resolved_union_type->hasArrayLike($this->code_base) && !$resolved_union_type->hasMixedOrNonEmptyMixedType()) {
                $this->emitIssue(
                    Issue::TypeArrayUnsetSuspicious,
                    $node->lineno,
                    ASTReverter::toShortString($expr_node),
                    (string)$resolved_union_type
                );
            }
            $dim_node = $node->children['dim'];
            $dim_value = $dim_node instanceof Node ? (new ContextNode($this->code_base, $context, $dim_node))->getEquivalentPHPScalarValue() : $dim_node;
            // unset($x[$i]) should convert a list<T> or non-empty-list<T> to an array<Y>
            $union_type = $union_type->withAssociativeArrays(true)->asMappedUnionType(static function (Type $type): Type {
                if ($type instanceof NonEmptyMixedType) {
                    // convert non-empty-mixed to non-null-mixed because `unset($x[$i])` could have removed the last element of an array,
                    // but that would still not be null.
                    return $type->isNullableLabeled() ? MixedType::instance(true) : NonNullMixedType::instance(false);
                }
                return $type;
            });
            $variable = clone $variable;
            $context->addScopeVariable($variable);
            $variable->setUnionType($union_type);
            /*
            if (!is_scalar($dim_value) || (!is_numeric($dim_value) || $dim_value >= 0)) {
                foreach ($union_type->getTypeSet() as $type) {
                    if ($type instanceof ListType) {
                        $union_type = $union_type->withoutType($type)->withType(
                            GenericArrayType::fromElementType($type->genericArrayElementType(), false, $type->getKeyType())
                        );
                        $variable = clone $variable;
                        $context->addScopeVariable($variable);
                        $variable->setUnionType($union_type);
                    }
                }
            }
             */

            if (!$union_type->hasTopLevelArrayShapeTypeInstances()) {
                return;
            }
            // TODO: detect and warn about null
            if (!\is_scalar($dim_value)) {
                return;
            }
            $variable->setUnionType($union_type->withoutArrayShapeField($dim_value));
        }
    }

    /**
     * @param Node $node a node of type AST_PROP in unset()
     * @see UnionTypeVisitor::resolveArrayShapeElementTypes()
     * @see UnionTypeVisitor::visitDim()
     */
    private function analyzeUnsetProp(Node $node): Context
    {
        $context = $this->context;
        if ($this->checkUnsetOfTemporaryExpression($node)) {
            return $context;
        }
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            // unreachable: already reported above
            return $context;
        }
        $prop_name = $node->children['prop'];
        if (!\is_string($prop_name)) {
            $prop_name = (new ContextNode($this->code_base, $this->context, $prop_name))->getEquivalentPHPScalarValue();
            if (!\is_string($prop_name)) {
                return $context;
            }
        }
        if ($expr_node->kind === \ast\AST_VAR && $expr_node->children['name'] === 'this' && $context === $this->context) {
            $context = $context->withThisPropertySetToTypeByName($prop_name, NullType::instance(false)->asPHPDocUnionType()->withIsDefinitelyUndefined());
        }

        $union_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr_node)->withStaticResolvedInContext($this->context);
        $type_fqsens = $union_type->objectTypesWithKnownFQSENs();
        foreach ($type_fqsens->getUniqueFlattenedTypeSet() as $type) {
            $fqsen = FullyQualifiedClassName::fromType($type);
            if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }
            $class = $this->code_base->getClassByFQSEN($fqsen);
            if ($class->isPropertyImmutableFromContext($this->code_base, $this->context, $prop_name)) {
                if ($class->hasPropertyWithName($this->code_base, $prop_name)) {
                    // NOTE: We deliberately emit this issue whether or not the access is to a public or private variable,
                    // because unsetting a private variable at runtime is also a (failed) attempt to unset a declared property.
                    $prop_context = $class->getPropertyByName($this->code_base, $prop_name)->getFileRef();
                } else {
                    // Properties that are undeclared on readonly classes can't be modified
                    $prop_context = $class->getContext();
                }
                $this->emitIssue(
                    Issue::TypeModifyImmutableObjectProperty,
                    $node->lineno,
                    $class->getClasslikeType(),
                    (string)$type,
                    $prop_name,
                    $prop_context->getFile(),
                    $prop_context->getLineNumberStart()
                );
                continue;
            }
            if ($class->hasPropertyWithName($this->code_base, $prop_name)) {
                // NOTE: We deliberately emit this issue whether or not the access is to a public or private variable,
                // because unsetting a private variable at runtime is also a (failed) attempt to unset a declared property.
                $prop = $class->getPropertyByName($this->code_base, $prop_name);
                if ($prop->isFromPHPDoc()) {
                    // TODO: Warn if __get is defined but __unset isn't defined?
                    continue;
                }
                if ($prop->isDynamicProperty()) {
                    continue;
                }
                $this->emitIssue(
                    Issue::TypeObjectUnsetDeclaredProperty,
                    $node->lineno,
                    (string)$type,
                    $prop_name,
                    $prop->getFileRef()->getFile(),
                    $prop->getFileRef()->getLineNumberStart()
                );
            }
        }
        return $context;
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIfElem(Node $node): Context
    {
        return $this->context;
    }

    /**
     * @param Node $node @phan-unused-param
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitWhile(Node $node): Context
    {
        return $this->context;
    }

    /**
     * @param Node $node @phan-unused-param
     * A node of kind ast\AST_SWITCH to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @suppress PhanUndeclaredProperty
     */
    public function visitSwitch(Node $node): Context
    {
        if (isset($node->phan_loop_contexts)) {
            // Combine contexts from continue/break statements within this do-while loop
            $context = (new ContextMergeVisitor($this->context, \array_merge([$this->context], $node->phan_loop_contexts), $this->code_base))->combineChildContextList();
            unset($node->phan_loop_contexts);
            return $context;
        }
        return $this->context;
    }

    /**
     * @param Node $node @phan-unused-param
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitchCase(Node $node): Context
    {
        return $this->context;
    }

    /**
     * @param Node $node @phan-unused-param
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node): Context
    {
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
    public function visitEncapsList(Node $node): Context
    {
        $this->analyzeNoOp($node, Issue::NoopEncapsulatedStringLiteral);

        foreach ($node->children as $child_node) {
            // Confirm that variables exists
            if (!($child_node instanceof Node)) {
                continue;
            }
            $this->checkExpressionInDynamicString($child_node);
        }

        return $this->context;
    }

    private const DEPRECATED_ENCAPS_VAR_FLAGS = ast\flags\ENCAPS_VAR_DOLLAR_CURLY_VAR_VAR | ast\flags\ENCAPS_VAR_DOLLAR_CURLY;

    private function checkExpressionInDynamicString(Node $expr_node): void
    {
        if ($expr_node->flags & self::DEPRECATED_ENCAPS_VAR_FLAGS) {
            // Only emit this warning if targeting PHP 8.2+ (where this was deprecated)
            if (Config::get_closest_target_php_version_id() >= 80200) {
                $deprecation = null;
                if (\in_array($expr_node->kind, [ast\AST_VAR, ast\AST_DIM], true) && ($expr_node->flags & ast\flags\ENCAPS_VAR_DOLLAR_CURLY)) {
                    $deprecation = 'Using ${var} in strings is deprecated, use {$var} instead';
                } elseif ($expr_node->kind === ast\AST_VAR && ($expr_node->flags & ast\flags\ENCAPS_VAR_DOLLAR_CURLY_VAR_VAR)) {
                    $deprecation = 'Using ${expr} in strings is deprecated, use {${expr}} instead';
                }
                if ($deprecation) {
                    $this->emitIssue(
                        Issue::DeprecatedEncapsVar,
                        $expr_node->lineno,
                        ASTReverter::toShortString($expr_node),
                        $deprecation
                    );
                }
            }
        }
        $code_base = $this->code_base;
        $context = $this->context;
        $type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $expr_node,
            true
        );

        if (!$type->hasPrintableScalar()) {
            if ($type->isArray()) {
                $this->emitIssue(
                    Issue::TypeConversionFromArray,
                    $expr_node->lineno,
                    'string'
                );
                return;
            }
            // Check for __toString(), stringable variables/expressions in encapsulated strings work whether or not strict_types is set
            try {
                foreach ($type->withStaticResolvedInContext($context)->asClassList($code_base, $context) as $clazz) {
                    if ($clazz->hasMethodWithName($code_base, "__toString", true)) {
                        return;
                    }
                }
            } catch (CodeBaseException | RecursionDepthException) {
                // Swallow "Cannot find class" or recursion exceptions, go on to emit issue
            }
            $this->emitIssue(
                Issue::TypeSuspiciousStringExpression,
                $expr_node->lineno,
                (string)$type,
                ASTReverter::toShortString($expr_node)
            );
        }
    }

    /**
     * Check if a given variable is undeclared.
     * @param Node $node Node with kind AST_VAR
     */
    /**
     * @param bool $was_undeclared true if the variable was undeclared when $node was evaluated,
     * although it may be declared in the current scope (by a later argument of the same call)
     */
    private function checkForUndeclaredVariable(Node $node, bool $was_undeclared = false): void
    {
        $variable_name = $node->children['name'];

        // Ignore $$var type things
        if (!\is_string($variable_name)) {
            return;
        }

        // Don't worry about non-existent undeclared variables
        // in the global scope if configured to do so
        if (Config::getValue('ignore_undeclared_variables_in_global_scope')
            && $this->context->isInGlobalScope()
        ) {
            return;
        }

        if ($was_undeclared || (!$this->context->getScope()->hasVariableWithName($variable_name)
            && !Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope()))
        ) {
            $this->emitIssueWithSuggestion(
                Variable::chooseIssueForUndeclaredVariable($this->context, $variable_name),
                $node->lineno,
                [$variable_name],
                // (The variable itself is in the scope by now if it was declared after $node was evaluated)
                $was_undeclared ? null : IssueFixSuggester::suggestVariableTypoFix($this->code_base, $this->context, $variable_name)
            );
        }
    }

    /**
     * Returns true if this is the scope of a function, method or closure returning a reference (`function &f()`).
     * `return $var;` takes a reference to `$var` there, which is checked in analyzeReturnsReference().
     */
    private function isInFunctionReturningReference(): bool
    {
        $context = $this->context;
        if (!$context->isInFunctionLikeScope()) {
            return false;
        }
        return $context->getFunctionLikeInScope($this->code_base)->returnsRef();
    }

    /**
     * The parents of a `$var[...]` node for which visitDim() does not check whether `$var` is possibly undeclared.
     */
    private const SKIP_DIM_VAR_CHECK_TYPES = [
        ast\AST_ARG_LIST   => true,  // may be a reference, which creates the variable: checked in analyzeCallToFunctionLike()
        ast\AST_NAMED_ARG  => true,  // same, and the parameter cannot be found by position
        ast\AST_ASSIGN_REF => true,  // `$x = &$var[0]` creates the variable
    ];

    /**
     * Emits PhanPossiblyUndeclaredVariable (or the global variant) for `$var` when `$var[...]` is read
     * and `$var` is possibly undeclared (see getIssueTypeForPossiblyUndeclaredVariableOfDimRead()).
     *
     * @param Node $node a node of kind ast\AST_DIM
     */
    private function checkForPossiblyUndeclaredVariableOfDimRead(Node $node): void
    {
        $issue_type = $this->getIssueTypeForPossiblyUndeclaredVariableOfDimRead($node);
        if ($issue_type !== null) {
            $var_node = self::getBaseVariableNodeOfDim($node);
            if ($var_node) {
                $this->emitIssue($issue_type, $var_node->lineno, $var_node->children['name']);
            }
        }
    }

    /**
     * Returns the `$var` node of `$var[...]`, `$var[...][...]`, etc., or null if the chain does not start with a variable.
     *
     * @param Node $node a node of kind ast\AST_DIM
     */
    private static function getBaseVariableNodeOfDim(Node $node): ?Node
    {
        do {
            $node = $node->children['expr'];
            if (!($node instanceof Node)) {
                return null;
            }
        } while ($node->kind === ast\AST_DIM);
        return $node->kind === ast\AST_VAR ? $node : null;
    }

    /**
     * Emits the issue that visitDim() recorded for a `$var[...]` argument of a call (with the scope at that point),
     * now that the parameter is known not to be by reference.
     *
     * @param Node $node a node of kind ast\AST_DIM
     */
    private function emitPossiblyUndeclaredVariableOfDimArgument(Node $node): void
    {
        // @phan-suppress-next-line PhanUndeclaredProperty this is added by visitDim()
        $issue_type = $node->possibly_undeclared_variable_issue ?? null;
        if (\is_string($issue_type)) {
            $var_node = self::getBaseVariableNodeOfDim($node);
            if ($var_node) {
                $this->emitIssue($issue_type, $var_node->lineno, $var_node->children['name']);
            }
        }
    }

    /**
     * Returns the issue type to emit (PhanPossiblyUndeclaredVariable or the global variant) when `$var[...]` is read
     * and `$var` is possibly undeclared in the current scope, or null when nothing should be emitted.
     *
     * UnionTypeVisitor::visitVar() emits it only when it is allowed to emit issues, and visitDim() computes the type
     * of `$var[...]` without that permission. The result is then cached for the parent expression as well
     * (Context::setCachedUnionTypeOfNode()), so the echo, assignment, argument, etc. reading `$var[...]` never
     * emitted it either, unlike the same read of `$var` or `$var->prop` (#5621).
     * An undeclared variable is still reported by the parent expression: no type is cached when computing it throws.
     *
     * @param Node $node a node of kind ast\AST_DIM
     */
    private function getIssueTypeForPossiblyUndeclaredVariableOfDimRead(Node $node): ?string
    {
        if ($node->flags & PhanAnnotationAdder::FLAG_IGNORE_NULLABLE_AND_UNDEF) {
            return null;
        }
        $var_node = self::getBaseVariableNodeOfDim($node);
        if (!$var_node || ($var_node->flags & PhanAnnotationAdder::FLAG_IGNORE_UNDEF)) {
            return null;
        }
        $variable_name = $var_node->children['name'];
        if (!\is_string($variable_name)) {
            return null;
        }
        $scope = $this->context->getScope();
        if (!$scope->hasVariableWithName($variable_name)
            || !$scope->getVariableByName($variable_name)->getUnionType()->isPossiblyUndefined()
            || ConditionVisitor::isInRecursiveConditionalAnalysis()
        ) {
            return null;
        }
        if ($this->context->isInGlobalScope()) {
            return Config::getValue('ignore_undeclared_variables_in_global_scope') ? null : Issue::PossiblyUndeclaredGlobalVariable;
        }
        return Issue::PossiblyUndeclaredVariable;
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDoWhile(Node $node): Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `ast\AST_GLOBAL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGlobal(Node $node): Context
    {
        $variable_name = $node->children['var']->children['name'] ?? null;
        if (!\is_string($variable_name)) {
            // Shouldn't happen?
            return $this->context;
        }
        $variable = new Variable(
            $this->context->withLineNumberStart($node->lineno),
            $variable_name,
            UnionType::empty(),
            0
        );
        $optional_global_variable_type = Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name);
        if ($optional_global_variable_type) {
            $variable->setUnionType($optional_global_variable_type);
            $scope_global_variable = $variable;
        } else {
            $scope = $this->context->getScope();
            if (!$scope->hasGlobalVariableWithName($variable_name)) {
                $actual_global_variable = clone $variable;
                $this->context->addGlobalScopeVariable($actual_global_variable);
            } else {
                // TODO: Support @global?
                $actual_global_variable = $scope->getGlobalVariableByName($variable_name);
            }
            $scope_global_variable = $actual_global_variable instanceof GlobalVariable ? (clone $actual_global_variable) : new GlobalVariable($actual_global_variable);
            // Importing an undefined global by reference will make an undefined value a reference to null.
            $scope_global_variable->setUnionType($actual_global_variable->getUnionType()->eraseRealTypeSetRecursively()->withIsPossiblyUndefined(false));
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($scope_global_variable);

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
    public function visitStatic(Node $node): Context
    {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            $this->code_base,
            false
        );

        // Check for duplicate static variable declarations (PHP 8.3+ fatal error)
        if ($this->context->isInFunctionLikeScope()) {
            $var_name = $variable->getName();
            $scope = $this->context->getScope();

            // Check if this static variable was already declared in this function
            if ($scope->hasVariableWithName($var_name)) {
                $existing_var = $scope->getVariableByName($var_name);

                // Only warn if the existing variable is also a static variable
                // (the IS_CONSTANT_DEFINITION flag is set on static variables)
                if ($existing_var->getPhanFlagsHasState(\Phan\Language\Element\Flags::IS_CONSTANT_DEFINITION)) {
                    $method = $this->context->getFunctionLikeInScope($this->code_base);
                    $this->emitIssue(
                        Issue::DuplicateStaticVariable,
                        $node->lineno,
                        $var_name,
                        $method->getRepresentationForIssue(),
                        $existing_var->getFileRef()->getFile(),
                        $existing_var->getFileRef()->getLineNumberStart()
                    );
                }
            }
        }

        // If the element has a default, set its type
        // on the variable
        if (isset($node->children['default'])) {
            $default_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node->children['default']
            );
        } else {
            $default_type = NullType::instance(false)->asRealUnionType();
        }

        // NOTE: Phan can't be sure that the type the static type starts with is the same as what it has later. Avoid false positive PhanRedundantCondition.
        // The static variable is always defined, even if the initializer (any expression in PHP 8.3+) uses a possibly undefined variable,
        // in which case it may be null.
        if ($default_type->isPossiblyUndefined()) {
            $default_type = $default_type->withIsPossiblyUndefined(false)->nullableClone();
        }
        $variable->setUnionType($default_type->eraseRealTypeSetRecursively());
        // TODO: Probably not true in a loop?
        // TODO: Expand this to assigning to variables? (would need to make references invalidate that, and skip this in the global scope)
        $variable->enablePhanFlagBits(\Phan\Language\Element\Flags::IS_CONSTANT_DEFINITION);

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

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
    public function visitEcho(Node $node): Context
    {
        return $this->visitPrint($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPrint(Node $node): Context
    {
        $code_base = $this->code_base;
        $context = $this->context;
        $expr_node = $node->children['expr'];
        $type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $expr_node,
            true
        );

        if (!$type->hasPrintableScalar()) {
            if ($type->isArray()) {
                $this->emitIssue(
                    Issue::TypeConversionFromArray,
                    $expr_node->lineno ?? $node->lineno,
                    'string'
                );
                return $context;
            }
            if (!$context->isStrictTypes()) {
                try {
                    foreach ($type->withStaticResolvedInContext($context)->asClassList($code_base, $context) as $clazz) {
                        if ($clazz->hasMethodWithName($code_base, "__toString", true)) {
                            return $context;
                        }
                    }
                } catch (CodeBaseException) {
                    // Swallow "Cannot find class", go on to emit issue
                }
            }
            $this->emitIssue(
                Issue::TypeSuspiciousEcho,
                $expr_node->lineno ?? $node->lineno,
                ASTReverter::toShortString($expr_node),
                (string)$type
            );
        }

        return $context;
    }

    /**
     * These types are either types which create variables,
     * or types which will be checked in other parts of Phan
     */
    private const SKIP_VAR_CHECK_TYPES = [
        ast\AST_ARG_LIST       => true,  // may be a reference
        ast\AST_NAMED_ARG      => true,  // same, checked in analyzeCallToFunctionLike()
        ast\AST_ARRAY_ELEM     => true,  // [$x, $y] = expr() is an AST_ARRAY_ELEM. visitArray() checks the right-hand side.
        ast\AST_ASSIGN_OP      => true,  // checked in visitAssignOp
        ast\AST_ASSIGN_REF     => true,  // Creates by reference?
        ast\AST_ASSIGN         => true,  // checked in visitAssign
        ast\AST_DIM            => true,  // should be checked elsewhere, as part of check for array access to non-array/string
        ast\AST_EMPTY          => true,  // TODO: Enable this in the future?
        ast\AST_GLOBAL         => true,  // global $var;
        ast\AST_ISSET          => true,  // TODO: Enable this in the future?
        ast\AST_PARAM_LIST     => true,  // this creates the variable
        ast\AST_STATIC         => true,  // static $var;
        ast\AST_STMT_LIST      => true,  // ;$var; (Implicitly creates the variable. Already checked to emit PhanNoopVariable)
        ast\AST_USE_ELEM       => true,  // may be a reference, checked elsewhere
    ];

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node): Context
    {
        $this->analyzeNoOp($node, Issue::NoopVariable);
        $parent_node = \end($this->parent_node_list);
        if ($parent_node instanceof Node && !($node->flags & PhanAnnotationAdder::FLAG_IGNORE_UNDEF)) {
            $parent_kind = $parent_node->kind;
            if ($parent_kind === ast\AST_NAMED_ARG) {
                // analyzeCallToFunctionLike() decides once it knows whether the parameter is by reference,
                // but with the scope of this point, since a later argument can assign the variable (`f(a: $var, b: $var = 1)`).
                $variable_name = $node->children['name'];
                // @phan-suppress-next-line PhanUndeclaredProperty this is added for analyzeCallToFunctionLike()
                $node->is_undeclared_variable_of_named_argument = \is_string($variable_name)
                    && !$this->context->getScope()->hasVariableWithName($variable_name)
                    && !Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope());
            }
            if (!\array_key_exists($parent_kind, self::SKIP_VAR_CHECK_TYPES)
                && !($parent_kind === ast\AST_RETURN && $this->isInFunctionReturningReference())) {
                $this->checkForUndeclaredVariable($node);
            }
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
    public function visitArray(Node $node): Context
    {
        $this->analyzeNoOp($node, Issue::NoopArray);
        foreach ($node->children as $child) {
            // `[&$a]` defines `$a`, unless this is the left side of an assignment (`[&$a] = $values;` assigns `$a`)
            if ($child instanceof Node && ($child->flags & flags\ARRAY_ELEM_REF) && !($child->flags & PhanAnnotationAdder::FLAG_IGNORE_UNDEF)) {
                $value = $child->children['value'];
                if ($value instanceof Node) {
                    $this->createReferenceTarget($value, true);
                }
            }
        }
        return $this->context;
    }

    /** @internal */
    public const NAME_FOR_BINARY_OP = [
        flags\BINARY_BOOL_AND            => '&&',
        flags\BINARY_BOOL_OR             => '||',
        flags\BINARY_BOOL_XOR            => 'xor',
        flags\BINARY_BITWISE_OR          => '|',
        flags\BINARY_BITWISE_AND         => '&',
        flags\BINARY_BITWISE_XOR         => '^',
        flags\BINARY_CONCAT              => '.',
        flags\BINARY_ADD                 => '+',
        flags\BINARY_SUB                 => '-',
        flags\BINARY_MUL                 => '*',
        flags\BINARY_DIV                 => '/',
        flags\BINARY_MOD                 => '%',
        flags\BINARY_POW                 => '**',
        flags\BINARY_SHIFT_LEFT          => '<<',
        flags\BINARY_SHIFT_RIGHT         => '>>',
        flags\BINARY_IS_IDENTICAL        => '===',
        flags\BINARY_IS_NOT_IDENTICAL    => '!==',
        flags\BINARY_IS_EQUAL            => '==',
        flags\BINARY_IS_NOT_EQUAL        => '!=',
        flags\BINARY_IS_SMALLER          => '<',
        flags\BINARY_IS_SMALLER_OR_EQUAL => '<=',
        flags\BINARY_IS_GREATER          => '>',
        flags\BINARY_IS_GREATER_OR_EQUAL => '>=',
        flags\BINARY_SPACESHIP           => '<=>',
        flags\BINARY_COALESCE            => '??',
        flags\BINARY_PIPE                => '|>',
    ];

    /**
     * @param Node $node
     * A node of type AST_BINARY_OP to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitBinaryOp(Node $node): Context
    {
        $flags = $node->flags;
        if ($this->isInNoOpPosition($node)) {
            if (\in_array($flags, [flags\BINARY_BOOL_AND, flags\BINARY_BOOL_OR, flags\BINARY_COALESCE], true)) {
                if (!ScopeImpactCheckingVisitor::hasPossibleImpact($this->code_base, $this->context, $node->children['right'])) {
                    $this->emitIssue(
                        Issue::NoopBinaryOperator,
                        $node->lineno,
                        self::NAME_FOR_BINARY_OP[$flags] ?? ''
                    );
                }
            } else {
                $this->emitIssue(
                    Issue::NoopBinaryOperator,
                    $node->lineno,
                    self::NAME_FOR_BINARY_OP[$flags] ?? ''
                );
            }
        }
        switch ($flags) {
            case flags\BINARY_CONCAT:
                $this->analyzeBinaryConcat($node);
                break;
            case flags\BINARY_DIV:
            case flags\BINARY_POW:
            case flags\BINARY_MOD:
                $this->analyzeBinaryNumericOp($node);
                break;
            case flags\BINARY_SHIFT_LEFT:
            case flags\BINARY_SHIFT_RIGHT:
                $this->analyzeBinaryShift($node);
                break;
            case flags\BINARY_BITWISE_OR:
            case flags\BINARY_BITWISE_AND:
            case flags\BINARY_BITWISE_XOR:
                $this->analyzeBinaryBitwiseOp($node);
                break;
            case flags\BINARY_PIPE:
                $this->analyzeBinaryPipe($node);
                break;
        }
        return $this->context;
    }

    private function analyzeBinaryShift(Node $node): void
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
                if ($type->isNullableLabeled()) {
                    return false;
                }
                if ($type instanceof IntType || $type instanceof MixedType) {
                    return true;
                }
                if ($type instanceof LiteralFloatType) {
                    return $type->isValidBitwiseOperand();
                }
                return false;
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfIntegerOp,
            Issue::TypeInvalidRightOperandOfIntegerOp
        );
    }

    private function analyzeBinaryBitwiseOp(Node $node): void
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
                if ($type->isNullableLabeled()) {
                    return false;
                }
                if ($type instanceof IntType || $type instanceof StringType || $type instanceof MixedType) {
                    return true;
                }
                if ($type instanceof LiteralFloatType) {
                    return $type->isValidBitwiseOperand();
                }
                return false;
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfBitwiseOp,
            Issue::TypeInvalidRightOperandOfBitwiseOp
        );
    }

    /** @internal used by AssignOperatorAnalysisVisitor */
    public const ISSUE_TYPES_RIGHT_SIDE_ZERO = [
        flags\BINARY_POW => Issue::PowerOfZero,
        flags\BINARY_DIV => Issue::DivisionByZero,
        flags\BINARY_MOD => Issue::ModuloByZero,
    ];

    private function analyzeBinaryNumericOp(Node $node): void
    {
        $left = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );

        $right = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );
        if (!$right->isEmpty() && !$right->containsTruthy()) {
            $this->emitIssue(
                self::ISSUE_TYPES_RIGHT_SIDE_ZERO[$node->flags],
                $node->children['right']->lineno ?? $node->lineno,
                ASTReverter::toShortString($node),
                $right
            );
        }
        $this->warnAboutInvalidUnionType(
            $node,
            static function (Type $type): bool {
                return $type->isValidNumericOperand();
            },
            $left,
            $right,
            Issue::TypeInvalidLeftOperandOfNumericOp,
            Issue::TypeInvalidRightOperandOfNumericOp
        );
    }

    /**
     * @param Node $node with type AST_BINARY_OP
     * @param Closure(Type):bool $is_valid_type
     */
    private function warnAboutInvalidUnionType(
        Node $node,
        Closure $is_valid_type,
        UnionType $left,
        UnionType $right,
        string $left_issue_type,
        string $right_issue_type
    ): void {
        if (!$left->isEmpty()) {
            if (!$left->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $left_issue_type,
                    $node->children['left']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $left
                );
            }
        }
        if (!$right->isEmpty()) {
            if (!$right->hasTypeMatchingCallback($is_valid_type)) {
                $this->emitIssue(
                    $right_issue_type,
                    $node->children['right']->lineno ?? $node->lineno,
                    PostOrderAnalysisVisitor::NAME_FOR_BINARY_OP[$node->flags],
                    $right
                );
            }
        }
    }

    private function analyzeBinaryConcat(Node $node): void
    {
        $left = $node->children['left'];
        if ($left instanceof Node) {
            $this->checkExpressionInDynamicString($left);
        }
        $right = $node->children['right'];
        if ($right instanceof Node) {
            $this->checkExpressionInDynamicString($right);
        }
    }

    private function analyzeBinaryPipe(Node $node): void
    {
        $call_node = PipeExpression::createSyntheticCall($node);
        if ($call_node === null) {
            return;
        }
        switch ($call_node->kind) {
            case \ast\AST_CALL:
                $this->visitCall($call_node);
                return;
            case \ast\AST_METHOD_CALL:
                $this->visitMethodCall($call_node);
                return;
            case \ast\AST_STATIC_CALL:
                $this->visitStaticCall($call_node);
                return;
            case \ast\AST_NULLSAFE_METHOD_CALL:
                $this->visitNullsafeMethodCall($call_node);
                return;
        }
    }

    public const NAME_FOR_UNARY_OP = [
        flags\UNARY_BOOL_NOT => '!',
        flags\UNARY_BITWISE_NOT => '~',
        flags\UNARY_SILENCE => '@',
        flags\UNARY_PLUS => '+',
        flags\UNARY_MINUS => '-',
    ];

    /**
     * @param Node $node
     * A node of type AST_EMPTY to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEmpty(Node $node): Context
    {
        if ($this->isInNoOpPosition($node)) {
            $this->emitIssue(
                Issue::NoopEmpty,
                $node->lineno,
                ASTReverter::toShortString($node->children['expr'])
            );
        }
        return $this->context;
    }

    /**
     * @internal
     * Maps the flags of nodes with kind AST_CAST to their types
     */
    public const AST_CAST_FLAGS_LOOKUP = [
        flags\TYPE_NULL => 'unset',
        flags\TYPE_BOOL => 'bool',
        flags\TYPE_LONG => 'int',
        flags\TYPE_DOUBLE => 'float',
        flags\TYPE_STRING => 'string',
        flags\TYPE_ARRAY => 'array',
        flags\TYPE_OBJECT => 'object',
        // These aren't casts, but they are used in various places
        flags\TYPE_CALLABLE => 'callable',
        flags\TYPE_VOID => 'void',
        flags\TYPE_ITERABLE => 'iterable',
        flags\TYPE_FALSE => 'false',
        flags\TYPE_TRUE => 'true',
        flags\TYPE_STATIC => 'static',
    ];

    /**
     * @suppress PhanUselessBinaryAddRight this replaces 'unset' with 'null'
     */
    public const AST_TYPE_FLAGS_LOOKUP = [
        ast\flags\TYPE_NULL => 'null',
    ] + self::AST_CAST_FLAGS_LOOKUP;


    /**
     * @param Node $node
     * A node of type ast\AST_CAST to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCast(Node $node): Context
    {
        if ($node->flags !== flags\TYPE_VOID && $this->isInNoOpPosition($node)) {
            $this->emitIssue(
                Issue::NoopCast,
                $node->lineno,
                self::AST_CAST_FLAGS_LOOKUP[$node->flags] ?? 'unknown',
                ASTReverter::toShortString($node->children['expr'])
            );
        }
        if ($node->flags === flags\TYPE_NULL) {
            $this->emitIssue(
                Issue::CompatibleUnsetCast,
                $node->lineno,
                ASTReverter::toShortString($node)
            );
        }
        return $this->context;
    }

    /**
     * @param Node $node
     * A node of kind ast\AST_ISSET to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIsset(Node $node): Context
    {
        if ($this->isInNoOpPosition($node)) {
            $this->emitIssue(
                Issue::NoopIsset,
                $node->lineno,
                ASTReverter::toShortString($node->children['var'])
            );
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
    public function visitUnaryOp(Node $node): Context
    {
        if ($node->flags === flags\UNARY_SILENCE) {
            $expr = $node->children['expr'];
            if ($expr instanceof Node) {
                if ($expr->kind === ast\AST_UNARY_OP && $expr->flags === flags\UNARY_SILENCE) {
                    $this->emitIssue(
                        Issue::NoopRepeatedSilenceOperator,
                        $node->lineno,
                        ASTReverter::toShortString($node)
                    );
                }
            } else {
                // TODO: Other node kinds
                $this->emitIssue(
                    Issue::NoopUnaryOperator,
                    $node->lineno,
                    self::NAME_FOR_UNARY_OP[$node->flags] ?? ''
                );
            }
        } else {
            if ($this->isInNoOpPosition($node)) {
                $this->emitIssue(
                    Issue::NoopUnaryOperator,
                    $node->lineno,
                    self::NAME_FOR_UNARY_OP[$node->flags] ?? ''
                );
            }
        }
        return $this->context;
    }

    /**
     * @override
     */
    public function visitPreInc(Node $node): Context
    {
        return $this->analyzeIncOrDec($node);
    }

    /**
     * @override
     */
    public function visitPostInc(Node $node): Context
    {
        return $this->analyzeIncOrDec($node);
    }

    /**
     * @override
     */
    public function visitPreDec(Node $node): Context
    {
        return $this->analyzeIncOrDec($node);
    }

    /**
     * @override
     */
    public function visitPostDec(Node $node): Context
    {
        return $this->analyzeIncOrDec($node);
    }

    public const NAME_FOR_INC_OR_DEC_KIND = [
        ast\AST_PRE_INC => '++(expr)',
        ast\AST_PRE_DEC => '--(expr)',
        ast\AST_POST_INC => '(expr)++',
        ast\AST_POST_DEC => '(expr)--',
    ];

    /**
     * Emit PhanDeprecatedStringIncrement for `++` or `--` on a value that is known to be a non-numeric string.
     *
     * - `--` on a non-numeric string has no effect, and is deprecated since PHP 8.3.
     * - `++` on an empty string or a string that is not alphanumeric is deprecated since PHP 8.3.
     * - `++` on any other non-numeric string ('a' to 'b', 'Az' to 'Ba') is deprecated since PHP 8.5, in favor of str_increment().
     *
     * @param Node $node a node of kind ast\AST_PRE_INC, ast\AST_POST_INC, ast\AST_PRE_DEC or ast\AST_POST_DEC
     */
    private function checkDeprecatedStringIncOrDec(Node $node, UnionType $old_type): void
    {
        $target_php_version = Config::get_closest_target_php_version_id();
        if ($target_php_version < 80300 || $old_type->isEmpty()) {
            return;
        }
        $is_decrement = $node->kind === ast\AST_POST_DEC || $node->kind === ast\AST_PRE_DEC;
        // The newest version of the deprecations of the possible values: all of them must be deprecated
        $min_php_version = 0;
        $value = '';
        foreach ($old_type->getTypeSet() as $type) {
            if (!($type instanceof LiteralStringType) || $type->isNullable()) {
                return;
            }
            $value = $type->getValue();
            if (\is_numeric($value)) {
                return;
            }
            $min_php_version = \max($min_php_version, $is_decrement || $value === '' || !\ctype_alnum($value) ? 80300 : 80500);
        }
        if ($target_php_version < $min_php_version) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedStringIncrement,
            $node->lineno,
            ASTReverter::toShortString($node),
            StringUtil::jsonEncode($value),
            \intdiv($min_php_version, 10000) . '.' . \intdiv($min_php_version % 10000, 100)
        );
    }

    private function analyzeIncOrDec(Node $node): Context
    {
        $var = $node->children['var'];
        if ($var instanceof Node && AssignmentVisitor::checkWriteToNullsafeChain($this->code_base, $this->context, $var)) {
            return $this->context;
        }
        $old_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $var);
        if (!$old_type->canCastToUnionType(UnionType::fromFullyQualifiedPHPDocString('int|string|float'), $this->code_base)) {
            $this->emitIssue(
                Issue::TypeInvalidUnaryOperandIncOrDec,
                $node->lineno,
                self::NAME_FOR_INC_OR_DEC_KIND[$node->kind],
                $old_type
            );
        }
        $this->checkDeprecatedStringIncOrDec($node, $old_type);
        // The left can be a non-Node for an invalid AST
        $kind = $var->kind ?? null;
        if ($kind === \ast\AST_VAR) {
            $new_type = $old_type->getTypeAfterIncOrDec();
            if ($old_type->isNonNullIntType()) {
                // e.g. incrementing a positive-int results in a positive-int, decrementing a negative-int results in a negative-int.
                $is_decrement = $node->kind === ast\AST_POST_DEC || $node->kind === ast\AST_PRE_DEC;
                $new_type = IntType::computeArithmeticResultType(
                    $is_decrement ? '-' : '+',
                    $old_type,
                    LiteralIntType::instanceForValue(1, false)->asPHPDocUnionType()
                )->asPHPDocUnionType();
            }
            if ($old_type === $new_type) {
                return $this->context;
            }
            if (!$this->context->isInLoop()) {
                try {
                    $value = $old_type->asSingleScalarValueOrNull();
                    if (\is_numeric($value)) {
                        if ($node->kind === ast\AST_POST_DEC || $node->kind === ast\AST_PRE_DEC) {
                            @--$value;
                        } else {
                            @++$value;
                        }
                        // TODO: Compute the real type set.
                        $new_type = Type::fromObject($value)->asPHPDocUnionType();
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
            try {
                $variable = (new ContextNode($this->code_base, $this->context, $var))->getVariableStrict();
            } catch (IssueException | NodeException) {
                return $this->context;
            }
            $variable = clone $variable;
            $variable->setUnionType($new_type);
            $this->context->addScopeVariable($variable);
            return $this->context;
        }
        // Treat expr++ like expr -= -1 and expr-- like expr -= 1.
        // Use `-` to avoid false positives about array operations.
        // (This isn't 100% accurate for invalid types)
        $new_node = new Node(
            ast\AST_ASSIGN_OP,
            ast\flags\BINARY_SUB,
            [
                'var'  => $var,
                'expr' => ($node->kind === ast\AST_POST_DEC || $node->kind === ast\AST_PRE_DEC) ? 1 : -1,
            ],
            $node->lineno
        );
        return (new AssignOperatorAnalysisVisitor($this->code_base, $this->context))->visitBinarySub($new_node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitConst(Node $node): Context
    {
        $context = $this->context;
        try {
            // Based on UnionTypeVisitor::visitConst
            $constant = (new ContextNode(
                $this->code_base,
                $context,
                $node
            ))->getConst();

            // Mark that this constant has been referenced from
            // this context
            $constant->addReference($context);
        } catch (IssueException $exception) {
            // We need to do this in order to check keys and (after the first 5) values in AST arrays.
            // Other parts of the AST may also not be covered.
            Issue::maybeEmitInstance(
                $this->code_base,
                $context,
                $exception->getIssueInstance()
            );
        } catch (Exception) {
            // Swallow any other types of exceptions. We'll log the errors
            // elsewhere.
        }

        // Check to make sure we're doing something with the
        // constant
        $this->analyzeNoOp($node, Issue::NoopConstant);

        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClassConst(Node $node): Context
    {
        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConst();
            // Mark that this class constant has been referenced
            // from this context
            $constant->addReference($this->context);
        } catch (IssueException $exception) {
            // We need to do this in order to check keys and (after the first 5) values in AST arrays, possibly other types.
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (Exception) {
            // Swallow any other types of exceptions. We'll log the errors
            // elsewhere.
        }

        // Check to make sure we're doing something with the
        // class constant
        $this->analyzeNoOp($node, Issue::NoopConstant);

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
    public function visitClassConstGroup(Node $node): Context
    {
        if (($node->flags & (ast\flags\MODIFIER_FINAL | ast\flags\MODIFIER_PRIVATE)) === (ast\flags\MODIFIER_FINAL | ast\flags\MODIFIER_PRIVATE)) {
            $this->emitIssue(
                Issue::PrivateFinalConstant,
                $node->lineno
            );
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
    public function visitClassConstDecl(Node $node): Context
    {
        $class = $this->context->getClassInScope($this->code_base);


        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError('expected class const element to be a Node');
            }
            $name = $child_node->children['name'];
            if (!\is_string($name)) {
                throw new AssertionError('expected class const name to be a string');
            }
            if ($class->isTrait() && Config::get_closest_minimum_target_php_version_id() < 80200) {
                $this->emitIssue(Issue::CompatibleTraitConstant, $node->lineno, $class->getFQSEN(), $name);
            }
            try {
                $const_decl = $class->getConstantByNameInContext($this->code_base, $name, $this->context);
                $const_decl->getUnionType();
            } catch (IssueException $exception) {
                // We need to do this in order to check keys and (after the first 5) values in AST arrays, possibly other types.
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
            } catch (Exception) {
                // Swallow any other types of exceptions. We'll log the errors
                // elsewhere.
            }
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
    public function visitConstDecl(Node $node): Context
    {
        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError('expected const element to be a Node');
            }
            if ($child_node->kind === ast\AST_ATTRIBUTE_LIST) {
                // Skip appended attribute groups (PHP 8.5+)
                continue;
            }
            $name = $child_node->children['name'];
            if (!\is_string($name)) {
                throw new AssertionError('expected const name to be a string');
            }

            try {
                $fqsen = FullyQualifiedGlobalConstantName::fromStringInContext(
                    $name,
                    $this->context
                );
                $const_decl = $this->code_base->getGlobalConstantByFQSEN($fqsen);
                $const_decl->getUnionType();
            } catch (IssueException $exception) {
                // We need to do this in order to check keys and (after the first 5) values in AST arrays, possibly other types.
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
            } catch (Exception) {
                // Swallow any other types of exceptions. We'll log the errors
                // elsewhere.
            }
        }

        return $this->context;
    }

    /**
     * @param Node $node
     * A node of kind `ast\AST_CLASS_NAME` to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClassName(Node $node): Context
    {
        try {
            foreach ((new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, null, true, false) as $class) {
                $class->addReference($this->context);
            }
        } catch (CodeBaseException $exception) {
            $exception_fqsen = $exception->getFQSEN();
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredClassReference,
                $node->lineno,
                [(string)$exception_fqsen],
                IssueFixSuggester::suggestSimilarClassForGenericFQSEN($this->code_base, $this->context, $exception_fqsen)
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance($this->code_base, $this->context, $exception->getIssueInstance());
        }

        // Check to make sure we're doing something with the
        // ::class class constant
        $this->analyzeNoOp($node, Issue::NoopConstant);

        return $this->context;
    }

    /**
     * @param Node $node
     * A node of kind ast\AST_CLOSURE or ast\AST_ARROW_FUNC to analyze
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node): Context
    {
        $func = $this->context->getFunctionLikeInScope($this->code_base);

        $return_type = $func->getUnionType();

        if (!$return_type->isEmpty()
            && !$func->hasReturn()
            && !$this->declNeverReturns($node)
            && !$return_type->isNull()
            && !$return_type->isNeverType()
        ) {
            $this->warnTypeMissingReturn($func, $node);
        }
        $this->analyzeNoOp($node, Issue::NoopClosure);
        $this->checkForFunctionInterfaceIssues($node, $func);
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
    public function visitArrowFunc(Node $node): Context
    {
        return $this->visitClosure($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitReturn(Node $node): Context
    {
        $context = $this->context;
        // Make sure we're actually returning from a method.
        if (!$context->isInFunctionLikeScope()) {
            return $context;
        }
        $code_base = $this->code_base;

        // Check real return types instead of phpdoc return types in traits for #800
        // TODO: Why did Phan originally not analyze return types of traits at all in 4c6956c05222e093b29393ceaa389ffb91041bdc
        $is_trait = false;
        if ($context->isInClassScope()) {
            $clazz = $context->getClassInScope($code_base);
            $is_trait = $clazz->isTrait();
        }

        // Get the method/function/closure we're in
        $method = $context->getFunctionLikeInScope($code_base);

        $override_return_types = Config::getValue('override_return_types') || Config::get_track_all_inferred_types();
        $allow_overriding_vague_return_types = Config::getValue('allow_overriding_vague_return_types');

        // Mark the method as returning something (even if void)
        $expr = $node->children['expr'];
        if (null !== $expr) {
            $method->setHasReturn(true);
        }

        if ($method->returnsRef()) {
            $this->analyzeReturnsReference($method, $node);
        }
        if ($method->hasYield()) {  // Function that is syntactically a Generator.
            $this->analyzeReturnInGenerator($method, $node);
            // TODO: Compare against TReturn of Generator<TKey,TValue,TSend,TReturn>
            return $context;  // Analysis was completed in PreOrderAnalysisVisitor
        }

        // Figure out what we intend to return
        // (For traits, lower the false positive rate by comparing against the real return type instead of the phpdoc type (#800))
        // TODO: Is `withAddedClassForResolvedSelf` still needed? (this is the only use of that method)
        $method_return_type = $is_trait ? $method->getRealReturnType()->withAddedClassForResolvedSelf($method->getContext()) : $method->getUnionType();

        // Check for failing to return a value, or returning a value in a void method.
        if ($method_return_type->hasRealTypeSet()) {
            if (!$this->checkIsValidReturnExpressionForType($node, $method_return_type->asRealUnionType(), $method)) {
                return $context;
            }
        }

        // This leaves functions which aren't syntactically generators.

        // Figure out what is actually being returned
        // TODO: Properly check return values of array shapes
        foreach ($this->getReturnTypes($context, $expr, $node->lineno) as $lineno => [$expression_type, $inner_node]) {
            // If there is no declared type, see if we can deduce
            // what it should be based on the return type
            if ($method_return_type->isEmpty()
                || $method->isReturnTypeUndefined()
            ) {
                $method->setIsReturnTypeUndefined(true);

                // Set the inferred type of the method based
                // on what we're returning
                $method->setUnionType($method->getUnionType()->withUnionType($expression_type));

                // No point in comparing this type to the
                // type we just set
                continue;
            }

            // Check if the return type is compatible with the declared return type.
            $is_mismatch = false;
            if (!$method->isReturnTypeUndefined()) {
                $resolved_expression_type = $expression_type->withStaticResolvedInContext($context);
                $method_return_type = $method_return_type->withStaticResolvedInContext($context);
                // We allow base classes to cast to subclasses, and subclasses to cast to base classes,
                // but don't allow subclasses to cast to subclasses on a separate branch of the inheritance tree
                if (!$this->checkCanCastToReturnType($resolved_expression_type, $method_return_type)) {
                    $this->emitTypeMismatchReturnIssue($resolved_expression_type, $method, $method_return_type, $lineno, $inner_node);
                    $is_mismatch = true;
                } elseif (Config::get_strict_return_checking() && $resolved_expression_type->typeCount() > 1) {
                    $is_mismatch = self::analyzeReturnStrict($code_base, $method, $resolved_expression_type, $method_return_type, $lineno, $inner_node);
                }
            }

            // For functions that aren't syntactically Generators,
            // update the set/existence of return values.
            //
            // If `override_return_types` is enabled, update the return value even if it doesn't
            // match the method's declared return value. One reason for this approach is because
            // phpdoc return values may be incorrect or out of date, and phan errors about the
            // incorrect phpdoc  return values may be suppressed.
            if ($method->isReturnTypeModifiable() && (!$is_mismatch || $override_return_types)) {
                // Add the new type to the set of values returned by the
                // method
                $union_type = $method->getUnionType();
                if ($allow_overriding_vague_return_types && $union_type->hasMixedTypeStrict()) {
                    $union_type = $union_type->makeFromFilter(static function (Type $type): bool {
                        return \get_class($type) !== MixedType::class;
                    });
                }
                $method->setUnionType($union_type->withUnionType($expression_type));
            }
        }

        return $context;
    }

    private function checkIsValidReturnExpressionForType(Node $node, UnionType $real_type, FunctionInterface $method): bool
    {
        $expr = $node->children['expr'];
        if ($real_type->isNeverType()) {
            $this->emitIssue(
                Issue::SyntaxReturnStatementInNever,
                $expr->lineno ?? $node->lineno,
                $method->getNameForIssue(),
                'never'
            );
            return false;
        }
        if ($expr !== null) {
            if ($real_type->isVoidType()) {
                $this->emitIssue(
                    Issue::SyntaxReturnValueInVoid,
                    $expr->lineno ?? $node->lineno,
                    'void',
                    $method->getNameForIssue(),
                    'return;',
                    'return ' . ASTReverter::toShortString($expr) . ';'
                );
                return false;
            }
        } else {
            // `function test() : ?string { return; }` is a fatal error. (We already checked for generators)
            if (!$real_type->isVoidType()) {
                $this->emitIssue(
                    Issue::SyntaxReturnExpectedValue,
                    $node->lineno,
                    $method->getNameForIssue(),
                    $real_type,
                    'return null',
                    'return'
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @param Node $node a node of kind ast\AST_RETURN
     */
    private function analyzeReturnsReference(FunctionInterface $method, Node $node): void
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            // `return $undef;` in a function returning a reference defines `$undef` (as null)
            $this->createReferenceTarget($expr, true);
        }
        if ((!$expr instanceof Node) || !\in_array($expr->kind, ArgumentType::REFERENCE_NODE_KINDS, true)) {
            $is_possible_reference = ArgumentType::isExpressionReturningReference($this->code_base, $this->context, $expr);

            if (!$is_possible_reference) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeNonVarReturnByRef,
                    $expr->lineno ?? $node->lineno,
                    $method->getRepresentationForIssue()
                );
            }
        }
    }

    /**
     * Emits Issue::TypeMismatchReturnNullable or TypeMismatchReturn, unless suppressed
     * @param Node|string|int|float|null $inner_node
     */
    private function emitTypeMismatchReturnIssue(UnionType $expression_type, FunctionInterface $method, UnionType $method_return_type, int $lineno, Node|float|int|null|string $inner_node): void
    {
        if ($this->shouldSuppressIssue(Issue::TypeMismatchReturnReal, $lineno)) {
            // Suppressing TypeMismatchReturnReal also suppresses less severe return type mismatches
            return;
        }
        if (!$expression_type->isNull() && $this->checkCanCastToReturnTypeIfWasNonNullInstead($expression_type, $method_return_type)) {
            if ($this->shouldSuppressIssue(Issue::TypeMismatchReturn, $lineno)) {
                // Suppressing TypeMismatchReturn also suppresses TypeMismatchReturnNullable
                return;
            }
            $issue_type = Issue::TypeMismatchReturnNullable;
        } else {
            $issue_type = Issue::TypeMismatchReturn;
            // TODO: Don't warn for callable <-> string
            if ($method_return_type->hasRealTypeSet()) {
                // Always emit a real type warning about returning a value in a void method
                $real_method_return_type = $method_return_type->getRealUnionType();
                $real_expression_type = $expression_type->getRealUnionType();
                if ($real_method_return_type->isVoidType() ||
                    ($expression_type->hasRealTypeSet() && !$real_expression_type->canCastToDeclaredType($this->code_base, $this->context, $real_method_return_type))) {
                    $this->emitIssue(
                        Issue::TypeMismatchReturnReal,
                        $lineno,
                        self::returnExpressionToShortString($inner_node),
                        $expression_type->toErrorMessageString(),
                        self::toDetailsForRealTypeMismatch($expression_type),
                        $method->getNameForIssue(),
                        $method_return_type->toErrorMessageString(),
                        self::toDetailsForRealTypeMismatch($method_return_type)
                    );
                    return;
                }
            }
        }
        // Some suppressions are based on line number (e.g. (at)phan-suppress-next-line)
        $context = (clone $this->context)->withLineNumberStart($lineno);

        if ($context->hasSuppressIssue($this->code_base, Issue::TypeMismatchReturnProbablyReal)) {
            // Suppressing ProbablyReal also suppresses the less severe version.
            return;
        }
        if ($issue_type === Issue::TypeMismatchReturn) {
            if ($expression_type->hasRealTypeSet() &&
                !$expression_type->getRealUnionType()->canCastToDeclaredType($this->code_base, $context, $method_return_type)) {
                // The argument's real type is completely incompatible with the documented phpdoc type.
                //
                // Either the phpdoc type is wrong or the argument is likely wrong.
                $this->emitIssue(
                    Issue::TypeMismatchReturnProbablyReal,
                    $lineno,
                    self::returnExpressionToShortString($inner_node),
                    $expression_type->toErrorMessageString(),
                    PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($expression_type),
                    $method->getNameForIssue(),
                    $method_return_type->toErrorMessageString(),
                    PostOrderAnalysisVisitor::toDetailsForRealTypeMismatch($method_return_type)
                );
                return;
            }
        }
        if ($context->hasSuppressIssue($this->code_base, $issue_type)) {
            // Suppressing TypeMismatchReturn also suppresses the less severe version.
            return;
        }
        if ($issue_type === Issue::TypeMismatchReturn && self::doesExpressionHaveSuperClassOfTargetType($this->code_base, $expression_type, $method_return_type)) {
            $this->emitIssue(
                Issue::TypeMismatchReturnSuperType,
                $lineno,
                self::returnExpressionToShortString($inner_node),
                $expression_type->toErrorMessageString(),
                $method->getNameForIssue(),
                $method_return_type->toErrorMessageString()
            );
            return;
        }
        $this->emitIssue(
            $issue_type,
            $lineno,
            self::returnExpressionToShortString($inner_node),
            $expression_type->toErrorMessageString(),
            $method->getNameForIssue(),
            $method_return_type->toErrorMessageString()
        );
    }

    /**
     * Returns true if the expression has an object class type that is a supertype of the target type.
     * (to emit a less severe issue for possible false positives)
     *
     * Normally, an exact type or subtype is required.
     * @internal
     */
    public static function doesExpressionHaveSuperClassOfTargetType(
        CodeBase $code_base,
        UnionType $expression_type,
        UnionType $target_type
    ): bool {
        $target_object_types = $target_type->objectTypesWithKnownFQSENs();
        if ($target_object_types->isEmpty()) {
            return false;
        }
        $expression_object_types = $expression_type->objectTypesWithKnownFQSENs();
        if ($expression_object_types->isEmpty()) {
            return false;
        }
        foreach ($expression_object_types->getTypeSet() as $type) {
            foreach ($target_object_types->getTypeSet() as $other) {
                if ($other->canCastToTypeWithoutConfig($type, $code_base)) {
                    continue 2;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Converts the type to a description of the real type (if different from phpdoc type) for Phan's issue messages
     * @internal
     */
    public static function toDetailsForRealTypeMismatch(UnionType $type): string
    {
        $real_type = $type->getRealUnionType();
        if ($real_type->isEqualTo($type)) {
            return '';
        }
        if ($real_type->isEmpty()) {
            return ' (no real type)';
        }
        return " (real type $real_type)";
    }

    private function analyzeReturnInGenerator(
        FunctionInterface $method,
        Node $node
    ): void {
        $method_generator_type = $method->getReturnTypeAsGeneratorTemplateType();
        $type_list = $method_generator_type->getTemplateParameterTypeList();
        // Generator<TKey,TValue,TSend,TReturn>
        if (\count($type_list) !== 4) {
            return;
        }
        $expected_return_type = $type_list[3];
        if ($expected_return_type->isEmpty()) {
            return;
        }

        $context = $this->context;
        $code_base = $this->code_base;

        foreach ($this->getReturnTypes($context, $node->children['expr'], $node->lineno) as $lineno => [$expression_type, $inner_node]) {
            $expression_type = $expression_type->withStaticResolvedInContext($context);
            // We allow base classes to cast to subclasses, and subclasses to cast to base classes,
            // but don't allow subclasses to cast to subclasses on a separate branch of the inheritance tree
            if (!self::checkCanCastToReturnType($expression_type, $expected_return_type)) {
                $this->emitTypeMismatchReturnIssue($expression_type, $method, $expected_return_type, $lineno, $inner_node);
            } elseif (Config::get_strict_return_checking() && $expression_type->typeCount() > 1) {
                self::analyzeReturnStrict($code_base, $method, $expression_type, $expected_return_type, $lineno, $inner_node);
            }
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitYield(Node $node): Context
    {
        $context = $this->context;
        // Make sure we're actually returning from a method.
        if (!$context->isInFunctionLikeScope()) {
            return $context;
        }

        // Get the method/function/closure we're in
        $method = $context->getFunctionLikeInScope($this->code_base);

        // Figure out what we intend to return
        $method_generator_type = $method->getReturnTypeAsGeneratorTemplateType();
        $type_list = $method_generator_type->getTemplateParameterTypeList();
        if (\count($type_list) === 0) {
            return $context;
        }
        return $this->compareYieldAgainstDeclaredType($node, $method, $context, $type_list);
    }

    /**
     * @param list<UnionType> $template_type_list
     */
    private function compareYieldAgainstDeclaredType(Node $node, FunctionInterface $method, Context $context, array $template_type_list): Context
    {
        $code_base = $this->code_base;

        $type_list_count = \count($template_type_list);

        $yield_value_node = $node->children['value'];
        if ($yield_value_node === null) {
            $yield_value_type = VoidType::instance(false)->asRealUnionType();
        } else {
            $yield_value_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $yield_value_node);
        }
        $expected_value_type = $template_type_list[\min(1, $type_list_count - 1)];
        try {
            if (!$yield_value_type->withStaticResolvedInContext($context)->canCastToUnionType($expected_value_type->withStaticResolvedInContext($context), $code_base)) {
                $this->emitIssue(
                    Issue::TypeMismatchGeneratorYieldValue,
                    $node->lineno,
                    ASTReverter::toShortString($yield_value_node),
                    (string)$yield_value_type,
                    $method->getNameForIssue(),
                    (string)$expected_value_type,
                    '\Generator<' . implode(',', $template_type_list) . '>'
                );
            }
        } catch (RecursionDepthException) {
        }

        if ($type_list_count > 1) {
            $yield_key_node = $node->children['key'];
            if ($yield_key_node === null) {
                // When yielding without a key, PHP automatically assigns auto-incrementing integer keys.
                // We use VoidType to represent "no explicit key", but it's compatible with int (and void).
                $yield_key_type = VoidType::instance(false)->asRealUnionType();
            } else {
                $yield_key_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $yield_key_node);
            }
            $expected_key_type = $template_type_list[0];
            // Check if the yield key type is compatible with the expected key type.
            // Special case: VoidType (missing key) is compatible with IntType (PHP auto-increments)
            // and with VoidType itself (no key expected).
            $is_compatible = $yield_key_type->withStaticResolvedInContext($context)->canCastToUnionType($expected_key_type->withStaticResolvedInContext($context), $code_base);
            if (!$is_compatible && $yield_key_node === null) {
                // Missing key produces int keys in PHP, so void (missing key) is compatible with int
                $is_compatible = $expected_key_type->hasType(IntType::instance(false));
            }
            if (!$is_compatible) {
                $this->emitIssue(
                    Issue::TypeMismatchGeneratorYieldKey,
                    $node->lineno,
                    ASTReverter::toShortString($yield_key_node),
                    (string)$yield_key_type,
                    $method->getNameForIssue(),
                    (string)$expected_key_type,
                    '\Generator<' . implode(',', $template_type_list) . '>'
                );
            }
        }
        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitYieldFrom(Node $node): Context
    {
        $context = $this->context;
        // Make sure we're actually returning from a method.
        if (!$context->isInFunctionLikeScope()) {
            return $context;
        }

        // Get the method/function/closure we're in
        $method = $context->getFunctionLikeInScope($this->code_base);
        $code_base = $this->code_base;

        $expr = $node->children['expr'];
        $yield_from_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr);
        if ($yield_from_type->isEmpty()) {
            return $context;
        }
        $yield_from_resolved_type = $yield_from_type->withStaticResolvedInContext($context);
        if (!$yield_from_resolved_type->hasIterable($code_base)) {
            $this->emitIssue(
                Issue::TypeInvalidYieldFrom,
                $node->lineno,
                ASTReverter::toShortString($expr),
                (string)$yield_from_type
            );
            return $context;
        }

        if (BlockAnalysisVisitor::isEmptyIterable($yield_from_type)) {
            RedundantCondition::emitInstance(
                $expr,
                $this->code_base,
                (clone $this->context)->withLineNumberStart($expr->lineno ?? $node->lineno),
                Issue::EmptyYieldFrom,
                [(string)$yield_from_type],
                Closure::fromCallable([BlockAnalysisVisitor::class, 'isEmptyIterable'])
            );
        }

        // Figure out what we intend to return
        $method_generator_type = $method->getReturnTypeAsGeneratorTemplateType();
        $type_list = $method_generator_type->getTemplateParameterTypeList();
        if (\count($type_list) === 0) {
            return $context;
        }
        return $this->compareYieldFromAgainstDeclaredType($node, $method, $context, $type_list, $yield_from_type);
    }

    /**
     * @param list<UnionType> $template_type_list
     */
    private function compareYieldFromAgainstDeclaredType(Node $node, FunctionInterface $method, Context $context, array $template_type_list, UnionType $yield_from_type): Context
    {
        $code_base = $this->code_base;

        $type_list_count = \count($template_type_list);

        // TODO: Can do a better job of analyzing expressions that are just arrays or subclasses of Traversable.
        //
        // A solution would need to check for (at)return Generator|T[]
        $yield_from_generator_type = $yield_from_type->asGeneratorTemplateType();

        $actual_template_type_list = $yield_from_generator_type->getTemplateParameterTypeList();
        $actual_type_list_count = \count($actual_template_type_list);
        if ($actual_type_list_count === 0) {
            return $context;
        }

        $yield_value_type = $actual_template_type_list[\min(1, $actual_type_list_count - 1)];
        $expected_value_type = $template_type_list[\min(1, $type_list_count - 1)];
        if (!$yield_value_type->withStaticResolvedInContext($context)->canCastToUnionType($expected_value_type, $code_base)) {
            $this->emitIssue(
                Issue::TypeMismatchGeneratorYieldValue,
                $node->lineno,
                sprintf('(values of %s)', ASTReverter::toShortString($node->children['expr'])),
                (string)$yield_value_type,
                $method->getNameForIssue(),
                (string)$expected_value_type,
                '\Generator<' . implode(',', $template_type_list) . '>'
            );
        }

        if ($type_list_count > 1 && $actual_type_list_count > 1) {
            // TODO: finalize syntax to indicate the absence of a key or value (e.g. use void instead?)
            $yield_key_type = $actual_template_type_list[0];
            $expected_key_type = $template_type_list[0];
            if (!$yield_key_type->withStaticResolvedInContext($context)->canCastToUnionType($expected_key_type, $code_base)) {
                $this->emitIssue(
                    Issue::TypeMismatchGeneratorYieldKey,
                    $node->lineno,
                    sprintf('(keys of %s)', ASTReverter::toShortString($node->children['expr'])),
                    (string)$yield_key_type,
                    $method->getNameForIssue(),
                    (string)$expected_key_type,
                    '\Generator<' . implode(',', $template_type_list) . '>'
                );
            }
        }
        return $context;
    }

    private function checkCanCastToReturnType(UnionType $expression_type, UnionType $method_return_type): bool
    {
        if ($method_return_type->isVoidType()) {
            // Allow returning null (or void) expressions from phpdoc return void - the callers can't tell
            return $expression_type->isNull();
        }
        if ($method_return_type->isNeverType()) {
            return $expression_type->isNeverType();
        }
        if ($expression_type->hasRealTypeSet() && $method_return_type->hasRealTypeSet()) {
            $real_expression_type = $expression_type->getRealUnionType();
            $real_method_return_type = $method_return_type->getRealUnionType();
            if (!$real_method_return_type->isNull() && !$real_expression_type->canCastToDeclaredType($this->code_base, $this->context, $real_method_return_type)) {
                return false;
            }
        }
        try {
            // Stop allowing base classes to cast to subclasses
            return $expression_type->canCastToUnionType($method_return_type, $this->code_base);
        } catch (RecursionDepthException) {
            return false;
        }
    }

    /**
     * Precondition: checkCanCastToReturnType is false
     */
    private function checkCanCastToReturnTypeIfWasNonNullInstead(UnionType $expression_type, UnionType $method_return_type): bool
    {
        $nonnull_expression_type = $expression_type->nonNullableClone();
        if ($nonnull_expression_type === $expression_type || $nonnull_expression_type->isEmpty()) {
            return false;
        }
        return $this->checkCanCastToReturnType($nonnull_expression_type, $method_return_type);
    }

    /**
     * @param Node|string|int|float|null $inner_node
     */
    private function analyzeReturnStrict(
        CodeBase $code_base,
        FunctionInterface $method,
        UnionType $expression_type,
        UnionType $method_return_type,
        int $lineno,
        Node|float|int|null|string $inner_node
    ): bool {
        $type_set = $expression_type->getTypeSet();
        $context = $this->context;
        if (\count($type_set) < 2) {
            throw new AssertionError("Expected at least two types for strict return type checks");
        }

        $mismatch_type_set = UnionType::empty();
        $mismatch_expanded_types = null;

        // For the strict
        foreach ($type_set as $type) {
            // See if the argument can be cast to the
            // parameter
            if (!$type->asPHPDocUnionType()->canCastToUnionType(
                $method_return_type,
                $code_base
            )) {
                if ($method->isPHPInternal()) {
                    // If we are not in strict mode and we accept a string parameter
                    // and the argument we are passing has a __toString method then it is ok
                    if (!$context->isStrictTypes() && $method_return_type->hasStringType()) {
                        if ($type->asPHPDocUnionType()->hasClassWithToStringMethod($code_base, $context)) {
                            continue;
                        }
                    }
                }
                $mismatch_type_set = $mismatch_type_set->withType($type);
                if ($mismatch_expanded_types === null) {
                    // Warn about the first type
                    $mismatch_expanded_types = $type;
                }
            }
        }


        if ($mismatch_expanded_types === null) {
            // No mismatches
            return false;
        }

        // If we have TypeMismatchReturn already, then also suppress the partial mismatch warnings (e.g. PartialTypeMismatchReturn) as well.
        if ($this->context->hasSuppressIssue($code_base, Issue::TypeMismatchReturn)) {
            return false;
        }
        $this->emitIssue(
            self::getStrictIssueType($mismatch_type_set),
            $lineno,
            self::returnExpressionToShortString($inner_node),
            (string)$expression_type,
            $method->getNameForIssue(),
            (string)$method_return_type,
            $mismatch_expanded_types
        );
        return true;
    }

    /**
     * @param Node|string|int|float|null $node
     */
    private static function returnExpressionToShortString(Node|float|int|null|string $node): string
    {
        return $node !== null ? ASTReverter::toShortString($node) : 'void';
    }

    private static function getStrictIssueType(UnionType $union_type): string
    {
        if ($union_type->typeCount() === 1) {
            $type = $union_type->getTypeSet()[0];
            if ($type instanceof NullType) {
                return Issue::PossiblyNullTypeReturn;
            }
            if ($type instanceof FalseType) {
                return Issue::PossiblyFalseTypeReturn;
            }
        }
        return Issue::PartialTypeMismatchReturn;
    }

    /**
     * @param \ast\Node|float|int|null|string $node
     * @return \Generator<int, array{0: UnionType, 1:\ast\Node|string|int|float|null}>
     */
    private function getReturnTypes(Context $context, Node|float|int|null|string $node, int $return_lineno): \Generator
    {
        if (!($node instanceof Node)) {
            if (null === $node) {
                yield $return_lineno => [VoidType::instance(false)->asRealUnionType(), null];
                return;
            }
            yield $return_lineno => [
                UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $context,
                    $node,
                    true
                ),
                $node
            ];
            return;
        }
        $kind = $node->kind;
        if ($kind === ast\AST_CONDITIONAL) {
            yield from self::deduplicateUnionTypes($this->getReturnTypesOfConditional($context, $node));
            return;
        } elseif ($kind === ast\AST_ARRAY) {
            $expression_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $context, $node, true);
            if ($expression_type->hasTopLevelArrayShapeTypeInstances()) {
                yield $return_lineno => [$expression_type, $node];
                return;
            }

            // TODO: Infer list<>
            $key_type_enum = GenericArrayType::getKeyTypeOfArrayNode($this->code_base, $context, $node);
            foreach (self::deduplicateUnionTypes($this->getReturnTypesOfArray($context, $node)) as $return_lineno => [$elem_type, $elem_node]) {
                yield $return_lineno => [
                    $elem_type->asGenericArrayTypes($key_type_enum),  // TODO: Infer corresponding key types
                    $elem_node,
                ];
            }
            return;
        }

        $expression_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $context,
            $node,
            true
        );

        yield $return_lineno => [$expression_type, $node];
    }

    /**
     * @return \Generator|UnionType[]
     * @phan-return \Generator<int,array{0:UnionType,1:Node|string|int|float|null}>
     */
    private function getReturnTypesOfConditional(Context $context, Node $node): \Generator
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
                yield from $this->getReturnTypes($context, $true_node, $node->lineno);
                return;
            } else {
                // The condition is unconditionally false

                // Add the type for the 'false' side
                yield from $this->getReturnTypes($context, $node->children['false'], $node->lineno);
                return;
            }
        }

        // TODO: false_context once there is a NegatedConditionVisitor
        // TODO: emit no-op if $cond_node is a literal, such as `if (2)`
        // - Also note that some things such as `true` and `false` are ast\AST_NAME nodes.

        if ($cond_node instanceof Node) {
            // TODO: Use different contexts and merge those, in case there were assignments or assignments by reference in both sides of the conditional?
            // Reuse the BranchScope (sort of unintuitive). The ConditionVisitor returns a clone and doesn't modify the original.
            $base_context = $this->context;
            // We don't bother analyzing visitReturn in PostOrderAnalysisVisitor, right now.
            // This may eventually change, just to ensure the expression is checked for issues
            $true_context = (new ConditionVisitor(
                $this->code_base,
                $base_context
            ))->__invoke($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))->__invoke($cond_node);
        } else {
            $true_context = $context;
            $false_context = $this->context;
        }

        // Allow nested ternary operators, or arrays within ternary operators
        if (isset($node->children['true'])) {
            yield from $this->getReturnTypes($true_context, $true_node, $true_node->lineno ?? $node->lineno);
        } else {
            // E.g. From the left-hand side of yield (int|false) ?: default,
            // yielding false is impossible.
            foreach ($this->getReturnTypes($true_context, $true_node, $true_node->lineno ?? $node->lineno) as $lineno => $details) {
                $raw_union_type = $details[0];
                if ($raw_union_type->isEmpty() || !$raw_union_type->containsFalsey()) {
                    yield $lineno => $details;
                } else {
                    $raw_union_type = $raw_union_type->nonFalseyClone();
                    if (!$raw_union_type->isEmpty()) {
                        yield $lineno => [$raw_union_type, $details[1]];
                    }
                }
            }
        }

        $false_node = $node->children['false'];
        yield from $this->getReturnTypes($false_context, $false_node, $false_node->lineno ?? $node->lineno);
    }

    /**
     * @param iterable<int, array{0: UnionType, 1: Node|string|int|float|null}> $types
     * @return \Generator<int, array{0: UnionType, 1: Node|string|int|float|null}>
     */
    private static function deduplicateUnionTypes(iterable $types): \Generator
    {
        $unique_types = [];
        foreach ($types as $lineno => $details) {
            $type = $details[0];
            foreach ($unique_types as $old_type) {
                if ($type->isEqualTo($old_type)) {
                    continue 2;
                }
            }
            yield $lineno => $details;
            $unique_types[] = $type;
        }
    }

    /**
     * @return \Generator|iterable<int,array{0:UnionType,1:Node|int|string|float|null}>
     * @phan-return \Generator<int,array{0:UnionType,1:Node|int|string|float|null}>
     */
    private function getReturnTypesOfArray(Context $context, Node $node): \Generator
    {
        if (\count($node->children) === 0) {
            // Possibly unreachable (array shape would be returned instead)
            yield $node->lineno => [MixedType::instance(false)->asPHPDocUnionType(), $node];
            return;
        }
        foreach ($node->children as $elem) {
            if (!($elem instanceof Node)) {
                // We already emit PhanSyntaxError
                continue;
            }
            // Don't bother recursing more than one level to iterate over possible types.
            if ($elem->kind === \ast\AST_UNPACK) {
                // Could optionally recurse to better analyze `yield [...SOME_EXPRESSION_WITH_MIX_OF_VALUES]`
                yield $elem->lineno => [
                    UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $context,
                        $elem,
                        true
                    ),
                    $elem
                ];
                continue;
            }
            $value_node = $elem->children['value'];
            if ($value_node instanceof Node) {
                yield $elem->lineno => [
                    UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $context,
                        $value_node,
                        true
                    ),
                    $value_node
                ];
            } else {
                yield $elem->lineno => [
                    Type::fromObject($value_node)->asRealUnionType(),
                    $value_node
                ];
            }
        }
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropDecl(Node $node): Context
    {
        return $this->context;
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropGroup(Node $node): Context
    {
        $this->checkUnionTypeCompatibility($node->children['type']);
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
    public function visitCall(Node $node): Context
    {
        $expression = $node->children['expr'];
        try {
            // Get the function.
            // If the function is undefined, always try to create a placeholder from Phan's type signatures for internal functions so they can still be type checked.
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode(true);

            foreach ($function_list_generator as $function) {
                // Check the call for parameter and argument types
                $this->analyzeCallToFunctionLike(
                    $function,
                    $node
                );
                // Check if function has #[NoDiscard] attribute and return value is ignored
                $this->checkNoDiscardAttribute($node, $function);
                if ($function instanceof Func && \strcasecmp($function->getName(), 'assert') === 0 && $function->getFQSEN()->getNamespace() === '\\') {
                    $this->context = $this->analyzeAssert($this->context, $node);
                }
            }
        } catch (CodeBaseException) {
            // ignore it.
        }

        $this->analyzeSpecialFunctionCall($node);

        return $this->context;
    }

    private function analyzeAssert(Context $context, Node $node): Context
    {
        $args_first_child = $node->children['args']->children[0] ?? null;
        if (!($args_first_child instanceof Node)) {
            // Ignore both first-class callable conversion(AST_CALLABLE_CONVERT) and assert with no args silently.
            return $this->context;
        }

        // Look to see if the asserted expression says anything about
        // the types of any variables.
        return (new ConditionVisitor(
            $this->code_base,
            $context
        ))->__invoke($args_first_child);
    }

    private function analyzeSpecialFunctionCall(Node $node): void
    {
        $function_name = ConditionVisitor::getFunctionName($node);
        if (!\is_string($function_name)) {
            return;
        }
        $function_name = \strtolower(\ltrim($function_name, '\\'));
        if ($function_name === 'set_error_handler') {
            $this->analyzeSetErrorHandlerCall($node);
        }
    }

    private function analyzeSetErrorHandlerCall(Node $node): void
    {
        $args_node = $node->children['args'];
        if (!$args_node instanceof Node) {
            return;
        }
        $callback_node = $args_node->children[0] ?? null;
        if (!($callback_node instanceof Node || \is_string($callback_node))) {
            return;
        }
        $handled = $callback_node instanceof Node ? $this->applyStaticPropertyModificationsForMethodCallableNode($callback_node) : null;
        if ($handled !== null) {
            return;
        }
        $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext(
            $this->code_base,
            $this->context,
            $callback_node,
            false
        );
        if (!$function_like_list) {
            $this->context = $this->context->withoutStaticPropertyOverrides();
            return;
        }
        $resolved_method = false;
        $applied_modifications = false;
        foreach ($function_like_list as $function_like) {
            if ($function_like instanceof Method) {
                $resolved_method = true;
                $this->ensureStaticPropertyModificationsComputed($function_like);
                $applied_modifications = $this->applyStaticPropertyModificationsFromMethod($function_like) || $applied_modifications;
            }
        }
        if (!$resolved_method) {
            $this->context = $this->context->withoutStaticPropertyOverrides();
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNew(Node $node): Context
    {
        $class_list = [];
        try {
            $context_node = new ContextNode(
                $this->code_base,
                $this->context,
                $node
            );

            $method = $context_node->getMethod(
                '__construct',
                false,
                false,
                true
            );

            $class_list = $context_node->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);
            // Add a reference to each class this method
            // could be called on
            foreach ($class_list as $class) {
                $class->addReference($this->context);
                if ($class->isDeprecated()) {
                    $this->emitIssue(
                        Issue::DeprecatedClass,
                        $node->lineno,
                        (string)$class->getFQSEN(),
                        $class->getContext()->getFile(),
                        (string)$class->getContext()->getLineNumberStart(),
                        $class->getDeprecationReason()
                    );
                }
            }

            $this->analyzeMethodVisibility(
                $method,
                $node,
                false
            );

            if (!$this->isConstructorOfClassStringUnknown($method, $node)) {
                $this->analyzeCallToFunctionLike(
                    $method,
                    $node
                );
            }

            foreach ($class_list as $class) {
                if ($class->isEnum() || $class->isAbstract() || $class->isInterface() || $class->isTrait()) {
                    // Check the full list of classes if any of the classes
                    // are abstract or interfaces.
                    $this->checkForInvalidNewType($node, $class_list);
                    break;
                }
            }
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (Exception) {
            // If we can't figure out what kind of a call
            // this is, don't worry about it
        }
        if ($this->isInNoOpPosition($node)) {
            $this->warnNoopNew($node, $class_list);
        }

        return $this->context;
    }

    /**
     * Returns true if the arguments of `new $class(...)` must not be checked against $constructor,
     * because $class is a `class-string<T>` and T declares no constructor (neither itself nor
     * through a parent class), so $constructor is the placeholder Phan creates for T.
     *
     * The string names some class that is T or extends/implements T, which can declare any
     * constructor - e.g. T is an interface, or a class whose subclasses add one. When T does
     * declare a constructor, or cannot be extended (a final class), the arguments are still
     * checked against it.
     *
     * @param Node $node a node of type AST_NEW
     */
    private function isConstructorOfClassStringUnknown(Method $constructor, Node $node): bool
    {
        if (!$constructor->isFakeConstructor()) {
            return false;
        }
        $class_node = $node->children['class'];
        if (!$class_node instanceof Node || $class_node->kind === ast\AST_NAME) {
            return false;
        }
        $class_fqsen = $constructor->getClassFQSEN();
        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            return false;
        }
        $class = $this->code_base->getClassByFQSEN($class_fqsen);
        if ($class->isFinal()) {
            // The string can only name this class, whose placeholder constructor takes no arguments.
            return false;
        }
        return (new ContextNode(
            $this->code_base,
            $this->context,
            $class_node
        ))->isClassOnlyNamedByClassString($class);
    }

    /**
     * @param Node $node a node of type AST_NEW
     * @param Clazz[] $class_list
     */
    private function checkForInvalidNewType(Node $node, array $class_list): void
    {
        // This is either a string (new 'something'()) or a class name (new something())
        $class_node = $node->children['class'];
        if (!$class_node instanceof Node) {
            foreach ($class_list as $class) {
                $this->warnIfInvalidClassForNew($class, $node);
            }
            return;
        }

        if ($class_node->kind === ast\AST_NAME) {
            $class_name = $class_node->children['name'];
            if (\is_string($class_name) && \strcasecmp('static', $class_name) === 0) {
                if ($this->isStaticGuaranteedToBeNonAbstract()) {
                    return;
                }
            }
            foreach ($class_list as $class) {
                $this->warnIfInvalidClassForNew($class, $class_node);
            }
            return;
        }
        foreach (UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $class_node)->getTypeSet() as $type) {
            if ($type instanceof LiteralStringType) {
                try {
                    $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($type->getValue());
                } catch (FQSENException) {
                    // Probably already emitted elsewhere, but emit anyway
                    Issue::maybeEmit(
                        $this->code_base,
                        $this->context,
                        Issue::TypeExpectedObjectOrClassName,
                        $node->lineno,
                        ASTReverter::toShortString($node),
                        $type->getValue()
                    );
                    continue;
                }
                if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                    continue;
                }
                $class = $this->code_base->getClassByFQSEN($class_fqsen);
                $this->warnIfInvalidClassForNew($class, $class_node);
            }
        }
    }

    /**
     * Given a call to `new static`, is the context likely to be guaranteed to be a non-abstract class?
     */
    private function isStaticGuaranteedToBeNonAbstract(): bool
    {
        if (!$this->context->isInMethodScope()) {
            return false;
        }
        // TODO: Could do a better job with closures inside of methods
        $method = $this->context->getFunctionLikeInScope($this->code_base);
        if (!($method instanceof Method)) {
            if ($method instanceof Func && $method->isClosure()) {
                // closures can be rebound
                return true;
            }
            return false;
        }
        return !$method->isStatic();
    }

    /**
     * Checks if this is referring to the `static` class name (also allows `self` if $allow_self is true)
     *
     * @param Node|int|string|float|null $node
     */
    public static function isStaticNameNode(Node|float|int|null|string $node, bool $allow_self): bool
    {
        if (!$node instanceof Node) {
            return false;
        }
        if ($node->kind !== ast\AST_NAME) {
            return false;
        }
        $name = $node->children['name'];
        if (!\is_string($name)) {
            return false;
        }
        return \strcasecmp($name, 'static') === 0 || ($allow_self && \strcasecmp($name, 'self') === 0);
    }

    private function warnIfInvalidClassForNew(Clazz $class, Node $node): void
    {
        // Make sure we're not instantiating an abstract
        // class
        if ($class->isEnum()) {
            $this->emitIssue(Issue::TypeInstantiateEnum, $node->lineno, (string)$class->getFQSEN());
        } elseif ($class->isAbstract()) {
            $this->emitIssue(
                self::isStaticNameNode($node, false) ? Issue::TypeInstantiateAbstractStatic : Issue::TypeInstantiateAbstract,
                $node->lineno,
                (string)$class->getFQSEN()
            );
        } elseif ($class->isInterface()) {
            // Make sure we're not instantiating an interface
            $this->emitIssue(
                Issue::TypeInstantiateInterface,
                $node->lineno,
                (string)$class->getFQSEN()
            );
        } elseif ($class->isTrait()) {
            // Make sure we're not instantiating a trait
            $this->emitIssue(
                self::isStaticNameNode($node, true) ? Issue::TypeInstantiateTraitStaticOrSelf : Issue::TypeInstantiateTrait,
                $node->lineno,
                (string)$class->getFQSEN()
            );
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node): Context
    {
        try {
            // Fetch the class list, and emit warnings as a side effect.
            // TODO: Unify UnionTypeVisitor, AssignmentVisitor, and PostOrderAnalysisVisitor
            (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, Issue::TypeInvalidInstanceof);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (CodeBaseException $exception) {
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredClassInstanceof,
                $node->lineno,
                [(string)$exception->getFQSEN()],
                IssueFixSuggester::suggestSimilarClassForGenericFQSEN(
                    $this->code_base,
                    $this->context,
                    $exception->getFQSEN(),
                    // Only suggest classes/interfaces for alternatives to instanceof checks. Don't suggest traits.
                    IssueFixSuggester::createFQSENFilterForClasslikeCategories($this->code_base, true, false, true)
                )
            );
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
    public function visitStaticCall(Node $node): Context
    {
        if (ParentPropertyHookCall::isParentHookCall($this->context, $node)) {
            $this->analyzeParentPropertyHookCall($node);
            return $this->context;
        }
        // Get the name of the method being called
        $method_name = $node->children['method'];

        // Give up on things like Class::$var
        if (!\is_string($method_name)) {
            if ($method_name instanceof Node) {
                $method_name = UnionTypeVisitor::anyStringLiteralForNode($this->code_base, $this->context, $method_name);
            }
            if (!\is_string($method_name)) {
                $method_name_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['method']);
                if (!$method_name_type->canCastToUnionType(StringType::instance(false)->asPHPDocUnionType(), $this->code_base)) {
                    Issue::maybeEmit(
                        $this->code_base,
                        $this->context,
                        Issue::TypeInvalidStaticMethodName,
                        $node->lineno,
                        $method_name_type
                    );
                }
                return $this->context;
            }
        }

        // Get the name of the static class being referenced
        $static_class = '';
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            $static_class = (string)$class_node;
        } elseif ($class_node->kind === ast\AST_NAME) {
            $static_class = (string)$class_node->children['name'];
        }

        $method = $this->getStaticMethodOrEmitIssue($node, $method_name);

        if ($method === null) {
            // Short circuit on a constructor being called statically
            // on something other than 'parent'
            if ($method_name === '__construct' && $static_class !== 'parent') {
                $this->emitConstructorWarning($node, $static_class, $method_name);
            }
            return $this->context;
        }

        try {
            if ($method_name === '__construct') {
                $this->checkNonAncestorConstructCall($node, $static_class, $method_name);
                // Even if it exists, continue on and type check the arguments passed.
            }
            // If the method being called isn't actually static and it's
            // not a call to parent::f from f, we may be in trouble.
            if (!$method->isStatic() && !$this->canCallInstanceMethodFromContext($method, $static_class)) {
                $class_list = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node->children['class']
                ))->getClassList();

                if (\count($class_list) > 0) {
                    $class = \array_values($class_list)[0];

                    $this->emitIssue(
                        Issue::StaticCallToNonStatic,
                        $node->lineno,
                        "{$class->getFQSEN()}::{$method_name}()",
                        $method->getFileRef()->getFile(),
                        (string)$method->getFileRef()->getLineNumberStart()
                    );
                }
            }

            $this->analyzeMethodVisibility(
                $method,
                $node,
                true
            );

            // Make sure the parameters look good
            $this->analyzeCallToFunctionLike(
                $method,
                $node
            );

            // Check if method has #[NoDiscard] attribute and return value is ignored
            $this->checkNoDiscardAttribute($node, $method);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (NullsafeShortCircuitException) {
            // The call is part of a chain that PHP short-circuits to null: it never happens, so it references no method.
            return $this->context;
        } catch (Exception) {
            // If we can't figure out the class for this method
            // call, cry YOLO and mark every method with that
            // name with a reference.
            if (Config::get_track_references()
                && Config::getValue('dead_code_detection_prefer_false_negative')
            ) {
                foreach ($this->code_base->getMethodSetByName(
                    $method_name
                ) as $method) {
                    $method->addReference($this->context);
                }
            }

            // If we can't figure out what kind of a call
            // this is, don't worry about it
            return $this->context;
        }

        if ($class_node instanceof Node && $class_node->kind === ast\AST_NAME) {
            $class_name = $class_node->children['name'] ?? null;
            if (\is_string($class_name)) {
                $class_name_lower = \strtolower($class_name);
                if (\in_array($class_name_lower, ['self', 'static', 'parent'], true)) {
                    $this->applyStaticPropertyModificationsFromMethod($method);
                    $this->widenStaticPropertyOverridesAssignedByMethod($method);
                }
            }
        }
        return $this->context;
    }

    private function applyStaticPropertyModificationsForMethodCallableNode(Node $callback_node): ?bool
    {
        if ($callback_node->kind === ast\AST_CALLABLE_CONVERT) {
            $expr = $callback_node->children['expr'] ?? null;
            if ($expr instanceof Node) {
                return $this->applyStaticPropertyModificationsForMethodCallableNode($expr);
            }
            return null;
        }
        $kind = $callback_node->kind;
        if ($kind !== ast\AST_METHOD_CALL && $kind !== ast\AST_STATIC_CALL && $kind !== ast\AST_NULLSAFE_METHOD_CALL) {
            return null;
        }
        $method_name = $callback_node->children['method'] ?? null;
        if ($method_name === null) {
            return null;
        }
        $is_static = $kind !== ast\AST_METHOD_CALL && $kind !== ast\AST_NULLSAFE_METHOD_CALL;
        try {
            $context_node = new ContextNode($this->code_base, $this->context, $callback_node);
            $method_list = $context_node->getMethodList($method_name, $is_static, true);
        } catch (IssueException|CodeBaseException|NodeException) {
            return null;
        }
        if (!$method_list) {
            return null;
        }
        $applied = false;
        foreach ($method_list as $method) {
            if ($method instanceof Method) {
                $this->ensureStaticPropertyModificationsComputed($method);
                $applied = $this->applyStaticPropertyModificationsFromMethod($method) || $applied;
            }
        }
        return $applied;
    }

    private function applyStaticPropertyModificationsFromMethod(Method $method, bool $may_create_override = true): bool
    {
        $modifications_by_class = $method->getStaticPropertyModifications();
        if (!$modifications_by_class) {
            return false;
        }
        $calling_class = $this->context->getClassFQSENOrNull();
        if (!$calling_class) {
            return false;
        }
        $applied = false;
        foreach ($modifications_by_class as $entry) {
            $target_class_fqsen = $entry['class'];
            $property_modifications = $entry['properties'];
            if (!$property_modifications) {
                continue;
            }
            if (!$this->canApplyStaticPropertyOverride($calling_class, $target_class_fqsen)) {
                continue;
            }
            $overrides_to_clear = [];
            $updates = [];
            foreach ($property_modifications as $property_name => $property_info) {
                /** @var array{type:UnionType,is_late_static:bool} $property_info */
                $property_type = $property_info['type'];
                $is_late_static = $property_info['is_late_static'];
                if (!$this->doesStaticOverrideMatchTargetClass($property_name, $target_class_fqsen, $calling_class, $is_late_static)) {
                    continue;
                }
                $old_override = $this->context->getStaticPropertyIfOverridden($property_name);
                if ($old_override === null) {
                    if (!$may_create_override) {
                        continue;
                    }
                    $updates[$property_name] = $property_type;
                    continue;
                }
                $overrides_to_clear[] = $property_name;
                $updates[$property_name] = $old_override->withUnionType($property_type);
            }
            if ($overrides_to_clear) {
                $this->context = $this->context->withoutStaticPropertyOverrides($overrides_to_clear);
            }
            if ($updates) {
                foreach ($updates as $property_name => $property_type) {
                    $this->context = $this->context->withStaticPropertySetToTypeByName($property_name, $property_type);
                }
                $applied = true;
            }
        }
        return $applied;
    }

    /**
     * Widens the types inferred for the static properties that the body of the called method assigns,
     * when the types it assigns were not recorded by analyzing it (see applyStaticPropertyModificationsFromMethod).
     * That is the case when the method is declared after the caller (it is analyzed later), when it is inherited
     * (the types are recorded for the method it is inherited from) and when the type of the assigned value is unknown.
     * The property is then assumed to have its previous type or its declared type, or an unknown type if it declares none.
     *
     * This only looks at the node of the method, instead of analyzing the method before its turn:
     * analyzing it twice changes what is inferred in it, e.g. from the types of the properties it has assigned the first time.
     */
    private function widenStaticPropertyOverridesAssignedByMethod(Method $method): void
    {
        if (!$this->context->getScope()->hasVariableWithName(Context::VAR_NAME_STATIC_PROPERTIES)) {
            // Nothing was inferred about static properties (the common case).
            return;
        }
        $method_node = $method->getNode();
        if (!($method_node instanceof Node)) {
            return;
        }
        self::$assigned_static_property_nodes ??= new WeakMap();
        if (!self::$assigned_static_property_nodes->offsetExists($method_node)) {
            $nodes = [];
            $stmts_node = $method_node->children['stmts'] ?? null;
            if ($stmts_node instanceof Node) {
                self::collectAssignedStaticPropertyNodes($stmts_node, self::getNamesOfNonNullParameters($method_node), $nodes);
            }
            // The assignments that may make the property null come first: the type of a property is widened once.
            \usort(
                $nodes,
                /**
                 * @param array{0:Node,1:bool} $a
                 * @param array{0:Node,1:bool} $b
                 */
                static fn (array $a, array $b): int => $b[1] <=> $a[1]
            );
            self::$assigned_static_property_nodes[$method_node] = $nodes;
        }
        $nodes = self::$assigned_static_property_nodes[$method_node];
        if (!$nodes) {
            return;
        }
        $applied = $this->getAppliedStaticPropertyModifications($method);
        $widened = [];
        foreach ($nodes as [$node, $may_assign_null]) {
            $name = $node->children['prop'];
            if (\is_string($name)) {
                $recorded_class = $applied ? self::getClassOfRecordedStaticPropertyModification($node, $method, $this->code_base) : null;
                if ($recorded_class !== null && isset($applied[$recorded_class][$name])) {
                    // The type recorded for this assignment was applied.
                    continue;
                }
                $names = [$name];
            } else {
                // `self::$$name = $v` may assign any of the properties, and no type is recorded for it.
                $names = $this->context->getStaticPropertyOverrideNames();
            }
            foreach ($names as $name) {
                if (!isset($widened[$name]) && $this->widenStaticPropertyOverrideAssignedByNode($name, $node, $method, $may_assign_null)) {
                    $widened[$name] = true;
                }
            }
        }
    }

    /**
     * @param string $name the name of a static property of the class in scope
     * @param Node $node an AST_STATIC_PROP node that is assigned in the body of $method, with that name or a dynamic name
     * @param bool $may_assign_null false if the assignment cannot make the property null
     * @return bool whether the type inferred for the property was widened
     */
    private function widenStaticPropertyOverrideAssignedByNode(string $name, Node $node, Method $method, bool $may_assign_null): bool
    {
        $old_override = $this->context->getStaticPropertyIfOverridden($name);
        if ($old_override === null) {
            return false;
        }
        $property = $this->getStaticPropertyOfClassInScope($name);
        if ($property !== null && !$this->mayBeSameStaticProperty($node, $method, $property)) {
            // e.g. `Other::$prop = $v`, or `self::$prop = $v` in a parent class when the class in scope declares its own $prop.
            return false;
        }
        $this->context = $this->context->withoutStaticPropertyOverrides([$name]);
        if ($property === null) {
            return true;
        }
        $declared_type = self::getDeclaredTypeOfProperty($property);
        // Without a declared type, the type after the call is unknown.
        $this->context = $this->context->withStaticPropertySetToTypeByName(
            $name,
            $declared_type !== null ? $old_override->withUnionType(self::getTypeOfAssignedValue($declared_type, $may_assign_null)) : UnionType::empty()
        );
        return true;
    }

    /**
     * @return array<string,array<string,true>> the names of the static properties with types recorded for $method
     * that applyStaticPropertyModificationsFromMethod applies to the properties of the class in scope,
     * by the class they are recorded for (see getClassOfRecordedStaticPropertyModification).
     *
     * Unless the property was assigned as `static::$prop`, a recorded type is only applied if the class it is recorded for
     * declares the property of the class in scope: not for another property with the same name,
     * and not for a property that the class of the method inherits.
     *
     * A property that the method also assigns a value of an unknown type to is left out: no type is recorded for that assignment.
     */
    private function getAppliedStaticPropertyModifications(Method $method): array
    {
        $calling_class = $this->context->getClassFQSENOrNull();
        if (!$calling_class) {
            return [];
        }
        $applied = [];
        foreach ($method->getStaticPropertyModifications() as $entry) {
            $target_class_fqsen = $entry['class'];
            if (!$this->canApplyStaticPropertyOverride($calling_class, $target_class_fqsen)) {
                continue;
            }
            foreach ($entry['properties'] as $name => $property_info) {
                if (!$method->hasStaticPropertyModificationOfUnknownType($target_class_fqsen, $name)
                    && $this->doesStaticOverrideMatchTargetClass($name, $target_class_fqsen, $calling_class, $property_info['is_late_static'])
                ) {
                    $applied[$target_class_fqsen->__toString()][$name] = true;
                }
            }
        }
        return $applied;
    }

    /**
     * @param Node $node an AST_STATIC_PROP node with a name, which is assigned in the body of $method
     * @return ?string the class that the type assigned to the property is recorded for when $method is analyzed
     * (see AssignmentVisitor::getStaticPropertyAssignmentTarget): the class of the method for `self` and `static`,
     * its parent class for `parent`. Null for anything else, such as `ClassName::$prop`: no type is recorded.
     */
    private static function getClassOfRecordedStaticPropertyModification(Node $node, Method $method, CodeBase $code_base): ?string
    {
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node) || $class_node->kind !== ast\AST_NAME) {
            return null;
        }
        $class_name = $class_node->children['name'];
        $method_context = $method->getContext();
        if (!\is_string($class_name) || !$method_context->isInClassScope()) {
            return null;
        }
        switch (\strtolower($class_name)) {
            case 'self':
            case 'static':
                return $method_context->getClassFQSEN()->__toString();
            case 'parent':
                try {
                    $method_class = $method_context->getClassInScope($code_base);
                } catch (CodeBaseException) {
                    return null;
                }
                return $method_class->hasParentType() ? $method_class->getParentClassFQSEN()->__toString() : null;
        }
        return null;
    }

    /**
     * @return ?UnionType the type that the property is declared with (a real type or `@var`),
     * or null if it is declared without a type, or with `@var null` and no real type.
     * (Without a declaration, the type of the property is inferred from its default value and from the assignments analyzed so far.)
     */
    private static function getDeclaredTypeOfProperty(Property $property): ?UnionType
    {
        $has_real_type = !$property->getRealUnionType()->isEmpty();
        if (!$has_real_type && $property->getPHPDocUnionType()->isEmpty()) {
            return null;
        }
        $type = $property->getUnionType();
        // A property with the real type `null` can only be null.
        return $type->isEmpty() || ($type->isNull() && !$has_real_type) ? null : $type;
    }

    /**
     * @param UnionType $type the declared type of a property
     * @param bool $may_be_null false for an assignment that cannot make the property null
     * @return UnionType the type that an assignment of an unknown value adds to the type of the property.
     * `?T` is converted to `T|null`, which is what the property has after a null check and an assignment of `T`
     * (unlike `?T`, that is only reported as a possibly null value where `T` is expected by the strict type checks),
     * or to `T` if the value is not null.
     */
    private static function getTypeOfAssignedValue(UnionType $type, bool $may_be_null): UnionType
    {
        if (!$type->containsNullable() || $type->isNull()) {
            return $type;
        }
        $non_null_type = $type->nonNullableClone();
        $type_set = $non_null_type->getTypeSet();
        // (nonNullableClone() makes the real type non-null-mixed when the property has no real type.)
        $real_type_set = $type->hasRealTypeSet() ? $non_null_type->getRealTypeSet() : [];
        if ($may_be_null) {
            $null_type = NullType::instance(false);
            $type_set[] = $null_type;
            if ($real_type_set && $type->getRealUnionType()->containsNullable()) {
                $real_type_set[] = $null_type;
            }
        }
        return UnionType::of($type_set, $real_type_set);
    }

    /**
     * @return ?Property the static property with that name of the class in scope, or null if there is no such property
     */
    private function getStaticPropertyOfClassInScope(string $property_name): ?Property
    {
        $class_fqsen = $this->context->getClassFQSENOrNull();
        if (!$class_fqsen || !$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            return null;
        }
        try {
            return $this->code_base->getClassByFQSEN($class_fqsen)->getPropertyByNameInContext(
                $this->code_base,
                $property_name,
                $this->context,
                true
            );
        } catch (IssueException | CodeBaseException) {
            return null;
        }
    }

    /**
     * @param Node $node an AST_STATIC_PROP node that is assigned in the body of $method, with the name of $property or a dynamic name
     * @param Property $property a static property of the class in scope
     * @return bool false if the assigned property is known to be another property than $property
     */
    private function mayBeSameStaticProperty(Node $node, Method $method, Property $property): bool
    {
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node) || $class_node->kind !== ast\AST_NAME) {
            // The class is an expression, such as `$class::$prop`.
            return true;
        }
        $class_name = $class_node->children['name'];
        $method_context = $method->getContext();
        if (!\is_string($class_name) || !$method_context->isInClassScope()) {
            return true;
        }
        try {
            switch (\strtolower($class_name)) {
                case 'static':
                    // The class that the calling method was called on.
                    return true;
                case 'self':
                    $class_fqsen = $method_context->getClassFQSEN();
                    break;
                case 'parent':
                    $method_class = $method_context->getClassInScope($this->code_base);
                    if (!$method_class->hasParentType()) {
                        return false;
                    }
                    $class_fqsen = $method_class->getParentClassFQSEN();
                    break;
                default:
                    if ($class_node->flags & ast\flags\NAME_NOT_FQ) {
                        $class_fqsen = FullyQualifiedClassName::fromNameInContext($class_name, $method_context);
                    } elseif ($class_node->flags & ast\flags\NAME_RELATIVE) {
                        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($method_context->getNamespace() . '\\' . $class_name);
                    } else {
                        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\' . $class_name);
                    }
                    break;
            }
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                return false;
            }
            $class = $this->code_base->getClassByFQSEN($class_fqsen);
            if ($class->isTrait()) {
                // `self` in a trait is the class using the trait.
                return true;
            }
            // An inherited property has the defining FQSEN of the property of the class that declares it,
            // and a property from a trait has the defining FQSEN of the property of the class that uses the trait:
            // the properties of two classes using the same trait are different properties.
            $name = $property->getName();
            return $class->hasPropertyWithName($this->code_base, $name)
                && $class->getPropertyByName($this->code_base, $name)->getDefiningFQSEN() === $property->getDefiningFQSEN();
        } catch (FQSENException | CodeBaseException) {
            return true;
        }
    }

    /**
     * Collects the AST_STATIC_PROP nodes that are assigned by running $node, such as `self::$prop = $v`,
     * `static::$prop['key'] ??= $v`, `[self::$a, Other::$b] = $v`, `self::$prop++` or `foreach ($values as self::$prop)`,
     * without analyzing it.
     *
     * The bodies of closures, functions and classes declared in $node are not run by running $node.
     *
     * @param array<string,true> $non_null_params the names of the parameters of the method that are not null (see getNamesOfNonNullParameters)
     * @param list<array{0:Node,1:bool}> $nodes the nodes found so far, each with whether the assignment may make the property null
     */
    private static function collectAssignedStaticPropertyNodes(Node $node, array $non_null_params, array &$nodes): void
    {
        switch ($node->kind) {
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
            case ast\AST_FUNC_DECL:
            case ast\AST_CLASS:
                return;
            case ast\AST_ASSIGN:
                $var = $node->children['var'];
                if ($var instanceof Node) {
                    self::collectStaticPropertyNodesOfAssignmentTarget($var, self::mayBeNull($node->children['expr'], $non_null_params), $nodes);
                }
                break;
            case ast\AST_ASSIGN_REF:
                $var = $node->children['var'];
                if ($var instanceof Node) {
                    self::collectStaticPropertyNodesOfAssignmentTarget($var, true, $nodes);
                }
                break;
            case ast\AST_ASSIGN_OP:
            case ast\AST_PRE_INC:
            case ast\AST_PRE_DEC:
            case ast\AST_POST_INC:
            case ast\AST_POST_DEC:
                // After `self::$prop .= $v`, `self::$prop ??= $v` or `self::$prop--`, the property is only null if it was null.
                $var = $node->children['var'];
                if ($var instanceof Node) {
                    self::collectStaticPropertyNodesOfAssignmentTarget($var, false, $nodes);
                }
                break;
            case ast\AST_FOREACH:
                foreach (['value', 'key'] as $name) {
                    $var = $node->children[$name];
                    if ($var instanceof Node) {
                        self::collectStaticPropertyNodesOfAssignmentTarget($var, true, $nodes);
                    }
                }
                break;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                self::collectAssignedStaticPropertyNodes($child, $non_null_params, $nodes);
            }
        }
    }

    /**
     * @param Node $method_node the node of a method
     * @return array<string,true> the names of the parameters with a real type that does not allow null.
     * (A parameter that the method assigns null to is rare, and is not looked for.)
     */
    private static function getNamesOfNonNullParameters(Node $method_node): array
    {
        $params_node = $method_node->children['params'] ?? null;
        if (!($params_node instanceof Node)) {
            return [];
        }
        $names = [];
        foreach ($params_node->children as $param) {
            if (!($param instanceof Node) || ($param->flags & ast\flags\PARAM_REF)) {
                continue;
            }
            $name = $param->children['name'];
            if (!\is_string($name)) {
                continue;
            }
            if ($param->flags & ast\flags\PARAM_VARIADIC) {
                // An array.
                $names[$name] = true;
                continue;
            }
            $type = $param->children['type'];
            $default = $param->children['default'];
            // `T $param = null` allows null.
            if ($type instanceof Node && self::isNonNullTypeNode($type) && ($default === null || !self::mayBeNull($default, []))) {
                $names[$name] = true;
            }
        }
        return $names;
    }

    /**
     * @param Node $type the node of a type declaration
     * @return bool whether the type does not allow null
     */
    private static function isNonNullTypeNode(Node $type): bool
    {
        switch ($type->kind) {
            case ast\AST_TYPE:
                return $type->flags !== ast\flags\TYPE_NULL && $type->flags !== ast\flags\TYPE_MIXED;
            case ast\AST_NAME:
            case ast\AST_TYPE_INTERSECTION:
                return true;
            case ast\AST_TYPE_UNION:
                foreach ($type->children as $child) {
                    if (!($child instanceof Node) || !self::isNonNullTypeNode($child)) {
                        return false;
                    }
                }
                return true;
        }
        // e.g. ast\AST_NULLABLE_TYPE
        return false;
    }

    /**
     * @param Node|string|int|float|null $expr an expression in the body of a method
     * @param array<string,true> $non_null_params the names of the parameters of the method that are not null
     * @return bool false if the syntax of the expression shows that its value is not null:
     * a literal, an array, `new`, a closure, an operation that does not result in null, or a parameter that is not null.
     * True for anything else, such as a call.
     */
    private static function mayBeNull(Node|float|int|string|null $expr, array $non_null_params): bool
    {
        if (!($expr instanceof Node)) {
            return $expr === null;
        }
        switch ($expr->kind) {
            case ast\AST_ARRAY:
            case ast\AST_NEW:
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
            case ast\AST_ENCAPS_LIST:
                return false;
            case ast\AST_CONST:
                $name = $expr->children['name']->children['name'] ?? null;
                return !\is_string($name) || !\in_array(\strtolower($name), ['true', 'false'], true);
            case ast\AST_VAR:
                $name = $expr->children['name'];
                return !\is_string($name) || !isset($non_null_params[$name]);
            case ast\AST_UNARY_OP:
                // `@$expr` is the value of the expression.
                return $expr->flags === ast\flags\UNARY_SILENCE && self::mayBeNull($expr->children['expr'], $non_null_params);
            case ast\AST_BINARY_OP:
                // `$a ?? $b` is null if $b is null.
                return $expr->flags === ast\flags\BINARY_COALESCE && self::mayBeNull($expr->children['right'], $non_null_params);
            case ast\AST_CONDITIONAL:
                // `$a ?: $b` is $a if it is truthy.
                return ($expr->children['true'] !== null && self::mayBeNull($expr->children['true'], $non_null_params))
                    || self::mayBeNull($expr->children['false'], $non_null_params);
            case ast\AST_ASSIGN:
                // `self::$a = self::$b = []`
                return self::mayBeNull($expr->children['expr'], $non_null_params);
        }
        return true;
    }

    /**
     * Collects the AST_STATIC_PROP nodes that an assignment to $node writes to:
     * the node itself, the expression that a dimension is fetched from (and not the dimension, which is read),
     * the values of array destructuring (and not the keys), and the target of a reference (`foreach ($values as &self::$prop)`).
     *
     * @param bool $may_assign_null whether the assignment may make $node null
     * @param list<array{0:Node,1:bool}> $nodes the nodes found so far, each with whether the assignment may make the property null
     */
    private static function collectStaticPropertyNodesOfAssignmentTarget(Node $node, bool $may_assign_null, array &$nodes): void
    {
        switch ($node->kind) {
            case ast\AST_STATIC_PROP:
                $nodes[] = [$node, $may_assign_null];
                return;
            case ast\AST_DIM:
                // Assigning a dimension (`self::$prop[] = $v`) does not make the property null.
                $expr = $node->children['expr'];
                if ($expr instanceof Node) {
                    self::collectStaticPropertyNodesOfAssignmentTarget($expr, false, $nodes);
                }
                return;
            case ast\AST_REF:
                $var = $node->children['var'];
                if ($var instanceof Node) {
                    self::collectStaticPropertyNodesOfAssignmentTarget($var, $may_assign_null, $nodes);
                }
                return;
            case ast\AST_ARRAY:
                foreach ($node->children as $element) {
                    $value = $element instanceof Node ? $element->children['value'] : null;
                    if ($value instanceof Node) {
                        self::collectStaticPropertyNodesOfAssignmentTarget($value, $may_assign_null, $nodes);
                    }
                }
                return;
        }
    }

    private function ensureStaticPropertyModificationsComputed(Method $method): void
    {
        $key = (string)$method->getFQSEN();
        if (isset(self::$checked_methods_for_static_property_modifications[$key])) {
            return;
        }
        self::$checked_methods_for_static_property_modifications[$key] = true;
        if (!$method->hasNode()) {
            return;
        }
        if ($method->getStaticPropertyModifications()) {
            return;
        }
        $method->analyze($method->getContext(), $this->code_base);
        $node = $method->getNode();
        if ($node instanceof Node) {
            BlockAnalysisVisitor::markMethodNodeAsAlreadyAnalyzed($node);
        }
    }

    private function canCallInstanceMethodFromContext(Method $method, string $static_class): bool
    {
        // Check if this is an instance method or closure of an instance method
        if (!$this->context->getScope()->hasVariableWithName('this')) {
            return false;
        }
        if (\in_array(\strtolower($static_class), ['parent', 'self', 'static'], true)) {
            return true;
        }
        $calling_class_fqsen = $this->context->getClassFQSENOrNull();
        if ($calling_class_fqsen) {
            $calling_class_type = $calling_class_fqsen->asType()->asExpandedTypes($this->code_base);
        } else {
            $calling_class_type = $this->context->getScope()->getVariableByName('this')->getUnionType()->asExpandedTypes($this->code_base);
        }
        // Allow calling its own methods and class's methods.
        return $calling_class_type->hasType($method->getClassFQSEN()->asType());
    }

    /**
     * Check calling A::__construct (where A is not parent)
     */
    private function checkNonAncestorConstructCall(
        Node $node,
        string $static_class,
        string $method_name
    ): void {
        // TODO: what about unanalyzable?
        if ($node->children['class']->kind !== ast\AST_NAME) {
            return;
        }
        // Only warn about self::__construct() recursion when called from within a constructor.
        // Calling self::__construct() from other methods is unusual but valid (e.g., for re-initialization).
        $is_in_constructor = false;
        if ($this->context->isInMethodScope()) {
            $method = $this->context->getFunctionLikeInScope($this->code_base);
            $is_in_constructor = $method instanceof Method && $method->isNewConstructor();
        }

        $found_ancestor_constructor = false;
        if ($this->context->isInMethodScope()) {
            try {
                $possible_ancestor_type = UnionTypeVisitor::unionTypeFromClassNode(
                    $this->code_base,
                    $this->context,
                    $node->children['class']
                );
            } catch (FQSENException $e) {
                $this->emitIssue(
                    $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInCallable : Issue::InvalidFQSENInCallable,
                    $node->lineno,
                    $e->getFQSEN()
                );
                return;
            }
            // If we can determine the ancestor type, and it's an parent/ancestor class, allow the call without warning.
            // (other code should check visibility and existence and args of __construct)

            if (!$possible_ancestor_type->isEmpty()) {
                // but forbid 'self::__construct', 'static::__construct' when called from __construct
                $type = $this->context->getClassFQSEN()->asType();
                if ($possible_ancestor_type->hasStaticType()) {
                    if ($is_in_constructor) {
                        $this->emitIssue(
                            Issue::AccessOwnConstructor,
                            $node->lineno,
                            $static_class
                        );
                    }
                    $found_ancestor_constructor = true;
                } elseif ($type->asPHPDocUnionType()->canCastToUnionType($possible_ancestor_type, $this->code_base)) {
                    if ($possible_ancestor_type->hasType($type)) {
                        if ($is_in_constructor) {
                            $this->emitIssue(
                                Issue::AccessOwnConstructor,
                                $node->lineno,
                                $static_class
                            );
                        }
                    }
                    $found_ancestor_constructor = true;
                }
            }
        }

        if (!$found_ancestor_constructor) {
            // TODO: new issue type?
            $this->emitConstructorWarning($node, $static_class, $method_name);
        }
    }

    /**
     * TODO: change to a different issue type in a future phan release?
     */
    private function emitConstructorWarning(Node $node, string $static_class, string $method_name): void
    {
        $this->emitIssue(
            Issue::UndeclaredStaticMethod,
            $node->lineno,
            "{$static_class}::{$method_name}()"
        );
    }

    /**
     * gets the static method, or emits an issue.
     * @param Node $node
     * @param string $method_name - NOTE: The caller should convert constants/class constants/etc in $node->children['method'] to a string.
     */
    private function getStaticMethodOrEmitIssue(Node $node, string $method_name): ?Method
    {
        try {
            $context_node = new ContextNode(
                $this->code_base,
                $this->context,
                $node
            );

            if (Config::get_track_references()) {
                $method_list = $context_node->getMethodList($method_name, true, true);
                if (!$method_list) {
                    $result = $context_node->getMethod($method_name, true, true);
                } else {
                    $result = $method_list[0];
                    $this->recordMethodReferences($method_list, $result);
                }
            } else {
                $result = $context_node->getMethod($method_name, true, true);
            }

            // This didn't throw NonClassMethodCall
            if (Config::get_strict_method_checking()) {
                $this->checkForPossibleNonObjectAndNonClassInMethod($node, $method_name);
            }

            return $result;
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (NullsafeShortCircuitException) {
            // The call is part of a chain that PHP short-circuits to null: it never happens, so it references no method.
            return null;
        } catch (Exception $e) {
            if ($e instanceof FQSENException) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    $e instanceof EmptyFQSENException ? Issue::EmptyFQSENInClasslike : Issue::InvalidFQSENInClasslike,
                    $node->lineno,
                    $e->getFQSEN()
                );
            }
            // We already checked for NonClassMethodCall
            if (Config::get_strict_method_checking()) {
                $this->checkForPossibleNonObjectAndNonClassInMethod($node, $method_name);
            }

            // If we can't figure out the class for this method
            // call, cry YOLO and mark every method with that
            // name with a reference.
            if (Config::get_track_references()
                && Config::getValue('dead_code_detection_prefer_false_negative')
            ) {
                foreach ($this->code_base->getMethodSetByName(
                    $method_name
                ) as $method) {
                    $method->addReference($this->context);
                }
            }

            // If we can't figure out what kind of a call
            // this is, don't worry about it
        }
        return null;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Node $node): Context
    {
        if (!$this->context->isInFunctionLikeScope()) {
            throw new AssertionError("Must be in function-like scope to get method");
        }

        $method = $this->context->getFunctionLikeInScope($this->code_base);
        if (($node->flags & (ast\flags\MODIFIER_FINAL | ast\flags\MODIFIER_PRIVATE)) === (ast\flags\MODIFIER_FINAL | ast\flags\MODIFIER_PRIVATE)) {
            // PHP 8.0+ only warns about private final methods when the method is NOT a constructor.
            // See https://www.php.net/manual/en/migration80.incompatible.php
            // See https://github.com/phan/phan/issues/4753
            if (!($method instanceof Method) || !$method->isNewConstructor()) {
                $this->emitIssue(
                    Issue::PrivateFinalMethod,
                    $node->lineno,
                    $method->getRepresentationForIssue()
                );
            }
        }

        $return_type = $method->getUnionType();

        if (!($method instanceof Method)) {
            throw new AssertionError("Function found where method expected");
        }

        $has_interface_class = false;
        try {
            $class = $method->getClass($this->code_base);
            $has_interface_class = $class->isInterface();
        } catch (Exception) {
        }

        if (!$method->isAbstract()
            && !$method->isFromPHPDoc()
            && !$has_interface_class
            && !$return_type->isEmpty()
            && !$method->hasReturn()
            && !$this->declNeverReturns($node)
            && !$return_type->hasType(VoidType::instance(false))
            && !$return_type->hasType(NullType::instance(false))
            && !$return_type->hasType(NeverType::instance(false))
        ) {
            $this->warnTypeMissingReturn($method, $node);
        }
        $this->checkForFunctionInterfaceIssues($node, $method);

        if ($method->hasReturn() && $method->isMagicAndVoid()) {
            $this->emitIssue(
                Issue::TypeMagicVoidWithReturn,
                $node->lineno,
                (string)$method->getFQSEN()
            );
        }

        return $this->context;
    }

    private function warnTypeMissingReturn(FunctionInterface $method, Node $node): void
    {
        $this->emitIssue(
            $method->getRealReturnType()->isEmpty() ? Issue::TypeMissingReturn : Issue::TypeMissingReturnReal,
            $node->lineno,
            $method->getFQSEN(),
            $method->getUnionType()
        );
    }
    /**
     * Visit a node with kind `ast\AST_FUNC_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFuncDecl(Node $node): Context
    {
        $function =
            $this->context->getFunctionLikeInScope($this->code_base);

        switch (strtolower($function->getName())) {
            case '__autoload':
                $this->emitIssue(
                    Issue::CompatibleAutoload,
                    $node->lineno
                );
                break;
            case 'assert':
                $this->emitIssue(
                    Issue::CompatibleAssertDeclaration,
                    $node->lineno
                );
                break;
        }

        $return_type = $function->getUnionType();

        if (!$return_type->isEmpty()
            && !$function->hasReturn()
            && !$this->declNeverReturns($node)
            && !$return_type->hasType(VoidType::instance(false))
            && !$return_type->hasType(NullType::instance(false))
        ) {
            $this->warnTypeMissingReturn($function, $node);
        }

        $this->checkForFunctionInterfaceIssues($node, $function);

        return $this->context;
    }

    /**
     * @suppress PhanPossiblyUndeclaredProperty
     */
    private function checkForFunctionInterfaceIssues(Node $node, FunctionInterface $function): void
    {
        $parameters_seen = [];
        foreach ($function->getParameterList() as $i => $parameter) {
            if (isset($parameters_seen[$parameter->getName()])) {
                $this->emitIssue(
                    Issue::ParamRedefined,
                    $node->lineno,
                    '$' . $parameter->getName()
                );
            } else {
                $parameters_seen[$parameter->getName()] = $i;
            }
        }
        $params_node = $node->children['params'];
        foreach ($params_node->children as $param) {
            $this->checkUnionTypeCompatibility($param->children['type']);
        }
        $this->checkUnionTypeCompatibility($node->children['returnType']);
    }

    private function checkUnionTypeCompatibility(?Node $type, bool $is_union = false): void
    {
        if (!$type) {
            return;
        }

        if ($type->kind === ast\AST_TYPE_INTERSECTION) {
            foreach ($type->children as $node) {
                $this->checkUnionTypeCompatibility($node);
            }
            return;
        }
        if ($type->kind === ast\AST_TYPE_UNION) {
            foreach ($type->children as $node) {
                $this->checkUnionTypeCompatibility($node, true);
            }
            return;
        }
        if ($type->kind === ast\AST_NULLABLE_TYPE) {
            $inner_type = $type->children['type'];
            if (!\is_object($inner_type)) {
                // The polyfill will create param type nodes for function(? $x)
                // Phan warns elsewhere.
                return;
            }
        } else {
            $inner_type = $type;
        }
        // echo \Phan\Debug::nodeToString($type) . "\n";
        if ($inner_type->kind === ast\AST_NAME) {
            return;
        }
        if ($inner_type->kind !== ast\AST_TYPE) {
            // e.g. ast\TYPE_UNION
            $this->emitIssue(
                Issue::InvalidNode,
                $inner_type->lineno,
                "Unsupported union type syntax " . ASTReverter::toShortString($inner_type)
            );
            return;
        }

        $minimum_target_php_version_id = Config::get_closest_minimum_target_php_version_id();
        if ($inner_type->flags === ast\flags\TYPE_TRUE && $minimum_target_php_version_id < 80200) {
            $this->emitIssue(
                Issue::CompatibleTrueType,
                $inner_type->lineno,
                'true'
            );
        } elseif (
            !$is_union &&
            $minimum_target_php_version_id < 80200 &&
            \in_array($inner_type->flags, [ast\flags\TYPE_NULL, ast\flags\TYPE_FALSE], true)
        ) {
            $this->emitIssue(
                Issue::CompatibleStandaloneType,
                $inner_type->lineno,
                ASTReverter::toShortTypeString( $type )
            );
        }
    }

    public function visitNullsafeMethodCall(Node $node): Context
    {
        return $this->visitMethodCall($node);
    }


    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $node): Context
    {
        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            if ($method_name instanceof Node) {
                $method_name = UnionTypeVisitor::anyStringLiteralForNode($this->code_base, $this->context, $method_name);
            }
            if (!\is_string($method_name)) {
                $method_name_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['method']);
                if (!$method_name_type->canCastToUnionType(StringType::instance(false)->asPHPDocUnionType(), $this->code_base)) {
                    Issue::maybeEmit(
                        $this->code_base,
                        $this->context,
                        Issue::TypeInvalidMethodName,
                        $node->lineno,
                        $method_name_type
                    );
                }
                return $this->context;
            }
        }

        try {
            $context_node = new ContextNode(
                $this->code_base,
                $this->context,
                $node
            );

            if (Config::get_track_references()) {
                $method_list = $context_node->getMethodList($method_name, false, true);
                if (!$method_list) {
                    // Fallback to throw the same exception logic as getMethod when list is empty
                    $method = $context_node->getMethod($method_name, false, true);
                } else {
                    $method = $method_list[0];
                    $this->recordMethodReferences($method_list, $method);
                }
            } else {
                $method = $context_node->getMethod($method_name, false, true);
            }
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return $this->context;
        } catch (NullsafeShortCircuitException) {
            // The call is part of a chain that PHP short-circuits to null: it never happens, so it references no method.
            return $this->context;
        } catch (NodeException) {
            // If we can't figure out the class for this method
            // call, cry YOLO and mark every method with that
            // name with a reference.
            if (Config::get_track_references()
                && Config::getValue('dead_code_detection_prefer_false_negative')
            ) {
                foreach ($this->code_base->getMethodSetByName(
                    $method_name
                ) as $method) {
                    $method->addReference($this->context);
                }
            }

            // Swallow it
            return $this->context;
        }

        // We already checked for NonClassMethodCall
        if (Config::get_strict_method_checking()) {
            $this->checkForPossibleNonObjectInMethod($node, $method_name);
        }

        $this->analyzeMethodVisibility(
            $method,
            $node,
            false
        );

        // Check the call for parameter and argument types
        $this->analyzeCallToFunctionLike(
            $method,
            $node
        );

        // Check if method has #[NoDiscard] attribute and return value is ignored
        $this->checkNoDiscardAttribute($node, $method);

        return $this->context;
    }

    // No need to analyze AST_CALLABLE_CONVERT

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitArgList(Node $node): Context
    {
        $argument_name_set = [];
        $has_unpack = false;

        foreach ($node->children as $i => $argument) {
            if (!\is_int($i)) {
                throw new AssertionError("Expected argument index to be an integer");
            }
            if ($argument instanceof Node && $argument->kind === ast\AST_NAMED_ARG) {
                ['name' => $argument_name, 'expr' => $argument_expression] = $argument->children;
                if ($argument_expression === null) {
                    throw new AssertionError("Expected argument to have an expression");
                }
                if (isset($argument_name_set[$argument_name])) {
                    $this->emitIssue(
                        Issue::DefinitelyDuplicateNamedArgument,
                        $argument->lineno,
                        ASTReverter::toShortString($argument),
                        ASTReverter::toShortString($argument_name_set[$argument_name])
                    );
                } else {
                    $argument_name_set[$argument_name] = $argument;
                }
            } else {
                $argument_expression = $argument;
            }
            if ($argument_name_set) {
                // `...` of partial function application is the last argument, and is allowed after named arguments.
                if ($argument === $argument_expression && !PartialApplication::isVariadicPlaceholder($argument)) {
                    $this->emitIssue(
                        Issue::PositionalArgumentAfterNamedArgument,
                        $argument->lineno ?? $node->lineno,
                        ASTReverter::toShortString($argument),
                        ASTReverter::toShortString(\end($argument_name_set))
                    );
                }
            }


            if (($argument->kind ?? 0) === ast\AST_UNPACK) {
                $has_unpack = true;
            }
        }
        // TODO: Make this a check that runs even without the $method object
        if ($has_unpack && $argument_name_set) {
            $this->emitIssue(
                Issue::ArgumentUnpackingUsedWithNamedArgument,
                $node->lineno,
                ASTReverter::toShortString($node)
            );
        }
        return $this->context;
    }

    private function checkForPossibleNonObjectInMethod(Node $node, string $method_name): void
    {
        $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr'] ?? $node->children['class']);
        if ($node->kind === ast\AST_NULLSAFE_METHOD_CALL && !$type->isNull() && !$type->isDefinitelyUndefined()) {
            $type = $type->nonNullableClone();
        }
        if ($type->containsDefiniteNonObjectType()) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::PossiblyNonClassMethodCall,
                $node->lineno,
                $method_name,
                $type
            );
        }
    }

    private function checkForPossibleNonObjectAndNonClassInMethod(Node $node, string $method_name): void
    {
        $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr'] ?? $node->children['class']);
        if ($type->containsDefiniteNonObjectAndNonClassType()) {
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::PossiblyNonClassMethodCall,
                $node->lineno,
                $method_name,
                $type
            );
        }
    }

    /**
     * Visit a node with kind `ast\AST_DIM`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDim(Node $node): Context
    {
        $code_base = $this->code_base;
        $context = $this->context;
        // Check the dimension type to trigger PhanUndeclaredVariable, etc.
        /* $dim_type = */
        UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['dim'],
            true
        );
        $this->analyzeNoOp($node, Issue::NoopArrayAccess);

        $flags = $node->flags;
        if ($flags & ast\flags\DIM_ALTERNATIVE_SYNTAX) {
            $this->emitIssue(
                Issue::CompatibleDimAlternativeSyntax,
                $node->children['dim']->lineno ?? $node->lineno,
                ASTReverter::toShortString($node)
            );
        }
        if ($flags & PhanAnnotationAdder::FLAG_IGNORE_NULLABLE_AND_UNDEF) {
            return $context;
        }
        $parent_node = \end($this->parent_node_list);
        $parent_kind = $parent_node->kind ?? null;
        if ($parent_kind === ast\AST_DIM && $parent_node instanceof Node && $parent_node->children['expr'] === $node) {
            // `$var[...][...]`: the outermost dimension checks the chain, since its parent decides whether `$var` is read
            // (`byref($var[0][1])` and `$x = &$var[0][1]` create `$var`).
        } elseif ($parent_kind === ast\AST_RETURN && $this->isInFunctionReturningReference()) {
            // `return $var['k'];` in a function returning a reference creates `$var` and the offset: see analyzeReturnsReference()
        } elseif (!isset(self::SKIP_DIM_VAR_CHECK_TYPES[$parent_kind])) {
            $this->checkForPossiblyUndeclaredVariableOfDimRead($node);
        } elseif ($parent_kind !== ast\AST_ASSIGN_REF) {
            // An argument: analyzeCallToFunctionLike() decides once it knows whether the parameter is by reference,
            // but with the scope of this point, since a later argument can assign the variable (`f($var[0], $var = [])`).
            // @phan-suppress-next-line PhanUndeclaredProperty this is added for analyzeCallToFunctionLike()
            $node->possibly_undeclared_variable_issue = $this->getIssueTypeForPossiblyUndeclaredVariableOfDimRead($node);
        }
        // Check the array type to trigger TypeArraySuspicious
        try {
            /* $array_type = */
            UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node,
                false
            );
            // TODO: check if array_type has array but not ArrayAccess.
            // If that is true, then assert that $dim_type can cast to `int|string`
        } catch (IssueException) {
            // Detect this elsewhere, e.g. want to detect PhanUndeclaredVariableDim but not PhanUndeclaredVariable
        }
        return $context;
    }

    /**
     * Visit a node with kind `ast\AST_CONDITIONAL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitConditional(Node $node): Context
    {
        if ($this->isInNoOpPosition($node)) {
            if (!ScopeImpactCheckingVisitor::hasPossibleImpact($this->code_base, $this->context, $node->children['true']) &&
                !ScopeImpactCheckingVisitor::hasPossibleImpact($this->code_base, $this->context, $node->children['false'])) {
                $this->emitIssue(
                    Issue::NoopTernary,
                    $node->lineno
                );
            }
        }
        $cond = $node->children['cond'];
        if ($cond instanceof Node && $cond->kind === ast\AST_CONDITIONAL) {
            $this->checkDeprecatedUnparenthesizedConditional($node, $cond);
        }
        return $this->context;
    }

    /**
     * Visit a node with kind `ast\AST_MATCH`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * analyzing the node
     */
    public function visitMatch(Node $node): Context
    {
        if ($this->isInNoOpPosition($node)) {
            if (!ScopeImpactCheckingVisitor::hasPossibleImpact($this->code_base, $this->context, $node->children['stmts'])) {
                $this->emitIssue(
                    Issue::NoopMatchExpression,
                    $node->lineno,
                    ASTReverter::toShortString($node)
                );
            }
        }
        return $this->context;
    }

    /**
     * @param Node $node a node of kind AST_CONDITIONAL with a condition that is also of kind AST_CONDITIONAL
     */
    private function checkDeprecatedUnparenthesizedConditional(Node $node, Node $cond): void
    {
        if ($cond->flags & flags\PARENTHESIZED_CONDITIONAL) {
            // The condition is unambiguously parenthesized.
            return;
        }
        if (isset($cond->children['true'])) {
            if (isset($node->children['true'])) {
                $description = 'a ? b : c ? d : e';
                $first_suggestion = '(a ? b : c) ? d : e';
                $second_suggestion = 'a ? b : (c ? d : e)';
            } else {
                $description = 'a ? b : c ?: d';
                $first_suggestion = '(a ? b : c) ?: d';
                $second_suggestion = 'a ? b : (c ?: d)';
            }
        } else {
            if (isset($node->children['true'])) {
                $description = 'a ?: b ? c : d';
                $first_suggestion = '(a ?: b) ? c : d';
                $second_suggestion = 'a ?: (b ? c : d)';
            } else {
                // This is harmless - (a ?: b) ?: c always produces the same result and side
                // effects as a ?: (b ?: c).
                // Don't warn.
                return;
            }
        }
        $this->emitIssue(
            Issue::CompatibleUnparenthesizedTernary,
            $node->lineno,
            $description,
            $first_suggestion,
            $second_suggestion
        );
    }

    /**
     * @param list<Node> $parent_node_list
     * @return bool true if the union type should skip analysis due to being the left-hand side expression of an assignment
     * We skip checks for $x['key'] being valid in expressions such as `$x['key']['key2']['key3'] = 'value';`
     * because those expressions will create $x['key'] as a side effect.
     *
     * Precondition: $parent_node->kind === ast\AST_DIM && $parent_node->children['expr'] is $node
     */
    private static function shouldSkipNestedAssignDim(array $parent_node_list): bool
    {
        $cur_parent_node = \end($parent_node_list);
        for (;; $cur_parent_node = $prev_parent_node) {
            $prev_parent_node = \prev($parent_node_list);
            if (!$prev_parent_node instanceof Node) {
                throw new AssertionError('Unexpected end of parent nodes seen in ' . __METHOD__);
            }
            switch ($prev_parent_node->kind) {
                case ast\AST_DIM:
                    if ($prev_parent_node->children['expr'] !== $cur_parent_node) {
                        return false;
                    }
                    break;
                case ast\AST_ASSIGN:
                case ast\AST_ASSIGN_REF:
                    return $prev_parent_node->children['var'] === $cur_parent_node;
                case ast\AST_ARRAY_ELEM:
                    $prev_parent_node = \prev($parent_node_list);  // this becomes AST_ARRAY
                    break;
                case ast\AST_ARRAY:
                    break;
                default:
                    return false;
            }
        }
    }

    /**
     * Check if the AST_DIM node at the end of $parent_node_list (whose expression is the node being analyzed)
     * is itself being written to, through zero or more enclosing dimensions or array destructuring elements:
     * the target of `=`, `=&`, a compound assignment such as `??=` or `.=`, `++`/`--`, or `unset()`.
     *
     * Used for write tracking: `$this->prop['key'] = ...`, `[$this->prop['key']] = ...`, `$this->prop['key'] .= ...`,
     * `$this->prop['key']++` and `unset($this->prop['key'])` all modify the property.
     *
     * @param list<Node> $parent_node_list
     */
    private static function isNestedDimModification(array $parent_node_list): bool
    {
        $cur_parent_node = \end($parent_node_list);
        for (;; $cur_parent_node = $prev_parent_node) {
            $prev_parent_node = \prev($parent_node_list);
            if (!$prev_parent_node instanceof Node) {
                return false;
            }
            switch ($prev_parent_node->kind) {
                case ast\AST_DIM:
                    if ($prev_parent_node->children['expr'] !== $cur_parent_node) {
                        return false;
                    }
                    break;
                case ast\AST_ASSIGN:
                case ast\AST_ASSIGN_REF:
                case ast\AST_ASSIGN_OP:
                    return $prev_parent_node->children['var'] === $cur_parent_node;
                case ast\AST_ARRAY_ELEM:
                    if ($prev_parent_node->children['value'] !== $cur_parent_node) {
                        // e.g. `[$this->prop['key'] => $v] = ...` reads the key
                        return false;
                    }
                    $prev_parent_node = \prev($parent_node_list);  // this becomes AST_ARRAY
                    break;
                case ast\AST_UNSET:
                case ast\AST_PRE_INC:
                case ast\AST_PRE_DEC:
                case ast\AST_POST_INC:
                case ast\AST_POST_DEC:
                    return true;
                default:
                    return false;
            }
        }
    }

    public function visitStaticProp(Node $node): Context
    {
        return $this->analyzeProp($node, true);
    }

    public function visitProp(Node $node): Context
    {
        return $this->analyzeProp($node, false);
    }

    public function visitNullsafeProp(Node $node): Context
    {
        return $this->analyzeProp($node, false);
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClone(Node $node): Context
    {
        $type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            true
        );
        if ($type->isEmpty()) {
            return $this->context;
        }
        if (!$type->hasPossiblyObjectTypes()) {
            $this->emitIssue(
                Issue::TypeInvalidCloneNotObject,
                $node->children['expr']->lineno ?? $node->lineno,
                $type
            );
            return $this->context;
        } elseif (Config::get_strict_param_checking()) {
            if ($type->containsNullable() || !$type->canStrictCastToUnionType($this->code_base, ObjectType::instance(false)->asPHPDocUnionType())) {
                $this->emitIssue(
                    Issue::TypePossiblyInvalidCloneNotObject,
                    $node->children['expr']->lineno ?? $node->lineno,
                    $type
                );
            }
        }
        foreach ($type->getUniqueFlattenedTypeSet() as $type_part) {
            if (!$type_part->isObjectWithKnownFQSEN()) {
                continue;
            }
            // Surprisingly, many types in php can be cloned, even closures
            if ($this->isTypeEnum($type_part)) {
                $this->emitIssue(Issue::TypeInstantiateEnum, $node->lineno, $type_part);
            }
        }
        if (isset($node->children['withProperties'])) {
            $this->analyzeCloneWithProperties($node, $node->children['withProperties']);
        }

        return $this->context;
    }

    /**
     * Analyzes the second argument of `clone($obj, ['prop' => $value])` (php 8.5+):
     * php assigns each value to the property of the new object, from the scope of the caller.
     *
     * @param Node $node a node of kind AST_CLONE
     * @param Node|float|int|string|null $with_properties the argument `withProperties`
     */
    private function analyzeCloneWithProperties(Node $node, mixed $with_properties): void
    {
        $with_properties_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $with_properties, true);
        // (A type that may be an array is accepted: `iterable`, `callable`, `?array`)
        if (!$with_properties_type->isEmpty() && !$with_properties_type->hasAnyTypeOverlap($this->code_base, ArrayType::instance(false)->asPHPDocUnionType())) {
            $this->emitIssue(
                Issue::TypeMismatchArgumentInternal,
                $with_properties->lineno ?? $node->lineno,
                2,
                'withProperties',
                ASTReverter::toShortString($with_properties),
                $with_properties_type,
                '\\clone()',
                'array'
            );
            return;
        }
        if (!($with_properties instanceof Node) || $with_properties->kind !== ast\AST_ARRAY) {
            return;
        }
        // The object that the properties are assigned to is the clone, which has the type of the object.
        // (This uses the node itself and not a copy without the properties: Phan caches the classes of a node by the id of the node,
        // and the id of a temporary node can be used again in the same statement.)
        $clone_node = $node;
        // The entries that php assigns: a later entry with the same key replaces an earlier one,
        // and so may `...$properties` or an entry with a key that is not known.
        $elem_for_key = [];
        foreach ($with_properties->children as $elem) {
            if (!($elem instanceof Node) || $elem->kind !== ast\AST_ARRAY_ELEM) {
                $elem_for_key = [];
                continue;
            }
            $key = $elem->children['key'];
            if ($key instanceof Node) {
                $key = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key)->asSingleScalarValueOrNull();
                if ($key === null) {
                    $elem_for_key = [];
                    continue;
                }
            }
            if (\is_string($key)) {
                $elem_for_key[$key] = $elem;
            }
        }
        foreach ($elem_for_key as $key => $elem) {
            // (php converts a key that is a numeric string to an int)
            $key = (string)$key;
            $value = $elem->children['value'];
            $prop_node = new Node(ast\AST_PROP, 0, ['expr' => $clone_node, 'prop' => $key], $elem->lineno);
            $assign_node = new Node(ast\AST_ASSIGN, 0, ['var' => $prop_node, 'expr' => $value], $elem->lineno);
            (new AssignmentVisitor(
                $this->code_base,
                $this->context->withLineNumberStart($elem->lineno),
                $assign_node,
                UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $value),
                0,
                null,
                false,
                false,
                false,
                true
            ))->__invoke($prop_node);
            $this->trackWriteReferenceOfClonedProperty($node->children['expr'], $key);
        }
    }

    /**
     * Records that `clone($obj, ['prop' => $value])` writes to the property, for dead code detection.
     *
     * @param Node|float|int|string|null $expr the object that is cloned
     */
    private function trackWriteReferenceOfClonedProperty(mixed $expr, string $property_name): void
    {
        if (!($expr instanceof Node) || !Config::get_track_references()) {
            return;
        }
        try {
            $class_list = (new ContextNode($this->code_base, $this->context, $expr))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT);
        } catch (Exception) {
            return;
        }
        foreach ($class_list as $class) {
            if ($class->hasPropertyWithName($this->code_base, $property_name)) {
                $property = $class->getPropertyByName($this->code_base, $property_name);
                $property->addReference($this->context);
                $property->setHasWriteReference();
            }
        }
    }

    private function isTypeEnum(Type $type): bool
    {
        if (!$type->isObjectWithKnownFQSEN()) {
            return false;
        }

        $fqsen = $type->asFQSEN();
        // The only thing that can implement the UnitEnum/BackedEnum interfaces in php 8.1+ are enums.
        if (\in_array(strtolower($fqsen->__toString()), ['\backedenum', '\unitenum'], true)) {
            return true;
        }
        if (!$fqsen instanceof FullyQualifiedClassName || !$this->code_base->hasClassWithFQSEN($fqsen)) {
            return false;
        }
        $class = $this->code_base->getClassByFQSEN($fqsen);
        return $class->isEnum();
    }

    /**
     * Analyze a node with kind `ast\AST_PROP` or `ast\AST_STATIC_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @param bool $is_static
     * True if fetching a static property.
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function analyzeProp(Node $node, bool $is_static): Context
    {
        $exception_or_null = null;

        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getProperty($is_static);

            // Mark that this property has been referenced from
            // this context
            if (Config::get_track_references()) {
                $this->trackPropertyReference($property, $node);
            }
        } catch (IssueException $exception) {
            // We'll check out some reasons it might not exist
            // before logging the issue
            $exception_or_null = $exception;
        } catch (Exception) {
            // Swallow any exceptions. We'll catch it later.
        }

        if (isset($property)) {
            // TODO could be more specific about checking if this is a magic property
            // Right now it warns if it is magic but (at)property is used, etc.
            $this->analyzeNoOp($node, Issue::NoopProperty);
        } else {
            $expr_or_class_node = $node->children['expr'] ?? $node->children['class'];
            if ($expr_or_class_node === null) {
                throw new AssertionError(
                    "Property nodes must either have an expression or class"
                );
            }

            $class_list = [];
            try {
                // Get the set of classes that are being referenced
                $class_list = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $expr_or_class_node
                ))->getClassList(
                    true,
                    $is_static ? ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME : ContextNode::CLASS_LIST_ACCEPT_OBJECT,
                    null,
                    false
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
            }

            if (!$is_static) {
                // Find out of any of them have a __get magic method
                // (Only check if looking for instance properties)
                $has_getter = $this->hasGetter($class_list);

                // If they don't, then analyze for No-ops.
                if (!$has_getter) {
                    $this->analyzeNoOp($node, Issue::NoopProperty);

                    if ($exception_or_null instanceof IssueException) {
                        Issue::maybeEmitInstance(
                            $this->code_base,
                            $this->context,
                            $exception_or_null->getIssueInstance()
                        );
                    }
                }
            } else {
                if ($exception_or_null instanceof IssueException) {
                    Issue::maybeEmitInstance(
                        $this->code_base,
                        $this->context,
                        $exception_or_null->getIssueInstance()
                    );
                }
            }
        }

        return $this->context;
    }

    /** @param Clazz[] $class_list */
    private function hasGetter(array $class_list): bool
    {
        foreach ($class_list as $class) {
            if ($class->hasGetMethod($this->code_base)) {
                return true;
            }
        }
        return false;
    }

    private function trackPropertyReference(Property $property, Node $node): void
    {
        $property->addReference($this->context);
        if (!$property->hasReadReference() && !$this->isAssignmentOrNestedAssignment($node)) {
            $property->setHasReadReference();
        }
        if (!$property->hasWriteReference() && $this->isAssignmentOrNestedAssignmentOrModification($node) !== false) {
            $property->setHasWriteReference();
        }
    }

    /**
     * @return ?bool
     * - false if this is a read reference
     * - false for modifications such as $x++ or $x += 1 if the assignment operation result is used
     * - true for modifications such as $x++ or $x += 1 if the assignment operation result is not used
     * - true if this is a write reference
     * - null if this is both, e.g. $a =& $b for $a and $b
     */
    private function isAssignmentOrNestedAssignment(Node $node): ?bool
    {
        $parent_node_list = $this->parent_node_list;
        $parent_node = \end($parent_node_list);
        if (!$parent_node instanceof Node) {
            // impossible
            return false;
        }
        $parent_kind = $parent_node->kind;
        // E.g. analyzing [$x] in [$x] = expr()
        while ($parent_kind === ast\AST_ARRAY_ELEM) {
            if ($parent_node->children['value'] !== $node) {
                // e.g. analyzing `$v = [$x => $y];` for $x
                return false;
            }
            \array_pop($parent_node_list);  // pop AST_ARRAY_ELEM
            $node = \array_pop($parent_node_list);  // AST_ARRAY
            $parent_node = \array_pop($parent_node_list);
            if (!$parent_node instanceof Node) {
                // impossible
                return false;
            }
            $parent_kind = $parent_node->kind;
        }
        if ($parent_kind === ast\AST_DIM) {
            return $parent_node->children['expr'] === $node && $this->shouldSkipNestedAssignDim($parent_node_list);
        } elseif ($parent_kind === ast\AST_ASSIGN) {
            return $parent_node->children['var'] === $node;
        } elseif ($parent_kind === ast\AST_ASSIGN_OP) {
            if ($parent_node->children['var'] !== $node) {
                return false;
            }
            \array_pop($parent_node_list);
            return self::isInNoOpPositionForList($parent_node, $parent_node_list);
        } elseif ($parent_kind === ast\AST_ASSIGN_REF) {
            return null;
        } elseif (\in_array($parent_kind, self::READ_AND_WRITE_KINDS, true)) {
            \array_pop($parent_node_list);
            return self::isInNoOpPositionForList($parent_node, $parent_node_list);
        }
        return false;
    }

    // An incomplete list of known parent node kinds that simultaneously read and write the given expression
    // TODO: ASSIGN_OP?
    private const READ_AND_WRITE_KINDS = [
        ast\AST_PRE_INC,
        ast\AST_PRE_DEC,
        ast\AST_POST_INC,
        ast\AST_POST_DEC,
    ];

    /**
     * @return ?bool
     * - false if this is a read reference
     * - true if this is a write reference
     * - true if this is a modification such as $x++, $x['k']++ or unset($x['k'])
     * - null if this is both, e.g. $a =& $b for $a and $b
     */
    private function isAssignmentOrNestedAssignmentOrModification(Node $node): ?bool
    {
        $parent_node_list = $this->parent_node_list;
        $parent_node = \end($parent_node_list);
        if (!$parent_node instanceof Node) {
            // impossible
            return false;
        }
        $parent_kind = $parent_node->kind;
        // E.g. analyzing [$x] in [$x] = expr()
        while ($parent_kind === ast\AST_ARRAY_ELEM) {
            if ($parent_node->children['value'] !== $node) {
                // e.g. analyzing `$v = [$x => $y];` for $x
                return false;
            }
            \array_pop($parent_node_list);  // pop AST_ARRAY_ELEM
            $node = \array_pop($parent_node_list);  // AST_ARRAY
            $parent_node = \array_pop($parent_node_list);
            if (!$parent_node instanceof Node) {
                // impossible
                return false;
            }
            $parent_kind = $parent_node->kind;
        }
        if ($parent_kind === ast\AST_DIM) {
            if ($parent_node->children['expr'] !== $node) {
                return false;
            }
            return self::isNestedDimModification($parent_node_list);
        } elseif ($parent_kind === ast\AST_ASSIGN || $parent_kind === ast\AST_ASSIGN_OP) {
            return $parent_node->children['var'] === $node;
        } elseif ($parent_kind === ast\AST_ASSIGN_REF) {
            return null;
        } elseif ($parent_kind === ast\AST_UNSET) {
            // unset($this->prop) removes the property
            return true;
        } else {
            return \in_array($parent_kind, self::READ_AND_WRITE_KINDS, true);
        }
    }

    /**
     * Analyze whether a method is callable
     */
    private function analyzeMethodVisibility(
        Method $method,
        Node $node,
        bool $is_static_call
    ): void {
        if ($is_static_call && $method->isStatic()) {
            $class_node = $node->children['class'] ?? null;
            if (!self::isStaticNameNode($class_node, true)) {
                $class_fqsen = $method->getFQSEN()->getFullyQualifiedClassName();
                if ($this->code_base->hasClassWithFQSEN($class_fqsen) && $this->code_base->getClassByFQSEN($class_fqsen)->isTrait()) {
                    $this->emitIssue(
                        Issue::CompatibleAccessMethodOnTraitDefinition,
                        $node->lineno,
                        (string)$method->getFQSEN(),
                        ASTReverter::toShortString($node)
                    );
                }
            }
        }
        if ($method->isPublic()) {
            return;
        }
        if ($method->isAccessibleFromClass($this->code_base, $this->context->getClassFQSENOrNull())) {
            return;
        }
        if ($method->isPrivate()) {
            $has_call_magic_method = !$method->isStatic()
                && $method->getDefiningClass($this->code_base)->hasMethodWithName($this->code_base, '__call', true);

            $this->emitIssue(
                $has_call_magic_method ?
                    Issue::AccessMethodPrivateWithCallMagicMethod : Issue::AccessMethodPrivate,
                $node->lineno,
                (string)$method->getFQSEN(),
                $method->getFileRef()->getFile(),
                (string)$method->getFileRef()->getLineNumberStart()
            );
        } else {
            if (Clazz::isAccessToElementOfThis($node)) {
                return;
            }
            $has_call_magic_method = !$method->isStatic()
                && $method->getDefiningClass($this->code_base)->hasMethodWithName($this->code_base, '__call', true);

            $this->emitIssue(
                $has_call_magic_method ?
                    Issue::AccessMethodProtectedWithCallMagicMethod : Issue::AccessMethodProtected,
                $node->lineno,
                (string)$method->getFQSEN(),
                $method->getFileRef()->getFile(),
                (string)$method->getFileRef()->getLineNumberStart()
            );
        }
    }

    /**
     * @param Node $node `parent::$prop::get()` or `parent::$prop::set($value)` in the hook of the same name of `$prop`
     */
    private function analyzeParentPropertyHookCall(Node $node): void
    {
        // Check the arguments, e.g. for undeclared variables
        $argument_types = [];
        foreach ($node->children['args']->children ?? [] as $arg) {
            $argument_types[] = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $arg);
        }
        $class = $this->context->getClassInScope($this->code_base);
        if (!$class->hasParentType()) {
            if (!$class->isTrait()) {
                $this->emitIssue(
                    Issue::ParentlessClass,
                    $node->lineno,
                    (string)$class->getFQSEN()
                );
            }
            return;
        }
        $property = ParentPropertyHookCall::getParentProperty($this->code_base, $this->context, $node);
        if (!$property) {
            if ($this->code_base->hasClassWithFQSEN($class->getParentClassFQSEN())) {
                $this->emitIssue(
                    Issue::UndeclaredProperty,
                    $node->lineno,
                    $class->getParentClassFQSEN() . '->' . $node->children['class']->children['prop']
                );
            }
            return;
        }
        if ($property->isPrivate()) {
            $this->emitIssue(
                Issue::AccessPropertyPrivate,
                $node->lineno,
                $property->asPropertyFQSENString(),
                $property->getContext()->getFile(),
                $property->getContext()->getLineNumberStart()
            );
            return;
        }
        $property->addReference($this->context);
        if (\strcasecmp((string)$node->children['method'], 'get') === 0) {
            $property->setHasReadReference();
        } else {
            $property->setHasWriteReference();
            if (\count($argument_types) === 1) {
                $this->checkParentSetHookArgument($node, $property, $argument_types[0]);
            }
        }
    }

    /**
     * Check the argument of `parent::$prop::set($value)` against the type that the parent's property accepts.
     *
     * @param Node $node a node of kind ast\AST_STATIC_CALL for which ParentPropertyHookCall::isParentHookCall() is true
     */
    private function checkParentSetHookArgument(Node $node, Property $property, UnionType $argument_type): void
    {
        $argument_node = $node->children['args']->children[0];
        if ($argument_node instanceof Node && \in_array($argument_node->kind, [ast\AST_UNPACK, ast\AST_NAMED_ARG], true)) {
            return;
        }
        $expected_type = ParentPropertyHookCall::getSetParameterType($this->code_base, $this->context, $property);
        if ($argument_type->isEmpty() || $expected_type->isEmpty()) {
            return;
        }
        if ($argument_type->asExpandedTypes($this->code_base)->canCastToUnionType($expected_type, $this->code_base)) {
            return;
        }
        $set_hook = $property->getSetHook();
        $parameter = $set_hook ? ($set_hook->getParameterList()[0] ?? null) : null;
        $declaration_context = $set_hook ? $set_hook->getContext() : $property->getContext();
        $this->emitIssue(
            Issue::TypeMismatchArgument,
            $node->lineno,
            1,
            $parameter ? $parameter->getName() : 'value',
            ASTReverter::toShortString($argument_node),
            $argument_type->toErrorMessageString(),
            $property->getRealDefiningFQSEN()->getFullyQualifiedClassName() . '::$' . $property->getName() . '::set()',
            $expected_type->toErrorMessageString(),
            $declaration_context->getFile(),
            $declaration_context->getLineNumberStart()
        );
    }

    /**
     * @param Node $node a node of kind ast\AST_CALLABLE_CONVERT:
     * the arguments of first-class callable syntax (`f(...)`) or of partial function application (`f(1, ?)`, php 8.6+)
     * @override
     */
    public function visitCallableConvert(Node $node): Context
    {
        if (Config::get_closest_minimum_target_php_version_id() < 80600 && PartialApplication::isPartialApplication($node)) {
            $this->emitIssue(
                Issue::CompatiblePartialApplication,
                $node->lineno,
                ASTReverter::toShortString($node)
            );
        }
        return $this->context;
    }

    /**
     * Analyze the parameters and arguments for a call
     * to the given method or function
     */
    private function analyzeCallToFunctionLike(
        FunctionInterface $method,
        Node $node
    ): void {
        $code_base = $this->code_base;
        $context = $this->context;

        $method->addReference($context);

        $args_node = $node->children['args'];
        if ($args_node->kind === ast\AST_CALLABLE_CONVERT) {
            if (PartialApplication::isPartialApplication($args_node)) {
                // Partial function application (php 8.6+): check the arguments that are bound to values.
                // The function is not called here, but the closure binds the arguments of parameters passed by reference
                // by reference, which creates the variables that do not exist yet: `$p = f(?, $out);` declares $out.
                $this->createReferencesAndCheckDimArguments($method, PartialApplication::getArgs($args_node));
                ArgumentType::analyzePartialApplication($method, $node, $context, $code_base);
                $this->addReferenceTypesOfPartialApplication($method, $args_node);
            }
            return;
        }
        $argument_list = $args_node->children;
        $this->createReferencesAndCheckDimArguments($method, $argument_list);

        // Confirm the argument types are clean
        ArgumentType::analyze(
            $method,
            $node,
            $context,
            $code_base
        );

        foreach ($argument_list as $i => $argument) {
            if (!$argument instanceof Node) {
                continue;
            }
            if (!$method->getParameterForCaller($i)) {
                continue;
            }

            $kind = $argument->kind;
            if ($kind === ast\AST_CLOSURE || $kind === ast\AST_ARROW_FUNC) {
                if (Config::get_track_references()) {
                    $this->trackReferenceToClosure($argument);
                }
            } elseif ($kind === ast\AST_ARRAY) {
                // When dead_code_detection_prefer_false_negative is true (default),
                // mark closures inside arrays passed as function arguments as referenced
                // to avoid false positives (fixes #5389)
                if (Config::get_track_references() && Config::getValue('dead_code_detection_prefer_false_negative')) {
                    $this->trackReferencesToClosuresInArray($argument);
                }
            }
        }

        // Take another pass over pass-by-reference parameters
        // and assign types to passed in variables.
        // (Named arguments are put at the positions of their parameters)
        $normalized_argument_list = ArgumentType::normalizeNamedArgs($argument_list, $method);
        foreach ($normalized_argument_list as $i => $argument) {
            if (!$argument instanceof Node || $argument->kind === ast\AST_NAMED_ARG) {
                // A named argument left here has no parameter of that name
                continue;
            }
            $parameter = $method->getParameterForCaller($i);

            // If the parameter is pass-by-reference and we're
            // passing a variable in, see if we should pass
            // the parameter and variable types to each other
            if ($parameter && $parameter->isPassByReference()) {
                self::analyzePassByReferenceArgument(
                    $code_base,
                    $context,
                    $argument,
                    $normalized_argument_list,
                    $method,
                    $parameter,
                    $method->getRealParameterForCaller($i),
                    $i
                );
            }
        }

        // If we're in quick mode, don't retest methods based on
        // parameter types passed in
        if (Config::get_quick_mode()) {
            return;
        }

        // Don't re-analyze recursive methods. That doesn't go
        // well.
        if ($context->isInFunctionLikeScope()
            && $method->getFQSEN() === $context->getFunctionLikeFQSEN()
        ) {
            $this->checkForInfiniteRecursion($node, $method);
            return;
        }

        if (!$method->needsRecursiveAnalysis()) {
            return;
        }

        // Re-analyze the method with the types of the arguments
        // being passed in.
        $this->analyzeMethodWithArgumentTypes(
            $node->children['args'],
            $method
        );
    }

    /**
     * Yields the positional arguments of partial function application that are bound to a parameter passed by reference.
     * (Named arguments are not handled, as in regular calls.)
     *
     * @param Node $args_node a node of kind ast\AST_CALLABLE_CONVERT
     * @return \Generator<int,array{0:Node,1:Parameter}> the argument and its parameter, by the index of the argument
     */
    private static function getBoundReferenceArguments(FunctionInterface $method, Node $args_node): \Generator
    {
        foreach (PartialApplication::getArgs($args_node) as $i => $argument) {
            if (!$argument instanceof Node || PartialApplication::isPlaceholder($argument)) {
                continue;
            }
            if ($argument->kind === ast\AST_NAMED_ARG || $argument->kind === ast\AST_UNPACK) {
                // The positions of the arguments after these are not known
                return;
            }
            $parameter = $method->getParameterForCaller($i);
            if ($parameter && $parameter->isPassByReference()) {
                yield $i => [$argument, $parameter];
            }
        }
    }

    /**
     * Creates the variables of the arguments for parameters passed by reference, before the arguments are checked,
     * and emits the issues that visitDim() left to this check for the `$var[...]` arguments passed by value.
     *
     * This is also used for the arguments of partial function application (php 8.6+), which are evaluated
     * and bound (by reference for these parameters) when the closure is created: the placeholders are skipped.
     *
     * @param array<int,Node|string|int|float|null> $argument_list
     */
    private function createReferencesAndCheckDimArguments(FunctionInterface $method, array $argument_list): void
    {
        foreach ($argument_list as $i => $argument) {
            if (!$argument instanceof Node || PartialApplication::isPlaceholder($argument)) {
                continue;
            }

            if ($argument->kind === ast\AST_NAMED_ARG) {
                // visitVar() and visitDim() leave `name: $var` and `name: $var[...]` to this check as well:
                // the parameter is found by name here.
                // (An unknown name is reported by ArgumentType::analyze().)
                $argument_expression = $argument->children['expr'];
                if (!($argument_expression instanceof Node)) {
                    continue;
                }
                // __call()/__callStatic() collect every named argument by value (the stand-in Method has no real parameters).
                $is_magic_call = $method instanceof Method && ($method->isMagicCall() || $method->isMagicCallStatic());
                $position = $is_magic_call ? null : self::getParameterPositionOfNamedArgument($method, (string)$argument->children['name']);
                if ($position !== null) {
                    $parameter = $method->getParameterForCaller($position);
                    if ($parameter && $parameter->isPassByReference()) {
                        // The same as for the positional argument: `preg_match($re, $s, matches: $m)` defines `$m`.
                        $this->createPassByReferenceArgumentInCall($method, $argument_expression, $parameter, $method->getRealParameterForCaller($position));
                        continue;
                    }
                }
                if ($argument_expression->kind === ast\AST_VAR) {
                    if (!($argument_expression->flags & PhanAnnotationAdder::FLAG_IGNORE_UNDEF)) {
                        // @phan-suppress-next-line PhanUndeclaredProperty this is added by visitVar()
                        $this->checkForUndeclaredVariable($argument_expression, ($argument_expression->is_undeclared_variable_of_named_argument ?? false) === true);
                    }
                } elseif ($argument_expression->kind === ast\AST_DIM && ($position !== null || $is_magic_call)) {
                    $this->emitPossiblyUndeclaredVariableOfDimArgument($argument_expression);
                }
                continue;
            }
            $parameter = $method->getParameterForCaller($i);
            if (!$parameter) {
                if ($argument->kind === ast\AST_DIM) {
                    $this->emitPossiblyUndeclaredVariableOfDimArgument($argument);
                }
                continue;
            }

            // If pass-by-reference, make sure the variable exists
            // or create it if it doesn't.
            if ($parameter->isPassByReference()) {
                $this->createPassByReferenceArgumentInCall($method, $argument, $parameter, $method->getRealParameterForCaller($i));
            } elseif ($argument->kind === ast\AST_DIM) {
                // visitDim() leaves `$var[...]` arguments to this check, since a reference would have created `$var`.
                // (It recorded whether `$var` was possibly undeclared when the argument was evaluated.)
                $this->emitPossiblyUndeclaredVariableOfDimArgument($argument);
            }
        }
    }

    /**
     * A variable that partial function application binds by reference may be set by any later call of the closure:
     * add the type of the parameter to the types of the variable.
     *
     * @param Node $args_node a node of kind ast\AST_CALLABLE_CONVERT
     */
    private function addReferenceTypesOfPartialApplication(FunctionInterface $method, Node $args_node): void
    {
        foreach (self::getBoundReferenceArguments($method, $args_node) as [$argument, $parameter]) {
            if ($argument->kind !== ast\AST_VAR) {
                continue;
            }
            $name = $argument->children['name'];
            if (!\is_string($name) || !$this->context->getScope()->hasVariableWithName($name)) {
                continue;
            }
            $variable = clone $this->context->getScope()->getVariableByName($name);
            $variable->setUnionType(
                $variable->getUnionType()->withUnionType($parameter->getNonVariadicUnionType())->eraseRealTypeSetRecursively()
            );
            $this->context->addScopeVariable($variable);
        }
    }

    /**
     * @param list<Method> $method_list
     */
    private function recordMethodReferences(array $method_list, Method $primary_method): void
    {
        if (!Config::get_track_references() || !$method_list) {
            return;
        }

        $seen_method_fqsens = [(string)$primary_method->getFQSEN() => true];
        $seen_class_fqsens = [];

        foreach ($method_list as $method) {
            $class = $method->getClass($this->code_base);
            $class_key = (string)$class->getFQSEN();
            if (!isset($seen_class_fqsens[$class_key])) {
                $class->addReference($this->context);
                $seen_class_fqsens[$class_key] = true;
            }

            $method_key = (string)$method->getFQSEN();
            if (isset($seen_method_fqsens[$method_key])) {
                continue;
            }
            $method->addReference($this->context);
            $seen_method_fqsens[$method_key] = true;
        }
    }

    /**
     * Returns the position of the parameter that the named argument `$name: ...` is passed to,
     * or null if no parameter has that name and the last parameter is not variadic (that is reported by ArgumentType::analyze()).
     * (A variadic last parameter collects the arguments with unknown names, as in ArgumentType::analyze())
     */
    private static function getParameterPositionOfNamedArgument(FunctionInterface $method, string $name): ?int
    {
        $parameter_list = $method->getRealParameterList();
        foreach ($parameter_list as $i => $parameter) {
            if ($parameter->getName() === $name) {
                return $i;
            }
        }
        $last_position = \count($parameter_list) - 1;
        if ($last_position >= 0 && $parameter_list[$last_position]->isVariadic()) {
            return $last_position;
        }
        return null;
    }

    /**
     * @param Parameter $parameter the parameter types inferred from combination of real and union type
     *
     * @param ?Parameter $real_parameter the real parameter type from the type signature
     */
    private function createPassByReferenceArgumentInCall(FunctionInterface $method, Node $argument, Parameter $parameter, ?Parameter $real_parameter): void
    {
        if ($argument->kind === ast\AST_DIM) {
            // `preg_match($re, $s, $out['m'])` creates `$out` and the offset 'm'.
            // (`$out[]` only creates `$out`: the element is appended when the type of the parameter is assigned)
            $this->createReferenceTarget($argument, false, true);
            return;
        }
        if ($argument->kind === ast\AST_VAR) {
            // We don't do anything with the new variable; just create it
            // if it doesn't exist
            try {
                $variable = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $argument
                ))->getOrCreateVariableForReferenceParameter($parameter, $real_parameter);
                $variable_union_type = $variable->getUnionType();
                if ($variable_union_type->hasRealTypeSet()) {
                    // TODO: Do a better job handling the large number of edge cases
                    // - e.g. infer that stream_select will convert non-empty arrays to possibly empty arrays, while the result continues to have a real type of array.
                    if ($method->getContext()->isPHPInternal() && \in_array($parameter->getReferenceType(), [Parameter::REFERENCE_IGNORED, Parameter::REFERENCE_READ_WRITE], true)) {
                        if (\preg_match('/shuffle|sort|array_(unshift|shift|push|pop|splice)/i', $method->getName())) {
                            // This use case is probably handled by MiscParamPlugin
                            return;
                        }
                    }
                    $variable->setUnionType($variable->getUnionType()->eraseRealTypeSetRecursively());
                }
            } catch (NodeException) {
                return;
            }
        } elseif ($argument->kind === ast\AST_STATIC_PROP
            || $argument->kind === ast\AST_PROP
        ) {
            $property_name = $argument->children['prop'];
            if ($property_name instanceof Node) {
                $property_name = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $property_name)->asSingleScalarValueOrNullOrSelf();
            }

            // Only try to handle known literals or strings, ignore properties with names that couldn't be inferred.
            if (\is_string($property_name)) {
                // We don't do anything with it; just create it
                // if it doesn't exist
                try {
                    $property = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $argument
                    ))->getOrCreateProperty($property_name, $argument->kind === ast\AST_STATIC_PROP);
                    $property->setHasWriteReference();
                } catch (IssueException $exception) {
                    Issue::maybeEmitInstance(
                        $this->code_base,
                        $this->context,
                        $exception->getIssueInstance()
                    );
                } catch (Exception) {
                    // If we can't figure out what kind of a call
                    // this is, don't worry about it
                }
            }
        }
    }

    /**
     * @param array<int,Node|string|int|float> $argument_list the arguments of the invocation in the order of the parameters (see ArgumentType::normalizeNamedArgs()), containing the pass by reference argument
     *
     * @param Parameter $parameter the parameter types inferred from combination of real and union type
     *
     * @param ?Parameter $real_parameter the real parameter type from the type signature
     */
    private static function analyzePassByReferenceArgument(
        CodeBase $code_base,
        Context $context,
        Node $argument,
        array $argument_list,
        FunctionInterface $method,
        Parameter $parameter,
        ?Parameter $real_parameter,
        int $parameter_offset
    ): void {
        $variable = null;
        $kind = $argument->kind;
        if ($kind === ast\AST_VAR) {
            try {
                $variable = (new ContextNode(
                    $code_base,
                    $context,
                    $argument
                ))->getOrCreateVariableForReferenceParameter($parameter, $real_parameter);
            } catch (NodeException) {
                // E.g. `function_accepting_reference(${$varName})` - Phan can't analyze outer type of ${$varName}
                return;
            }
        } elseif ($kind === ast\AST_STATIC_PROP
            || $kind === ast\AST_PROP
        ) {
            $property_name = $argument->children['prop'];
            if ($property_name instanceof Node) {
                $property_name = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $property_name)->asSingleScalarValueOrNullOrSelf();
            }

            // Only try to handle property names that could be inferred.
            if (\is_string($property_name)) {
                // We don't do anything with it; just create it
                // if it doesn't exist
                try {
                    $variable = (new ContextNode(
                        $code_base,
                        $context,
                        $argument
                    ))->getOrCreateProperty($property_name, $argument->kind === ast\AST_STATIC_PROP);
                    $variable->addReference($context);
                } catch (IssueException $exception) {
                    Issue::maybeEmitInstance(
                        $code_base,
                        $context,
                        $exception->getIssueInstance()
                    );
                } catch (Exception) {
                    // If we can't figure out what kind of a call
                    // this is, don't worry about it
                }
            }
        }

        if ($variable) {
            $set_variable_type = static function (UnionType $new_type) use ($code_base, $context, $variable, $argument): void {
                if ($variable instanceof Variable) {
                    $variable = clone $variable;
                    AssignmentVisitor::analyzeSetUnionTypeInContext($code_base, $context, $variable, $new_type, $argument);
                    $context->addScopeVariable($variable);
                } else {
                    // This is a Property. Add any compatible new types to the type of the property.
                    AssignmentVisitor::addTypesToPropertyStandalone($code_base, $context, $variable, $new_type);
                }
            };
            if ($variable instanceof Property) {
                // TODO: If @param-out is ever supported, then use that type to check
                self::checkPassingPropertyByReference($code_base, $context, $method, $parameter, $argument, $variable, $parameter_offset);
            }
            $variable_type = $variable->getUnionType();
        } elseif ($kind === ast\AST_DIM && self::getBaseVariableNodeOfDim($argument)) {
            // `$var[$k]`: createPassByReferenceArgumentInCall() created `$var` and the offset `$k` if they did not exist.
            try {
                $variable_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $argument, false);
            } catch (IssueException) {
                return;
            }
            $set_variable_type = static function (UnionType $new_type) use ($code_base, $context, $argument): void {
                self::assignTypeToReferencedDim($code_base, $context, $argument, $new_type);
            };
        } else {
            return;
        }
        switch ($parameter->getReferenceType()) {
            case Parameter::REFERENCE_WRITE_ONLY:
                self::analyzeWriteOnlyReference($code_base, $context, $method, $set_variable_type, $argument_list, $parameter);
                break;
            case Parameter::REFERENCE_READ_WRITE:
                $reference_parameter_type = $parameter->getNonVariadicUnionType();
                if ($variable_type->isEmpty()) {
                    // if Phan doesn't know the variable type,
                    // then guess that the variable is the type of the reference
                    // when analyzing the following statements.
                    $set_variable_type($reference_parameter_type);
                } elseif (!$variable_type->canCastToUnionType($reference_parameter_type, $code_base)) {
                    // Phan already warned about incompatible types.
                    // But analyze the following statements as if it could have been the type expected,
                    // to reduce false positives.
                    $set_variable_type($variable_type->withUnionType(
                        $reference_parameter_type
                    ));
                }
                // don't modify - assume the function takes the same type in that it returns,
                // and we want to preserve generic array types for sorting functions (May change later on)
                // TODO: Check type compatibility earlier, and don't modify?
                break;
            case Parameter::REFERENCE_IGNORED:
                // Pretend this reference doesn't modify the passed in argument.
                break;
            case Parameter::REFERENCE_DEFAULT:
            default:
                $reference_parameter_type = $parameter->getNonVariadicUnionType();
                // We have no idea what type of reference this is.
                // Probably user defined code.
                $set_variable_type($variable_type->withUnionType(
                    $reference_parameter_type
                ));
                break;
        }
    }

    /**
     * Analyzes `$var[$k] = <value of type $type>` for the offset `$var[$k]` that a reference was taken to.
     * Nothing is done if `$var` cannot be an array, e.g. if it is an ArrayAccess object.
     *
     * @param Node $node a node of kind ast\AST_DIM, for which getBaseVariableNodeOfDim() is not null
     */
    private static function assignTypeToReferencedDim(CodeBase $code_base, Context $context, Node $node, UnionType $type): void
    {
        $var_node = self::getBaseVariableNodeOfDim($node);
        $variable_name = $var_node->children['name'] ?? null;
        if (!\is_string($variable_name) || !$context->getScope()->hasVariableWithName($variable_name)) {
            return;
        }
        $var_type = $context->getScope()->getVariableByName($variable_name)->getUnionType();
        if (!($var_type->isEmpty() || $var_type->hasArray() || $var_type->containsNullable() || $var_type->hasMixedOrNonEmptyMixedType())) {
            return;
        }
        (new AssignmentVisitor(
            $code_base,
            $context,
            new Node(ast\AST_ASSIGN, 0, ['var' => $node, 'expr' => null], $node->lineno),
            $type
        ))->__invoke($node);
        // The types of the variable and of the offset may have been computed and cached for these nodes already.
        $context->clearCachedUnionTypes();
    }

    /**
     * @param Closure(UnionType):void $set_variable_type
     * @param array<int,Node|string|int|float> $argument_list (see ArgumentType::normalizeNamedArgs())
     */
    private static function analyzeWriteOnlyReference(
        CodeBase $code_base,
        Context $context,
        FunctionInterface $method,
        Closure $set_variable_type,
        array $argument_list,
        Parameter $parameter
    ): void {
        switch ($method->getFQSEN()->__toString()) {
            case '\preg_match':
                $set_variable_type(
                    RegexAnalyzer::getPregMatchUnionType($code_base, $context, $argument_list)
                );
                return;
            case '\preg_match_all':
                $set_variable_type(
                    RegexAnalyzer::getPregMatchAllUnionType($code_base, $context, $argument_list)
                );
                return;
            default:
                $reference_parameter_type = $parameter->getNonVariadicUnionType();

                // The previous value is being ignored, and being replaced.
                // FIXME: Do something different for properties, e.g. limit it to a scope, combine with old property, etc.
                $set_variable_type(
                    $reference_parameter_type
                );
        }
    }

    private function trackReferenceToClosure(Node $argument): void
    {
        try {
            $inner_context = $this->context->withLineNumberStart($argument->lineno);
            $method = (new ContextNode(
                $this->code_base,
                $inner_context,
                $argument
            ))->getClosure();

            $method->addReference($inner_context);
        } catch (Exception) {
            // Swallow it
        }
    }

    /**
     * Recursively find and track references to closures inside an array node.
     * This prevents false positive PhanUnreferencedClosure warnings for closures
     * passed inside arrays to function arguments.
     */
    private function trackReferencesToClosuresInArray(Node $array_node): void
    {
        foreach ($array_node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            // Handle AST_ARRAY_ELEM nodes
            if ($child->kind === ast\AST_ARRAY_ELEM) {
                $value = $child->children['value'];
                if ($value instanceof Node) {
                    if ($value->kind === ast\AST_CLOSURE || $value->kind === ast\AST_ARROW_FUNC) {
                        $this->trackReferenceToClosure($value);
                    } elseif ($value->kind === ast\AST_ARRAY) {
                        // Recursively handle nested arrays
                        $this->trackReferencesToClosuresInArray($value);
                    }
                }
            }
        }
    }

    /**
     * Replace the method's parameter types with the argument
     * types and re-analyze the method.
     *
     * This is used when analyzing callbacks and closures, e.g. in array_map.
     *
     * @param list<UnionType> $argument_types
     * An AST node listing the arguments
     *
     * @param FunctionInterface $method
     * The method or function being called
     * @see analyzeMethodWithArgumentTypes (Which takes AST nodes)
     *
     * @param list<Node|mixed> $arguments
     * An array of arguments to the callable, to analyze references.
     *
     * @param bool $erase_old_return_type
     * Whether $method's old return type should be erased
     * to use the newly inferred type based on $argument_types.
     * (useful for array_map, etc)
     */
    public function analyzeCallableWithArgumentTypes(
        array $argument_types,
        FunctionInterface $method,
        array $arguments = [],
        bool $erase_old_return_type = false
    ): void {
        $method = $this->findDefiningMethod($method);
        if (!$method->needsRecursiveAnalysis()) {
            return;
        }

        // Don't re-analyze recursive methods. That doesn't go well.
        if ($this->context->isInFunctionLikeScope()
            && $method->getFQSEN() === $this->context->getFunctionLikeFQSEN()
        ) {
            return;
        }
        foreach ($argument_types as $i => $type) {
            $argument_types[$i] = $type->withStaticResolvedInContext($this->context);
        }

        $original_method_scope = $method->getInternalScope();
        $method->setInternalScope(clone $original_method_scope);
        try {
            // Even though we don't modify the parameter list, we still need to know the types
            // -- as an optimization, we don't run quick mode again if the types didn't change?
            $parameter_list = \array_map(static function (Parameter $parameter): Parameter {
                return clone $parameter;
            }, $method->getParameterList());

            foreach ($parameter_list as $i => $parameter_clone) {
                if (!isset($argument_types[$i]) && $parameter_clone->hasDefaultValue()) {
                    $parameter_type = $parameter_clone->getDefaultValueType()->withRealTypeSet($parameter_clone->getNonVariadicUnionType()->getRealTypeSet());
                    if ($parameter_type->isType(NullType::instance(false))) {
                        // Treat a parameter default of null the same way as passing null to that parameter
                        // (Add null to the list of possibilities)
                        $parameter_clone->addUnionType($parameter_type);
                    } else {
                        // For other types (E.g. string), just replace the union type.
                        $parameter_clone->setUnionType($parameter_type);
                    }
                }

                // Add the parameter to the scope
                $method->getInternalScope()->addVariable(
                    $parameter_clone->asNonVariadic()
                );

                // If there's no parameter at that offset, we may be in
                // a ParamTooMany situation. That is caught elsewhere.
                if (!isset($argument_types[$i])
                    || !$parameter_clone->hasEmptyNonVariadicType()
                ) {
                    continue;
                }

                $this->updateParameterTypeByArgument(
                    $method,
                    $parameter_clone,
                    $arguments[$i] ?? null,
                    $argument_types,
                    $parameter_list,
                    $i
                );
            }
            foreach ($parameter_list as $parameter_clone) {
                if ($parameter_clone->isVariadic()) {
                    // We're using this parameter clone to analyze the **inside** of the method, it's never seen on the outside.
                    // Convert it immediately.
                    // TODO: Add tests of variadic references, fix those if necessary.
                    $method->getInternalScope()->addVariable(
                        $parameter_clone->cloneAsNonVariadic()
                    );
                }
            }

            // Now that we know something about the parameters used
            // to call the method, we can reanalyze the method with
            // the types of the parameter
            if ($erase_old_return_type) {
                $method->setUnionType($method->getOriginalReturnType());
            }
            $method->analyzeWithNewParams($method->getContext(), $this->code_base, $parameter_list);
        } finally {
            $method->setInternalScope($original_method_scope);
        }
    }

    /**
     * Replace the method's parameter types with the argument
     * types and re-analyze the method.
     *
     * @param Node $argument_list_node
     * An AST node listing the arguments
     *
     * @param FunctionInterface $method
     * The method or function being called
     * Precondition: $method->needsRecursiveAnalysis() === false
     *
     * @return void
     *
     * TODO: deduplicate code.
     */
    private function analyzeMethodWithArgumentTypes(
        Node $argument_list_node,
        FunctionInterface $method
    ): void {
        $method = $this->findDefiningMethod($method);
        $original_method_scope = $method->getInternalScope();
        $method->setInternalScope(clone $original_method_scope);
        $method_context = $method->getContext();

        try {
            // Even though we don't modify the parameter list, we still need to know the types
            // -- as an optimization, we don't run quick mode again if the types didn't change?
            $parameter_list = \array_map(static function (Parameter $parameter): Parameter {
                return $parameter->cloneAsNonVariadic();
            }, $method->getParameterList());

            // always resolve all arguments outside of quick mode to detect undefined variables, other problems in call arguments.
            // Fixes https://github.com/phan/phan/issues/583
            $argument_types = [];
            foreach ($argument_list_node->children as $i => $argument) {
                // Determine the type of the argument at position $i
                $argument_types[$i] = UnionTypeVisitor::unionTypeFromNode(
                    $this->code_base,
                    $this->context,
                    $argument,
                    true
                )->withStaticResolvedInContext($this->context)->eraseRealTypeSetRecursively();
            }

            foreach ($parameter_list as $i => $parameter_clone) {
                $argument = $argument_list_node->children[$i] ?? null;

                if ($argument === null
                    && $parameter_clone->hasDefaultValue()
                ) {
                    $parameter_type = $parameter_clone->getDefaultValueType()->withRealTypeSet($parameter_clone->getNonVariadicUnionType()->getRealTypeSet());
                    if ($parameter_type->isType(NullType::instance(false))) {
                        // Treat a parameter default of null the same way as passing null to that parameter
                        // (Add null to the list of possibilities)
                        $parameter_clone->addUnionType($parameter_type);
                    } else {
                        // For other types (E.g. string), just replace the union type.
                        $parameter_clone->setUnionType($parameter_type);
                    }
                }

                // Add the parameter to the scope
                // TODO: asNonVariadic()?
                $method->getInternalScope()->addVariable(
                    $parameter_clone
                );

                // If there's no parameter at that offset, we may be in
                // a ParamTooMany situation. That is caught elsewhere.
                if ($argument === null) {
                    continue;
                }

                // If there's a declared type for the parameter,
                // then don't bother overriding the type to analyze the function/method body (unless the parameter is pass-by-reference)
                // Note that $parameter_clone was converted to a non-variadic clone, so the getNonVariadicUnionType returns an array.
                if (!$parameter_clone->hasEmptyNonVariadicType() && !$parameter_clone->isPassByReference()) {
                    continue;
                }

                $this->updateParameterTypeByArgument(
                    $method,
                    $parameter_clone,
                    $argument,
                    $argument_types,
                    $parameter_list,
                    $i
                );
            }
            foreach ($parameter_list as $parameter_clone) {
                if ($parameter_clone->isVariadic()) {
                    // We're using this parameter clone to analyze the **inside** of the method, it's never seen on the outside.
                    // Convert it immediately.
                    // TODO: Add tests of variadic references, fix those if necessary.
                    $method->getInternalScope()->addVariable(
                        $parameter_clone->cloneAsNonVariadic()
                    );
                }
            }

            // Now that we know something about the parameters used
            // to call the method, we can reanalyze the method with
            // the types of the parameter
            $method->analyzeWithNewParams($method_context, $this->code_base, $parameter_list);
        } finally {
            $method->setInternalScope($original_method_scope);
        }
    }

    private function findDefiningMethod(FunctionInterface $method): FunctionInterface
    {
        if ($method instanceof Method) {
            $defining_fqsen = $method->getDefiningFQSEN();
            if ($method->getFQSEN() !== $defining_fqsen) {
                // This should always happen, unless in the language server mode
                if ($this->code_base->hasMethodWithFQSEN($defining_fqsen)) {
                    return $this->code_base->getMethodByFQSEN($defining_fqsen);
                }
            }
        }
        return $method;
    }

    /**
     * Check if $argument_list_node calling itself is likely to be a case of infinite recursion.
     * This is based on heuristics, and will not catch all cases.
     */
    private function checkForInfiniteRecursion(Node $node, FunctionInterface $method): void
    {
        $argument_list_node = $node->children['args'];
        $kind = $node->kind;
        if ($kind === ast\AST_METHOD_CALL || $kind === ast\AST_NULLSAFE_METHOD_CALL) {
            $expr = $node->children['expr'];
            if (!$expr instanceof Node || $expr->kind !== ast\AST_VAR || $expr->children['name'] !== 'this') {
                return;
            }
        }
        $nearest_function_like = null;
        foreach ($this->parent_node_list as $c) {
            if (\in_array($c->kind, [ast\AST_FUNC_DECL, ast\AST_METHOD, ast\AST_CLOSURE], true)) {
                $nearest_function_like = $c;
            }
        }
        if (!$nearest_function_like) {
            return;
        }
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
        if (ReachabilityChecker::willUnconditionallyBeReached($nearest_function_like->children['stmts'], $argument_list_node)) {
            $this->emitIssue(
                Issue::InfiniteRecursion,
                $node->lineno,
                $method->getNameForIssue()
            );
            return;
        }
        $this->checkForInfiniteRecursionWithSameArgs($node, $method);
    }

    private function checkForInfiniteRecursionWithSameArgs(Node $node, FunctionInterface $method): void
    {
        $argument_list_node = $node->children['args'];
        $parameter_list = $method->getParameterList();
        if (\count($argument_list_node->children) !== \count($parameter_list)) {
            return;
        }
        if (\count($argument_list_node->children) === 0) {
            if ($method instanceof Method && !$method->isStatic()) {
                // Instance methods might guard recursion using shared state.
                // Defer the decision to VariableTrackerVisitor so that it can check if $this was modified.
                // @phan-suppress-next-line PhanUndeclaredProperty
                $node->check_infinite_recursion = [[], $method->getNameForIssue(), true];
                return;
            }
            $this->emitIssue(
                Issue::PossibleInfiniteRecursionSameParams,
                $node->lineno,
                $method->getNameForIssue()
            );
            return;
        }
        // TODO also check AST_UNPACK against variadic
        $arg_names = [];
        foreach ($argument_list_node->children as $i => $arg) {
            if (!$arg instanceof Node) {
                return;
            }
            $is_unpack = false;
            if ($arg->kind === ast\AST_UNPACK) {
                $arg = $arg->children['expr'];
                if (!$arg instanceof Node) {
                    return;
                }
                $is_unpack = true;
            }
            if ($arg->kind !== ast\AST_VAR) {
                return;
            }
            $arg_name = $arg->children['name'];
            if (!\is_string($arg_name)) {
                return;
            }
            $param = $parameter_list[$i];
            if ($param->getName() !== $arg_name || $param->isVariadic() !== $is_unpack) {
                return;
            }
            $arg_names[] = $arg_name;
        }
        $outer_scope = $method->getInternalScope();
        $current_scope = $this->context->getScope();
        foreach ($arg_names as $arg_name) {
            if (!$current_scope->hasVariableWithName($arg_name) || !$outer_scope->hasVariableWithName($arg_name)) {
                return;
            }
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $node->check_infinite_recursion = [$arg_names, $method->getNameForIssue()];
    }

    /**
     * @param FunctionInterface $method
     * The method that we're updating parameter types for
     *
     * @param Parameter $parameter
     * The parameter that we're updating
     *
     * @param Node|mixed $argument
     * The argument whose type we'd like to replace the
     * parameter type with.
     *
     * @param list<UnionType> $argument_types
     * The type of arguments
     *
     * @param list<Parameter> &$parameter_list
     * The parameter list - types are modified by reference
     *
     * @param int $parameter_offset
     * The offset of the parameter on the method's
     * signature.
     */
    private function updateParameterTypeByArgument(
        FunctionInterface $method,
        Parameter $parameter,
        mixed $argument,
        array $argument_types,
        array &$parameter_list,
        int $parameter_offset
    ): void {
        $argument_type = $argument_types[$parameter_offset];
        if ($parameter->isVariadic()) {
            for ($i = $parameter_offset + 1; $i < \count($argument_types); $i++) {
                $argument_type = $argument_type->withUnionType($argument_types[$i]);
            }
        }
        // $argument_type = $this->filterValidArgumentTypes($argument_type, $non_variadic_parameter_type);
        if (!$argument_type->isEmpty()) {
            // Then set the new type on that parameter based
            // on the argument's type. We'll use this to
            // retest the method with the passed in types
            // TODO: if $argument_type is non-empty and !isType(NullType), instead use setUnionType?

            if ($parameter->isCloneOfVariadic()) {
                // For https://github.com/phan/phan/issues/1525 : Collapse array shapes into generic arrays before recursively analyzing a method.
                if ($parameter->hasEmptyNonVariadicType()) {
                    $parameter->setUnionType(
                        $argument_type->withFlattenedArrayShapeOrLiteralTypeInstances()->asListTypes()->withRealTypeSet($parameter->getNonVariadicUnionType()->getRealTypeSet())
                    );
                } else {
                    $parameter->addUnionType(
                        $argument_type->withFlattenedArrayShapeOrLiteralTypeInstances()->asListTypes()->withRealTypeSet($parameter->getNonVariadicUnionType()->getRealTypeSet())
                    );
                }
            } else {
                $parameter->addUnionType(
                    ($method instanceof Func && $method->isClosure() ? $argument_type : $argument_type->withFlattenedArrayShapeOrLiteralTypeInstances())->withRealTypeSet($parameter->getNonVariadicUnionType()->getRealTypeSet())
                );
            }
            if ($method instanceof Method && ($parameter->getFlags() & Parameter::PARAM_MODIFIER_FLAGS)) {
                $this->analyzeArgumentWithConstructorPropertyPromotion($method, $parameter);
            }
        }

        // If we're passing by reference, get the variable
        // we're dealing with wrapped up and shoved into
        // the scope of the method
        if (!$parameter->isPassByReference()) {
            // Overwrite the method's variable representation
            // of the parameter with the parameter with the
            // new type
            $method->getInternalScope()->addVariable(
                $parameter
            );

            return;
        }

        // At this point we're dealing with a pass-by-reference
        // parameter.

        // For now, give up and work on it later.
        //
        // TODO (Issue #376): It's possible to have a
        // parameter `&...$args`. Analyzing that is going to
        // be a problem. Is it possible to create
        // `PassByReferenceVariableCollection extends Variable`
        // or something similar?
        if ($parameter->isVariadic()) {
            return;
        }

        if (!$argument instanceof Node) {
            return;
        }

        $variable = null;
        if ($argument->kind === ast\AST_VAR) {
            try {
                $variable = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $argument
                ))->getOrCreateVariableForReferenceParameter($parameter, $method->getRealParameterForCaller($parameter_offset));
            } catch (NodeException) {
                // Could not figure out the node name
                return;
            }
        } elseif (\in_array($argument->kind, [ast\AST_STATIC_PROP, ast\AST_PROP], true)) {
            try {
                $variable = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $argument
                ))->getProperty($argument->kind === ast\AST_STATIC_PROP);
            } catch (IssueException | NodeException) {
                // Hopefully caught elsewhere
            }
        }

        // If we couldn't find a variable, give up
        if (!$variable) {
            return;
        }
        // For @phan-ignore-reference, don't bother modifying the type
        if ($parameter->getReferenceType() === Parameter::REFERENCE_IGNORED) {
            return;
        }

        $pass_by_reference_variable =
            new PassByReferenceVariable(
                $parameter,
                $variable,
                $this->code_base,
                $this->context
            );
        // Add it to the (cloned) scope of the function wrapped
        // in a way that makes it addressable as the
        // parameter its mimicking
        $method->getInternalScope()->addVariable(
            $pass_by_reference_variable
        );
        $parameter_list[$parameter_offset] = $pass_by_reference_variable;
    }

    private function analyzeArgumentWithConstructorPropertyPromotion(Method $method, Parameter $parameter): void
    {
        if (!$method->isNewConstructor()) {
            return;
        }
        $code_base = $this->code_base;
        $class_fqsen = $method->getClassFQSEN();
        $class = $code_base->getClassByFQSEN($class_fqsen);
        $property = $class->getPropertyByName($code_base, $parameter->getName());
        AssignmentVisitor::addTypesToPropertyStandalone($code_base, $this->context, $property, $parameter->getUnionType());
    }


    /**
     * Emit warnings if the pass-by-reference call would set the property to an invalid type
     * @param Node $argument a node of kind ast\AST_PROP or ast\AST_STATIC_PROP
     */
    private static function checkPassingPropertyByReference(CodeBase $code_base, Context $context, FunctionInterface $method, Parameter $parameter, Node $argument, Property $property, int $parameter_offset): void
    {
        $parameter_type = $parameter->getNonVariadicUnionType();
        $expr_node = $argument->children['expr'] ?? null;
        if ($expr_node instanceof Node &&
                $expr_node->kind === ast\AST_VAR &&
                $expr_node->children['name'] === 'this') {
            // If the property is of the form $this->prop, check for local assignments and conditions on $this->prop
            $property_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $argument);
        } else {
            $property_type = $property->getUnionType();
        }
        if ($property_type->hasRealTypeSet()) {
            // Barely any reference parameters will have real union types (and phan would already warn about passing them in if they did),
            // so warn if the phpdoc type doesn't match the property's real type.
            if (!$parameter_type->canCastToDeclaredType($code_base, $context, $property_type)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeMismatchArgumentPropertyReferenceReal,
                    $argument->lineno,
                    $parameter_offset,
                    $property->getRepresentationForIssue(),
                    $property_type,
                    self::toDetailsForRealTypeMismatch($property_type),
                    $method->getRepresentationForIssue(),
                    $parameter_type,
                    self::toDetailsForRealTypeMismatch($parameter_type)
                );
                return;
            }
        }
        if ($parameter_type->canCastToDeclaredType($code_base, $context, $property_type)) {
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            Issue::TypeMismatchArgumentPropertyReference,
            $argument->lineno,
            $parameter_offset,
            $property->getRepresentationForIssue(),
            $property_type,
            $method->getRepresentationForIssue(),
            $parameter_type
        );
    }

    /**
     * @param list<Node> $parent_node_list
     */
    private static function isInNoOpPositionForList(Node $node, array $parent_node_list): bool
    {
        $parent_node = \end($parent_node_list);
        if (!($parent_node instanceof Node)) {
            return false;
        }
        switch ($parent_node->kind) {
            case ast\AST_STMT_LIST:
                return true;
            case ast\AST_EXPR_LIST:
                $parent_parent_node = \prev($parent_node_list);
                // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
                if ($parent_parent_node->kind === ast\AST_MATCH_ARM) {
                    return false;
                }
                if ($node !== \end($parent_node->children)) {
                    return true;
                }
                // This is an expression list, but it's in the condition
                return $parent_node !== ($parent_parent_node->children['cond'] ?? null);
        }
        return false;
    }

    private function isInNoOpPosition(Node $node): bool
    {
        $parent_node = \end($this->parent_node_list);
        if (!($parent_node instanceof Node)) {
            return false;
        }
        switch ($parent_node->kind) {
            case ast\AST_STMT_LIST:
                return true;
            case ast\AST_EXPR_LIST:
                $parent_parent_node = \prev($this->parent_node_list);
                // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
                if ($parent_parent_node->kind === ast\AST_MATCH_ARM) {
                    return false;
                }
                if ($node !== \end($parent_node->children)) {
                    return true;
                }
                // This is an expression list, but it's in the condition
                return $parent_node !== ($parent_parent_node->children['cond'] ?? null);
        }
        return false;
    }

    /**
     * @param Node $node
     * A node to check to see if it's a no-op
     *
     * @param string $issue_type
     * A message to emit if it's a no-op
     */
    private function analyzeNoOp(Node $node, string $issue_type): void
    {
        if ($this->isInNoOpPosition($node)) {
            $this->emitIssue(
                $issue_type,
                $node->lineno
            );
        }
    }

    /**
     * Check if a function/method call with #[NoDiscard] has its return value ignored
     * @param Node $node the function/method call node
     * @param Func|Method|mixed $function_like the function or method being called
     */
    private function checkNoDiscardAttribute(Node $node, mixed $function_like): void
    {
        // Only check for Func and Method instances (not closures or other types)
        if (!($function_like instanceof \Phan\Language\Element\Func || $function_like instanceof \Phan\Language\Element\Method)) {
            return;
        }

        if (!$this->isInNoOpPosition($node)) {
            // Return value is being used, no warning needed
            return;
        }

        // Check if this is a (void) cast - that explicitly suppresses NoDiscard
        $parent_node = \end($this->parent_node_list);
        if ($parent_node instanceof Node && $parent_node->kind === \ast\AST_CAST && $parent_node->flags === \ast\flags\TYPE_VOID) {
            return;
        }

        if ($function_like->hasNoDiscardAttribute()) {
            $this->emitIssue(
                Issue::NoDiscardReturnValueIgnored,
                $node->lineno,
                $function_like->getRepresentationForIssue()
            );
        }
    }

    private static function hasEmptyImplementation(Method $method): bool
    {
        if ($method->isAbstract() || $method->isPHPInternal()) {
            return false;
        }
        $stmts = $method->getNode()->children['stmts'] ?? null;
        if (!$stmts instanceof Node) {
            // This is abstract or a stub or a magic method
            return false;
        }
        return empty($stmts->children);
    }

    /**
     * @param list<Clazz> $class_list
     */
    private function warnNoopNew(
        Node $node,
        array $class_list
    ): void {
        $has_constructor_or_destructor = \count($class_list) === 0;
        foreach ($class_list as $class) {
            if ($class->getPhanFlagsHasState(\Phan\Language\Element\Flags::IS_CONSTRUCTOR_USED_FOR_SIDE_EFFECTS)) {
                return;
            }
        }
        foreach ($class_list as $class) {
            if ($class->hasMethodWithName($this->code_base, '__construct', true)) {
                $constructor = $class->getMethodByName($this->code_base, '__construct');
                if (!$constructor->getPhanFlagsHasState(\Phan\Language\Element\Flags::IS_FAKE_CONSTRUCTOR)) {
                    if (!self::hasEmptyImplementation($constructor)) {
                        $has_constructor_or_destructor = true;
                        break;
                    }
                }
            }
            if ($class->hasMethodWithName($this->code_base, '__destruct', true)) {
                $destructor = $class->getMethodByName($this->code_base, '__destruct');
                if (!self::hasEmptyImplementation($destructor)) {
                    $has_constructor_or_destructor = true;
                    break;
                }
            }
            if (!$class->isClass() || $class->isAbstract()) {
                $has_constructor_or_destructor = true;
                break;
            }
        }
        $this->emitIssue(
            $has_constructor_or_destructor ? Issue::NoopNew : Issue::NoopNewNoSideEffects,
            $node->lineno,
            ASTReverter::toShortString($node)
        );
    }

    public const LOOP_SCOPE_KINDS = [
        ast\AST_FOR => true,
        ast\AST_FOREACH => true,
        ast\AST_WHILE => true,
        ast\AST_DO_WHILE => true,
        ast\AST_SWITCH => true,
    ];

    /**
     * Analyzes a `break;` or `break N;` statement.
     * Checks if there are enough loops to break out of.
     */
    public function visitBreak(Node $node): Context
    {
        $depth = $node->children['depth'] ?? 1;
        if (!\is_int($depth)) {
            return $this->context;
        }
        foreach ($this->parent_node_list as $iter_node) {
            if (\array_key_exists($iter_node->kind, self::LOOP_SCOPE_KINDS)) {
                $depth--;
                if ($depth <= 0) {
                    return $this->context;
                }
            }
        }
        $this->warnBreakOrContinueWithoutLoop($node);
        return $this->context;
    }

    /**
     * Analyzes a `continue;` or `continue N;` statement.
     * Checks for http://php.net/manual/en/migration73.incompatible.php#migration73.incompatible.core.continue-targeting-switch
     * and similar issues.
     */
    public function visitContinue(Node $node): Context
    {
        $nodes = $this->parent_node_list;
        $depth = $node->children['depth'] ?? 1;
        if (!\is_int($depth)) {
            return $this->context;
        }
        for ($iter_node = \end($nodes); $iter_node instanceof Node; $iter_node = \prev($nodes)) {
            switch ($iter_node->kind) {
                case ast\AST_FOR:
                case ast\AST_FOREACH:
                case ast\AST_WHILE:
                case ast\AST_DO_WHILE:
                    $depth--;
                    if ($depth <= 0) {
                        return $this->context;
                    }
                    break;
                case ast\AST_SWITCH:
                    $depth--;
                    if ($depth <= 0) {
                        $this->emitIssue(
                            Issue::ContinueTargetingSwitch,
                            $node->lineno
                        );
                        return $this->context;
                    }
                    break;
            }
        }
        $this->warnBreakOrContinueWithoutLoop($node);
        return $this->context;
    }

    /**
     * Visit a node of kind AST_LABEL to check for unused labels.
     * @override
     */
    public function visitLabel(Node $node): Context
    {
        $label = $node->children['name'];
        $used_labels = GotoAnalyzer::getLabelSet($this->parent_node_list);
        if (!isset($used_labels[$label])) {
            $this->emitIssue(
                Issue::UnusedGotoLabel,
                $node->lineno,
                $label
            );
        }
        return $this->context;
    }

    private function warnBreakOrContinueWithoutLoop(Node $node): void
    {
        $depth = $node->children['depth'] ?? 1;
        $name = $node->kind === ast\AST_BREAK ? 'break' : 'continue';
        if ($depth !== 1) {
            $this->emitIssue(
                Issue::ContinueOrBreakTooManyLevels,
                $node->lineno,
                $name,
                $depth
            );
            return;
        }
        $this->emitIssue(
            Issue::ContinueOrBreakNotInLoop,
            $node->lineno,
            $name
        );
    }

    /**
     * @param Node $node
     * A decl to check to see whether it can never return normally
     *
     * @return bool
     * True when no path through the decl returns normally - i.e. every path
     * throws an exception, exits (exit()/die()), or loops forever.
     * A path that reaches a `return` (or falls off the end) makes this false.
     */
    private function declNeverReturns(Node $node): bool
    {
        // Work around fallback parser generating methods without statements list.
        // Otherwise, 'stmts' would always be a Node due to preconditions.
        $stmts_node = $node->children['stmts'];
        return $stmts_node instanceof Node && BlockExitStatusChecker::willUnconditionallyNeverReturn($stmts_node, $this->code_base, $this->context);
    }

    private function canApplyStaticPropertyOverride(FullyQualifiedClassName $calling_class, FullyQualifiedClassName $target_class): bool
    {
        if ($calling_class->__toString() === $target_class->__toString()) {
            return true;
        }
        $is_trait = false;
        if ($this->code_base->hasClassWithFQSEN($target_class)) {
            $class = $this->code_base->getClassByFQSEN($target_class);
            $is_trait = $class->isTrait();
        }
        if ($is_trait) {
            return true;
        }
        $calling_type = $calling_class->asType();
        $target_type = $target_class->asType();
        return $calling_type->isSubtypeOf($target_type, $this->code_base);
    }

    private function doesStaticOverrideMatchTargetClass(string $property_name, FullyQualifiedClassName $target_class, FullyQualifiedClassName $calling_class, bool $is_late_static): bool
    {
        if (!$this->code_base->hasClassWithFQSEN($calling_class)) {
            return false;
        }
        $calling_class_instance = $this->code_base->getClassByFQSEN($calling_class);
        try {
            $property = $calling_class_instance->getPropertyByNameInContext(
                $this->code_base,
                $property_name,
                $this->context,
                true,
                null,
                true
            );
        } catch (IssueException | CodeBaseException) {
            return false;
        }
        $defining_class = $property->getRealDefiningFQSEN()->getFullyQualifiedClassName();
        if ($defining_class->__toString() === $target_class->__toString()) {
            return true;
        }
        if ($is_late_static) {
            $defining_type = $defining_class->asType();
            return $calling_class->asType()->isSubtypeOf($defining_type, $this->code_base);
        }
        return false;
    }


    /**
     * @param Node $node @unused-param
     * A node to analyze
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitThrow(Node $node): Context
    {
        $parent_node = \end($this->parent_node_list);
        if (!($parent_node instanceof Node)) {
            return $this->context;
        }
        // No additional processing needed for non-statement lists

        return $this->context;
    }

}
