<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\AnalysisVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use ast\Node;

class PostOrderAnalysisVisitor extends AnalysisVisitor
{
    /**
     * @var Node|null
     */
    private $parent_node;

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
     * @param Node|null $parent_node
     * The parent node of the node being analyzed
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $parent_node = null
    ) {
        parent::__construct($code_base, $context);
        $this->parent_node = $parent_node;
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
    public function visit(Node $node) : Context
    {
        // Many nodes don't change the context and we
        // don't need to read them.
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
    public function visitAssign(Node $node) : Context
    {
        // Get the type of the right side of the
        // assignment
        $right_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            true
        );

        \assert(
            $node->children['var'] instanceof Node,
            "Expected left side of assignment to be a var"
        );

        if ($right_type->isType(VoidType::instance(false))) {
            $this->emitIssue(
                Issue::TypeVoidAssignment,
                $node->lineno ?? 0
            );
        }

        // Handle the assignment based on the type of the
        // right side of the equation and the kind of item
        // on the left
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type
        ))($node->children['var']);

        if ($node->children['expr'] instanceof Node
            && $node->children['expr']->kind == \ast\AST_CLOSURE
        ) {
            $closure_node = $node->children['expr'];
            $method = (new ContextNode(
                $this->code_base,
                $this->context->withLineNumberStart(
                    $closure_node->lineno ?? 0
                ),
                $closure_node
            ))->getClosure();

            $method->addReference($this->context);
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
    public function visitAssignRef(Node $node) : Context
    {
        return $this->visitAssign($node);
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIfElem(Node $node) : Context
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
    public function visitWhile(Node $node) : Context
    {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitch(Node $node) : Context
    {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitchCase(Node $node) : Context
    {
        return $this->visitIfElem($node);
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
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEncapsList(Node $node) : Context
    {
        foreach ((array)$node->children as $child_node) {
            // Confirm that variables exists
            if ($child_node instanceof Node
                && $child_node->kind == \ast\AST_VAR
            ) {
            }
        }

        return $this->context;
    }

    /**
     * Check if a given variable is undeclared.
     * @param Node - Node with kind AST_VAR
     * @return void
     */
    private function checkForUndeclaredVariable(Node $node)
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

        if (!$this->context->getScope()->hasVariableWithName($variable_name)
            && !Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())
        ) {
            $this->emitIssue(
                Issue::UndeclaredVariable,
                $node->lineno ?? 0,
                $variable_name
            );
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
    public function visitDoWhile(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_GLOBAL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGlobal(Node $node) : Context
    {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            $this->code_base,
            false
        );
        $variable_name = $variable->getName();
        $optional_global_variable_type = Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name);
        if ($optional_global_variable_type) {
            $variable->setUnionType($optional_global_variable_type);
        } else {
            $scope = $this->context->getScope();
            if ($scope->hasGlobalVariableWithName($variable_name)) {
                // TODO: Support @global, add a clone to the method context?
                $actual_global_variable = $scope->getGlobalVariableByName($variable_name);
                $this->context->addScopeVariable($actual_global_variable);
                return $this->context;
            }
        }

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
    public function visitForeach(Node $node) : Context
    {
        $expression_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        // Check the expression type to make sure its
        // something we can iterate over
        if ($expression_type->isScalar()) {
            $this->emitIssue(
                Issue::TypeMismatchForeach,
                $node->lineno ?? 0,
                (string)$expression_type
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
    public function visitStatic(Node $node) : Context
    {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            $this->code_base,
            false
        );

        // If the element has a default, set its type
        // on the variable
        if (isset($node->children['default'])) {
            $default_type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['default']
            );

            $variable->setUnionType($default_type);
        }

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
    public function visitEcho(Node $node) : Context
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
    public function visitPrint(Node $node) : Context
    {
        $type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            true
        );

        // TODO: Check objects and resources as well?
        if ($type->isType(ArrayType::instance(false))
            || $type->isType(ArrayType::instance(true))
            || $type->isGenericArray()
        ) {
            $this->emitIssue(
                Issue::TypeConversionFromArray,
                $node->lineno ?? 0,
                'string'
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
    public function visitVar(Node $node) : Context
    {
        $this->analyzeNoOp($node, Issue::NoopVariable);
        $parent_node = $this->parent_node;
        if ($parent_node instanceof Node) {
            $parent_kind = $parent_node->kind;
            /**
             * These types are either types which create variables,
             * or types which will be checked in other parts of Phan
             */
            static $skip_var_check_types = [
                \ast\AST_ARG_LIST       => true,  // may be a reference
                \ast\AST_ARRAY_ELEM     => true,  // [$X, $y] = expr() is an AST_ARRAY_ELEM. visitArray() checks the right hand side.
                \ast\AST_ASSIGN_OP      => true,  // checked in visitAssignOp
                \ast\AST_ASSIGN_REF     => true,  // Creates by reference?
                \ast\AST_ASSIGN         => true,  // checked in visitAssign
                \ast\AST_DIM            => true,  // should be checked elsewhere, as part of check for array access to non-array/string
                \ast\AST_EMPTY          => true,  // TODO: Enable this in the future?
                \ast\AST_GLOBAL         => true,  // global $var;
                \ast\AST_ISSET          => true,  // TODO: Enable this in the future?
                \ast\AST_PARAM_LIST     => true,  // this creates the variable
                \ast\AST_STATIC         => true,  // static $var;
                \ast\AST_STMT_LIST      => true,  // ;$var; (Implicitly creates the variable. Already checked to emit PhanNoopVariable)
                \ast\AST_USE_ELEM       => true,  // may be a reference, checked elsewhere
            ];

            if (!\array_key_exists($parent_kind, $skip_var_check_types)) {
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
    public function visitArray(Node $node) : Context
    {
        $this->analyzeNoOp($node, Issue::NoopArray);
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
    public function visitConst(Node $node) : Context
    {
        $context = $this->context;
        try {
            $nameNode = $node->children['name'];
            // Based on UnionTypeVisitor::visitConst
            if ($nameNode->kind == \ast\AST_NAME) {
                $constant = (new ContextNode(
                    $this->code_base,
                    $context,
                    $node
                ))->getConst();

                // Mark that this constant has been referenced from
                // this context
                $constant->addReference($context);
            }
        } catch (IssueException $exception) {
            // We need to do this in order to check keys and (after the first 5) values in AST arrays.
            // Other parts of the AST may also not be covered.
            // (This issue may be a duplicate)
            Issue::maybeEmitInstance(
                $this->code_base,
                $context,
                $exception->getIssueInstance()
            );
        } catch (\Exception $exception) {
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
    public function visitClassConst(Node $node) : Context
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
        } catch (\Exception $exception) {
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
    public function visitClosure(Node $node) : Context
    {
        $func = $this->context->getFunctionLikeInScope($this->code_base);

        $return_type = $func->getUnionType();

        if (!$return_type->isEmpty()
            && !$func->getHasReturn()
            && !$this->declOnlyThrows($node)
            && !$return_type->hasType(VoidType::instance(false))
            && !$return_type->hasType(NullType::instance(false))
        ) {
            $this->emitIssue(
                Issue::TypeMissingReturn,
                $node->lineno ?? 0,
                (string)$func->getFQSEN(),
                (string)$return_type
            );
        }
        $this->analyzeNoOp($node, Issue::NoopClosure);
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
    public function visitReturn(Node $node) : Context
    {
        // Make sure we're actually returning from a method.
        if (!$this->context->isInFunctionLikeScope()) {
            return $this->context;
        }

        // Check real return types instead of phpdoc return types in traits for #800
        // TODO: Why did Phan originally not analyze return types of traits at all in 4c6956c05222e093b29393ceaa389ffb91041bdc
        $is_trait = false;
        if ($this->context->isInClassScope()) {
            $clazz = $this->context->getClassInScope($this->code_base);
            $is_trait = $clazz->isTrait();
        }


        // Get the method/function/closure we're in
        $method = $this->context->getFunctionLikeInScope($this->code_base);

        \assert(
            !empty($method),
            "We're supposed to be in either method or closure scope."
        );

        // Figure out what we intend to return
        // (For traits, lower the false positive rate by comparing against the real return type instead of the phpdoc type (#800))
        $method_return_type = $is_trait ? $method->getRealReturnType() : $method->getUnionType();

        // Figure out what is actually being returned
        foreach ($this->getReturnTypes($this->context, $node->children['expr']) as $expression_type) {
            if ($method->getHasYield()) {  // Function that is syntactically a Generator.
                continue;  // Analysis was completed in PreOrderAnalysisVisitor
            }
            // This leaves functions which aren't syntactically generators.

            // If there is no declared type, see if we can deduce
            // what it should be based on the return type
            if ($method_return_type->isEmpty()
                || $method->isReturnTypeUndefined()
            ) {
                if (!$is_trait) {
                    $method->setIsReturnTypeUndefined(true);

                    // Set the inferred type of the method based
                    // on what we're returning
                    $method->getUnionType()->addUnionType($expression_type);
                }

                // No point in comparing this type to the
                // type we just set
                continue;
            }

            // C
            if (!$method->isReturnTypeUndefined()
                && !$expression_type->canCastToExpandedUnionType(
                    $method_return_type,
                    $this->code_base
                )
            ) {
                $this->emitIssue(
                    Issue::TypeMismatchReturn,
                    $node->lineno ?? 0,
                    (string)$expression_type,
                    $method->getName(),
                    (string)$method_return_type
                );
            }
            // For functions that aren't syntactically Generators,
            // update the set/existence of return values.

            if ($method->isReturnTypeUndefined()) {
                // Add the new type to the set of values returned by the
                // method
                $method->getUnionType()->addUnionType($expression_type);
            }

            // Mark the method as returning something (even if void)
            if (null !== $node->children['expr']) {
                $method->setHasReturn(true);
            }
        }

        return $this->context;
    }

    /**
     * @return \Generator|UnionType[]
     */
    private function getReturnTypes(Context $context, $node)
    {
        if (!($node instanceof Node)) {
            if (null === $node) {
                yield VoidType::instance(false)->asUnionType();
                return;
            }
            yield UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $context,
                $node,
                true
            );
            return;
        }
        $kind = $node->kind;
        if ($kind === \ast\AST_CONDITIONAL) {
            yield from self::deduplicateUnionTypes($this->getReturnTypesOfConditional($context, $node));
            return;
        } elseif ($kind === \ast\AST_ARRAY) {
            foreach (self::deduplicateUnionTypes($this->getReturnTypesOfArray($context, $node)) as $elem_type) {
                yield $elem_type->asGenericArrayTypes();
            }
            return;
        }

        $expression_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $context,
            $node,
            true
        );

        if ($expression_type->hasStaticType()) {
            $expression_type =
                $expression_type->withStaticResolvedInContext(
                    $context
                );
        }
        yield $expression_type;
    }

    /**
     * @return \Generator|UnionType[]
     */
    private function getReturnTypesOfConditional(Context $context, Node $node)
    {
        $cond_node = $node->children['cond'];
        $cond_truthiness = UnionTypeVisitor::checkCondUnconditionalTruthiness($cond_node);
        // For the shorthand $a ?: $b, the cond node will be the truthy value.
        // Note: an ast node will never be null(can be unset), it will be a const AST node with the name null.
        $true_node = $node->children['true'] ?? $cond_node;

        // Rarely, a conditional will always be true or always be false.
        if ($cond_truthiness !== null) {
            // TODO: Add no-op checks in another PR, if they don't already exist for conditional.
            if ($cond_truthiness === true) {
                // The condition is unconditionally true
                yield from $this->getReturnTypes($context, $true_node);
                return;
            } else {
                // The condition is unconditionally false

                // Add the type for the 'false' side
                yield from $this->getReturnTypes($context, $node->children['false']);
                return;
            }
        }

        // TODO: false_context once there is a NegatedConditionVisitor
        // TODO: emit no-op if $cond_node is a literal, such as `if (2)`
        // - Also note that some things such as `true` and `false` are \ast\AST_NAME nodes.

        if ($cond_node instanceof Node) {
            // TODO: Use different contexts and merge those, in case there were assignments or assignments by reference in both sides of the conditional?
            // Reuse the BranchScope (sort of unintuitive). The ConditionVisitor returns a clone and doesn't modify the original.
            $base_context = $this->context;
            // We don't bother analyzing visitReturn in PostOrderAnalysisVisitor, right now.
            // This may eventually change, just to ensure the expression is checked for issues
            assert($base_context->isInFunctionLikeScope());
            $true_context = (new ConditionVisitor(
                $this->code_base,
                $base_context
            ))($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))($cond_node);
        } else {
            $true_context = $context;
            $false_context = $this->context;
        }

        // Allow nested ternary operators, or arrays within ternary operators
        yield from $this->getReturnTypes($true_context, $true_node);

        yield from $this->getReturnTypes($false_context, $node->children['false']);
    }

    /**
     * @param \Generator|UnionType[] $types
     * @return \Generator|UnionType[]
     * @suppress PhanPluginUnusedVariable
     */
    private static function deduplicateUnionTypes($types)
    {
        $unique_types = [];
        foreach ($types as $type) {
            foreach ($unique_types as $old_type) {
                if ($type->isEqualTo($old_type)) {
                    break;
                }
            }
            yield $type;
            $unique_types[] = $type;
        }
    }

    /**
     * @return \Generator|UnionType[]
     */
    private function getReturnTypesOfArray(Context $context, Node $node)
    {
        if (!empty($node->children)
            && $node->children[0] instanceof Node
            && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            for ($i=0; $i<5; $i++) {
                // Check to see if we're out of elements
                if (empty($node->children[$i])) {
                    return;
                }

                // Don't bother recursing more than one level to iterate over possible types.
                if ($node->children[$i]->children['value'] instanceof Node) {
                    yield UnionTypeVisitor::unionTypeFromNode(
                        $this->code_base,
                        $context,
                        $node->children[$i]->children['value'],
                        true
                    );
                } else {
                    yield Type::fromObject(
                        $node->children[$i]->children['value']
                    )->asUnionType();
                }
            }
            return;
        }
        yield ArrayType::instance(false)->asUnionType();
    }

    /**
     * @param Node $node (@phan-unused-param)
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropDecl(Node $node) : Context
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
    public function visitCall(Node $node) : Context
    {
        $expression = $node->children['expr'];
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode();

            foreach ($function_list_generator as $function) {
                assert($function instanceof FunctionInterface);
                // Check the call for parameter and argument types
                $this->analyzeCallToMethod(
                    $function,
                    $node
                );
            }
        } catch (CodeBaseException $e) {
            // ignore it.
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
    public function visitNew(Node $node) : Context
    {
        try {
            $context_node = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ));

            $method = $context_node->getMethod(
                '__construct',
                false,
                false
            );

            $class_list = $context_node->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);
            // Add a reference to each class this method
            // could be called on
            foreach ($class_list as $class) {
                $class->addReference($this->context);
                if ($class->isDeprecated()) {
                    $this->emitIssue(
                        Issue::DeprecatedClass,
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN(),
                        $class->getContext()->getFile(),
                        (string)$class->getContext()->getLineNumberStart()
                    );
                }
                foreach ($class->getInterfaceFQSENList() as $interface) {
                    $clazz = $this->code_base->getClassByFQSEN($interface);
                    if ($clazz->isDeprecated()) {
                        $this->emitIssue(
                            Issue::DeprecatedInterface,
                            $node->lineno ?? 0,
                            (string)$clazz->getFQSEN(),
                            $clazz->getContext()->getFile(),
                            (string)$clazz->getContext()->getLineNumberStart()
                        );
                    }
                }
                foreach ($class->getTraitFQSENList() as $trait) {
                    $clazz = $this->code_base->getClassByFQSEN($trait);
                    if ($clazz->isDeprecated()) {
                        $this->emitIssue(
                            Issue::DeprecatedTrait,
                            $node->lineno ?? 0,
                            (string)$clazz->getFQSEN(),
                            $clazz->getContext()->getFile(),
                            (string)$clazz->getContext()->getLineNumberStart()
                        );
                    }
                }
            }

            $this->analyzeMethodVisibility(
                $method,
                $node
            );

            $this->analyzeCallToMethod(
                $method,
                $node
            );

            foreach ($class_list as $class) {
                // Make sure we're not instantiating an abstract
                // class
                if ($class->isAbstract()
                    && (!$this->context->isInClassScope()
                    || $class->getFQSEN() != $this->context->getClassFQSEN())
                ) {
                    $this->emitIssue(
                        Issue::TypeInstantiateAbstract,
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN()
                    );
                }

                // Make sure we're not instantiating an interface
                if ($class->isInterface()) {
                    $this->emitIssue(
                        Issue::TypeInstantiateInterface,
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN()
                    );
                }
            }
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (\Exception $exception) {
            // If we can't figure out what kind of a call
            // this is, don't worry about it
            return $this->context;
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
    public function visitInstanceof(Node $node) : Context
    {
        try {
            // Fetch the class list, and emit warnings as a side effect.
            // TODO: Unify UnionTypeVisitor, AssignmentVisitor, and PostOrderAnalysisVisitor
            (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, Issue::TypeInvalidInstanceof);
        } catch (CodeBaseException $exception) {
            $this->emitIssue(
                Issue::UndeclaredClassInstanceof,
                $node->lineno ?? 0,
                (string)$exception->getFQSEN()
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
    public function visitStaticCall(Node $node) : Context
    {
        // Get the name of the method being called
        $method_name = $node->children['method'];

        // Give up on things like Class::$var
        if (!\is_string($method_name)) {
            return $this->context;
        }

        // Get the name of the static class being referenced
        $static_class = '';
        if ($node->children['class']->kind == \ast\AST_NAME) {
            $static_class = $node->children['class']->children['name'];
        }

        $method = $this->getStaticMethodOrEmitIssue($node);

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
            // Get the method that's calling the static method
            $calling_method = null;
            if ($this->context->isInMethodScope()) {
                $calling_function_like =
                    $this->context->getFunctionLikeInScope($this->code_base);

                if ($calling_function_like instanceof Method) {
                    $calling_method = $calling_function_like;
                }
            }

            // If the method being called isn't actually static and it's
            // not a call to parent::f from f, we may be in trouble.
            if (!$method->isStatic()

                // Allow static calls to parent if we're not in a static
                // method or if it's to the overridden method
                && !(
                    (
                        'parent' === $static_class
                        || 'self' === $static_class
                        || 'static' === $static_class
                    )
                    && $this->context->isInMethodScope()
                    && (
                        $this->context->getFunctionLikeFQSEN()->getName() == $method->getFQSEN()->getName()
                        || ($calling_method && !$calling_method->isStatic())
                    )

                // Allow static calls to methods from non-static class methods
                ) && !(
                    $this->context->isInClassScope()
                    && $this->context->isInFunctionLikeScope()
                    && ($calling_method && !$calling_method->isStatic())
                // Allow static calls parent methods from closure
                ) && !(
                    $this->context->isInClassScope()
                    && $this->context->isInFunctionLikeScope()
                    && $this->context->getFunctionLikeFQSEN()->isClosure()
                )
            ) {
                $class_list = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node->children['class']
                ))->getClassList();

                if (!empty($class_list)) {
                    $class = \array_values($class_list)[0];

                    $this->emitIssue(
                        Issue::StaticCallToNonStatic,
                        $node->lineno ?? 0,
                        "{$class->getFQSEN()}::{$method_name}()",
                        $method->getFileRef()->getFile(),
                        (string)$method->getFileRef()->getLineNumberStart()
                    );
                }
            }

            $this->analyzeMethodVisibility(
                $method,
                $node
            );

            // Make sure the parameters look good
            $this->analyzeCallToMethod(
                $method,
                $node
            );
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (\Exception $exception) {
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
        return $this->context;
    }

    /**
     * Check calling A::__construct (where A is not parent)
     * @return void
     */
    private function checkNonAncestorConstructCall(
        Node $node,
        string $static_class,
        string $method_name
    ) {
        // TODO: what about unanalyzable?
        if ($node->children['class']->kind !== \ast\AST_NAME) {
            return;
        }
        $class_context_node = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['class']
        ));
        // TODO: check for self/static/<class name of self> and warn about recursion?
        // TODO: Only allow calls to __construct from other constructors?
        $found_ancestor_constructor = false;
        if ($this->context->isInMethodScope()) {
            $possible_ancestor_type = $class_context_node->getClassUnionType();
            // If we can determine the ancestor type, and it's an parent/ancestor class, allow the call without warning.
            // (other code should check visibility and existence and args of __construct)

            if (!$possible_ancestor_type->isEmpty()) {
                // but forbid 'self::__construct', 'static::__construct'
                $type = $this->context->getClassFQSEN()->asUnionType();
                if ($possible_ancestor_type->hasStaticType()) {
                    $this->emitIssue(
                        Issue::AccessOwnConstructor,
                        $node->lineno ?? 0,
                        $static_class
                    );
                    $found_ancestor_constructor = true;
                } elseif ($type->asExpandedTypes($this->code_base)->canCastToUnionType($possible_ancestor_type)) {
                    if ($type->canCastToUnionType($possible_ancestor_type)) {
                        $this->emitIssue(
                            Issue::AccessOwnConstructor,
                            $node->lineno ?? 0,
                            $static_class
                        );
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
     * @return void
     */
    private function emitConstructorWarning(Node $node, string $static_class, string $method_name)
    {
        $this->emitIssue(
            Issue::UndeclaredStaticMethod,
            $node->lineno ?? 0,
            "{$static_class}::{$method_name}()"
        );
    }

    /**
     * gets the static method, or emits an issue.
     * @return Method|null
     */
    private function getStaticMethodOrEmitIssue(Node $node)
    {
        $method_name = $node->children['method'];

        try {
            // Get a reference to the method being called
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, true, true);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (\Exception $exception) {
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
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Node $node) : Context
    {
        \assert(
            $this->context->isInFunctionLikeScope(),
            "Must be in function-like scope to get method"
        );

        $method = $this->context->getFunctionLikeInScope($this->code_base);

        $return_type = $method->getUnionType();

        \assert(
            $method instanceof Method,
            "Function found where method expected"
        );

        $has_interface_class = false;
        if ($method instanceof Method) {
            try {
                $class = $method->getClass($this->code_base);
                $has_interface_class = $class->isInterface();
            } catch (\Exception $exception) {
            }

            if (!$method->isAbstract()
                && !$method->isFromPHPDoc()
                && !$has_interface_class
                && !$return_type->isEmpty()
                && !$method->getHasReturn()
                && !$this->declOnlyThrows($node)
                && !$return_type->hasType(VoidType::instance(false))
                && !$return_type->hasType(NullType::instance(false))
            ) {
                $this->emitIssue(
                    Issue::TypeMissingReturn,
                    $node->lineno ?? 0,
                    (string)$method->getFQSEN(),
                    (string)$return_type
                );
            }

            if ($method->isStatic()
                && $method->getUnionType()->hasTemplateType()
            ) {
                $this->emitIssue(
                    Issue::TemplateTypeStaticMethod,
                    $node->lineno ?? 0,
                    (string)$method->getFQSEN()
                );
            }
        }

        if ($method->getHasReturn() && $method->getIsMagicAndVoid()) {
            $this->emitIssue(
                Issue::TypeMagicVoidWithReturn,
                $node->lineno ?? 0,
                (string)$method->getFQSEN()
            );
        }

        $parameters_seen = [];
        foreach ($method->getParameterList() as $i => $parameter) {
            if (isset($parameters_seen[$parameter->getName()])) {
                $this->emitIssue(
                    Issue::ParamRedefined,
                    $node->lineno ?? 0,
                    '$' . $parameter->getName()
                );
            } else {
                $parameters_seen[$parameter->getName()] = $i;
            }
        }


        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_FUNC_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFuncDecl(Node $node) : Context
    {
        $method =
            $this->context->getFunctionLikeInScope($this->code_base);

        $return_type = $method->getUnionType();

        if (!$return_type->isEmpty()
            && !$method->getHasReturn()
            && !$this->declOnlyThrows($node)
            && !$return_type->hasType(VoidType::instance(false))
            && !$return_type->hasType(NullType::instance(false))
        ) {
            $this->emitIssue(
                Issue::TypeMissingReturn,
                $node->lineno ?? 0,
                (string)$method->getFQSEN(),
                (string)$return_type
            );
        }

        $parameters_seen = [];
        foreach ($method->getParameterList() as $i => $parameter) {
            if (isset($parameters_seen[$parameter->getName()])) {
                $this->emitIssue(
                    Issue::ParamRedefined,
                    $node->lineno ?? 0,
                    '$' . $parameter->getName()
                );
            } else {
                $parameters_seen[$parameter->getName()] = $i;
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
    public function visitMethodCall(Node $node) : Context
    {
        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return $this->context;
        }

        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return $this->context;
        } catch (NodeException $exception) {
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

        $this->analyzeMethodVisibility(
            $method,
            $node
        );

        // Check the call for parameter and argument types
        $this->analyzeCallToMethod(
            $method,
            $node
        );

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDim(Node $node) : Context
    {
        // Check the array type to trigger TypeArraySuspicious
        try {
            /* $array_type = */
            UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node,
                false
            );
            // TODO: check if array_type has array but not ArrayAccess.
            // If that is true, then assert that $dim_type can cast to `int|string`
        } catch (IssueException $exception) {
            // Detect this elsewhere, e.g. want to detect PhanUndeclaredVariableDim but not PhanUndeclaredVariable
        }
        // Check the dimension type to trigger PhanUndeclaredVariable, etc.
        /* $dim_type = */
        UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['dim'],
            true
        );
        return $this->context;
    }

    public function visitStaticProp(Node $node) : Context
    {
        return $this->analyzeProp($node, true);
    }

    public function visitProp(Node $node) : Context
    {
        return $this->analyzeProp($node, false);
    }

    /**
     * Analyze a node with kind `\ast\AST_PROP` or `\ast\AST_STATIC_PROP`
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
    public function analyzeProp(Node $node, bool $is_static) : Context
    {
        $exception_or_null = null;

        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getProperty($node->children['prop'], $is_static);

            // Mark that this property has been referenced from
            // this context
            $property->addReference($this->context);
        } catch (IssueException $exception) {
            // We'll check out some reasons it might not exist
            // before logging the issue
            $exception_or_null = $exception;
        } catch (\Exception $exception) {
            // Swallow any exceptions. We'll catch it later.
        }

        if (isset($property)) {
            $this->analyzeNoOp($node, Issue::NoopProperty);
        } else {
            \assert(
                isset($node->children['expr'])
                || isset($node->children['class']),
                "Property nodes must either have an expression or class"
            );

            $class_list = [];
            try {
                // Get the set of classes that are being referenced
                $class_list = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node->children['expr'] ?? $node->children['class']
                ))->getClassList(true);
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
                $has_getter =
                    \array_reduce($class_list, function ($carry, $class) {
                        return (
                            $carry ||
                            $class->hasGetMethod($this->code_base)
                        );
                    }, false);

                // If they don't, then analyze for Noops.
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
            }
        }

        return $this->context;
    }

    /**
     * Analyze whether a method is callable
     *
     * @param Method $method
     * @param Node $node
     *
     * @return void
     */
    private function analyzeMethodVisibility(
        Method $method,
        Node $node
    ) {
        if ($method->isPrivate()
            && (
                !$this->context->isInClassScope()
                || $this->context->getClassFQSEN() != $method->getDefiningClassFQSEN()
            )
        ) {
            $has_call_magic_method = !$method->isStatic()
                && $method->getDefiningClass($this->code_base)->hasMethodWithName($this->code_base, '__call');

            $this->emitIssue(
                $has_call_magic_method ?
                    Issue::AccessMethodPrivateWithCallMagicMethod : Issue::AccessMethodPrivate,
                $node->lineno ?? 0,
                (string)$method->getFQSEN(),
                $method->getFileRef()->getFile(),
                (string)$method->getFileRef()->getLineNumberStart()
            );
        } elseif ($method->isProtected() && !$this->canAccessProtectedMethodFromContext($method)) {
            $has_call_magic_method = !$method->isStatic()
                && $method->getDefiningClass($this->code_base)->hasMethodWithName($this->code_base, '__call');

            $this->emitIssue(
                $has_call_magic_method ?
                    Issue::AccessMethodProtectedWithCallMagicMethod : Issue::AccessMethodProtected,
                $node->lineno ?? 0,
                (string)$method->getFQSEN(),
                $method->getFileRef()->getFile(),
                (string)$method->getFileRef()->getLineNumberStart()
            );
        }
    }

    private function canAccessProtectedMethodFromContext(Method $method) : bool
    {
        $context = $this->context;
        if (!$context->isInClassScope()) {
            return false;
        }
        $class_fqsen = $context->getClassFQSEN();
        $class_fqsen_type = $class_fqsen->asType();
        $method_class_fqsen_type = $method->getClassFQSEN()->asType();
        if ($class_fqsen_type->canCastToType($method_class_fqsen_type)) {
            return true;
        }
        $method_defining_class_fqsen = $method->getDefiningClassFQSEN();
        if ($class_fqsen === $method_defining_class_fqsen) {
            return true;
        }
        $method_defining_class_fqsen_type = $method_defining_class_fqsen->asType();
        if ($class_fqsen_type->isSubclassOf($this->code_base, $method_defining_class_fqsen_type)) {
            return true;
        }
        if ($method_defining_class_fqsen_type->isSubclassOf($this->code_base, $class_fqsen_type)) {
            return true;
        }
        return false;
    }

    /**
     * Analyze the parameters and arguments for a call
     * to the given method or function
     *
     * @param FunctionInterface $method
     * @param Node $node
     *
     * @return void
     */
    private function analyzeCallToMethod(
        FunctionInterface $method,
        Node $node
    ) {
        $method->addReference($this->context);

        // Create variables for any pass-by-reference
        // parameters
        $argument_list = $node->children['args'];
        foreach ($argument_list->children as $i => $argument) {
            if (!$argument instanceof \ast\Node) {
                continue;
            }

            $parameter = $method->getParameterForCaller($i);
            if (!$parameter) {
                continue;
            }

            // If pass-by-reference, make sure the variable exists
            // or create it if it doesn't.
            if ($parameter->isPassByReference()) {
                if ($argument->kind == \ast\AST_VAR) {
                    try {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        $variable = (new ContextNode(
                            $this->code_base,
                            $this->context,
                            $argument
                        ))->getOrCreateVariable();
                    } catch (NodeException $e) {
                        // E.g. `function_accepting_reference(${$varName})` - Phan can't analyze outer type of ${$varName}
                        continue;
                    }
                } elseif ($argument->kind == \ast\AST_STATIC_PROP
                    || $argument->kind == \ast\AST_PROP
                ) {
                    $property_name = $argument->children['prop'];

                    if (\is_string($property_name)) {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        try {
                            (new ContextNode(
                                $this->code_base,
                                $this->context,
                                $argument
                            ))->getOrCreateProperty($argument->children['prop'], $argument->kind == \ast\AST_STATIC_PROP);
                        } catch (IssueException $exception) {
                            Issue::maybeEmitInstance(
                                $this->code_base,
                                $this->context,
                                $exception->getIssueInstance()
                            );
                        } catch (\Exception $exception) {
                            // If we can't figure out what kind of a call
                            // this is, don't worry about it
                        }
                    } else {
                        // This is stuff like `Class->$foo`. I'm ignoring
                        // it.
                    }
                }
            }
        }

        // Confirm the argument types are clean
        ArgumentType::analyze(
            $method,
            $node,
            $this->context,
            $this->code_base
        );

        // Take another pass over pass-by-reference parameters
        // and assign types to passed in variables
        foreach ($argument_list->children as $i => $argument) {
            if (!$argument instanceof \ast\Node) {
                continue;
            }
            $parameter = $method->getParameterForCaller($i);

            if (!$parameter) {
                continue;
            }

            if (Config::get_track_references()) {
                (new ArgumentVisitor(
                    $this->code_base,
                    $this->context
                ))($argument);
            }

            // If the parameter is pass-by-reference and we're
            // passing a variable in, see if we should pass
            // the parameter and variable types to eachother
            $variable = null;
            if ($parameter->isPassByReference()) {
                if ($argument->kind == \ast\AST_VAR) {
                    try {
                        $variable = (new ContextNode(
                            $this->code_base,
                            $this->context,
                            $argument
                        ))->getOrCreateVariable();
                    } catch (NodeException $e) {
                        // E.g. `function_accepting_reference(${$varName})` - Phan can't analyze outer type of ${$varName}
                        continue;
                    }
                } elseif ($argument->kind == \ast\AST_STATIC_PROP
                    || $argument->kind == \ast\AST_PROP
                ) {
                    $property_name = $argument->children['prop'];

                    if (\is_string($property_name)) {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        try {
                            $variable = (new ContextNode(
                                $this->code_base,
                                $this->context,
                                $argument
                            ))->getOrCreateProperty($argument->children['prop'], $argument->kind == \ast\AST_STATIC_PROP);
                        } catch (IssueException $exception) {
                            Issue::maybeEmitInstance(
                                $this->code_base,
                                $this->context,
                                $exception->getIssueInstance()
                            );
                        } catch (\Exception $exception) {
                            // If we can't figure out what kind of a call
                            // this is, don't worry about it
                        }
                    } else {
                        // This is stuff like `Class->$foo`. I'm ignoring
                        // it.
                    }
                }

                if ($variable) {
                    $reference_parameter_type = $parameter->getNonVariadicUnionType();
                    switch ($parameter->getReferenceType()) {
                        case Parameter::REFERENCE_WRITE_ONLY:
                            // The previous value is being ignored, and being replaced.
                            $variable->setUnionType(
                                $reference_parameter_type
                            );
                            break;
                        case Parameter::REFERENCE_READ_WRITE:
                            $variable_type = $variable->getUnionType();
                            if ($variable_type->isEmpty()) {
                                // if Phan doesn't know the variable type,
                                // then guess that the variable is the type of the reference
                                // when analyzing the following statements.
                                $variable->setUnionType(
                                    $reference_parameter_type
                                );
                            } elseif (!$variable_type->canCastToUnionType($reference_parameter_type)) {
                                // Phan already warned about incompatible types.
                                // But analyze the following statements as if it could have been the type expected,
                                // to reduce false positives.
                                $variable->getUnionType()->addUnionType(
                                    $reference_parameter_type
                                );
                            }
                            // don't modify - assume the function takes the same type in that it returns,
                            // and we want to preserve generic array types for sorting functions (May change later on)
                            // TODO: Check type compatibility earlier, and don't modify?
                            break;
                        case Parameter::REFERENCE_DEFAULT:
                        default:
                            // We have no idea what type of reference this is.
                            // Probably user defined code.
                            $variable->getUnionType()->addUnionType(
                                $reference_parameter_type
                            );
                            break;
                    }
                }
            }
        }

        // If we're in quick mode, don't retest methods based on
        // parameter types passed in
        if (Config::get_quick_mode()) {
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
     * Replace the method's parameter types with the argument
     * types and re-analyze the method.
     *
     * This is used when analyzing callbacks and closures, e.g. in array_map.
     *
     * @param UnionType[] $argument_types
     * An AST node listing the arguments
     *
     * @param FunctionInterface $method
     * The method or function being called
     *
     * @return void
     *
     * @see analyzeMethodWithArgumentTypes (Which takes AST nodes)
     */
    public function analyzeCallableWithArgumentTypes(
        array $argument_types,
        FunctionInterface $method
    ) {
        // Don't re-analyze recursive methods. That doesn't go well.
        if ($this->context->isInFunctionLikeScope()
            && $method->getFQSEN() === $this->context->getFunctionLikeFQSEN()
        ) {
            return;
        }

        $original_parameter_list = $method->getParameterList();
        if (\count($original_parameter_list) === 0) {
            return;  // No point in recursing if there's no changed parameters.
        }

        $original_method_scope = $method->getInternalScope();
        $method->setInternalScope(clone($original_method_scope));
        // Even though we don't modify the parameter list, we still need to know the types
        // -- as an optimization, we don't run quick mode again if the types didn't change?
        $parameter_list = \array_map(function (Variable $parameter) : Variable {
            return clone($parameter);
        }, $original_parameter_list);

        foreach ($parameter_list as $i => $parameter_clone) {
            assert($parameter_clone instanceof Parameter);
            // Add the parameter to the scope
            $method->getInternalScope()->addVariable(
                $parameter_clone
            );

            if (!isset($argument_types[$i]) && $parameter_clone->hasDefaultValue()) {
                $parameter_type = $parameter_clone->getDefaultValueType();
                if ($parameter_type->isType(NullType::instance(false))) {
                    // Treat a parameter default of null the same way as passing null to that parameter
                    // (Add null to the list of possibilities)
                    $parameter_clone->addUnionType($parameter_type);
                } else {
                    // For other types (E.g. string), just replace the union type.
                    $parameter_clone->setUnionType($parameter_type);
                }
            }

            // If there's no parameter at that offset, we may be in
            // a ParamTooMany situation. That is caught elsewhere.
            if (!isset($argument_types[$i])
                || !$parameter_clone->getNonVariadicUnionType()->isEmpty()
            ) {
                continue;
            }

            $this->updateParameterTypeByArgument(
                $method,
                $parameter_clone,
                null,  // TODO: Can array_map/array_filter accept closures with references? Consider warning?
                $argument_types[$i],
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
        $method->analyzeWithNewParams($method->getContext(), $this->code_base, $parameter_list);

        // Reset to the original parameter scope after
        // having tested the parameters with the types passed in
        $method->setParameterList($original_parameter_list);
        $method->setInternalScope($original_method_scope);
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
     *
     * @return void
     */
    private function analyzeMethodWithArgumentTypes(
        Node $argument_list_node,
        FunctionInterface $method
    ) {
        // Don't re-analyze recursive methods. That doesn't go
        // well.
        if ($this->context->isInFunctionLikeScope()
            && $method->getFQSEN() === $this->context->getFunctionLikeFQSEN()
        ) {
            return;
        }

        // Create a copy of the method's original scope so that we can reset it
        // after re-analyzing it.
        // Don't modify the parameter list - We use that to know when to emit PhanTypeMismatchArgument,
        // so issues would be incorrectly emitted if it was temporarily modified.
        $original_parameter_list = $method->getParameterList();

        if (\count($original_parameter_list) === 0) {
            return;  // No point in recursing if there's no changed parameters.
        }

        $original_method_scope = $method->getInternalScope();
        $method->setInternalScope(clone($original_method_scope));
        // Even though we don't modify the parameter list, we still need to know the types
        // -- as an optimization, we don't run quick mode again if the types didn't change?
        $parameter_list = \array_map(function (Variable $parameter) : Variable {
            return clone($parameter);
        }, $original_parameter_list);

        // always resolve all arguments outside of quick mode to detect undefined variables, other problems in call arguments.
        // Fixes https://github.com/phan/phan/issues/583
        $argument_types = [];
        foreach ($argument_list_node->children as $i => $argument) {
            if (!$argument) {
                continue;
            }
            // Determine the type of the argument at position $i
            $argument_types[$i] = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $argument,
                true
            );
        }

        foreach ($parameter_list as $i => $parameter_clone) {
            assert($parameter_clone instanceof Parameter);
            // Add the parameter to the scope
            $method->getInternalScope()->addVariable(
                $parameter_clone
            );
            $argument = $argument_list_node->children[$i] ?? null;

            if (!$argument
                && $parameter_clone->hasDefaultValue()
            ) {
                $parameter_type = $parameter_clone->getDefaultValueType();
                if ($parameter_type->isType(NullType::instance(false))) {
                    // Treat a parameter default of null the same way as passing null to that parameter
                    // (Add null to the list of possibilities)
                    $parameter_clone->addUnionType($parameter_type);
                } else {
                    // For other types (E.g. string), just replace the union type.
                    $parameter_clone->setUnionType($parameter_type);
                }
            }

            // If there's no parameter at that offset, we may be in
            // a ParamTooMany situation. That is caught elsewhere.
            if (!$argument
                || !$parameter_clone->getNonVariadicUnionType()->isEmpty()
            ) {
                continue;
            }

            $this->updateParameterTypeByArgument(
                $method,
                $parameter_clone,
                $argument,
                $argument_types[$i],
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
        $method->analyzeWithNewParams($method->getContext(), $this->code_base, $parameter_list);

        // Reset to the original parameter scope after
        // having tested the parameters with the types passed in
        $method->setParameterList($original_parameter_list);
        $method->setInternalScope($original_method_scope);
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
     * @param UnionType $argument_type
     * The type of $argument
     *
     * @param int $parameter_offset
     * The offset of the parameter on the method's
     * signature.
     *
     * @return void
     */
    private function updateParameterTypeByArgument(
        FunctionInterface $method,
        Variable $parameter,
        $argument,
        UnionType $argument_type,
        array &$parameter_list,
        int $parameter_offset
    ) {
        // Then set the new type on that parameter based
        // on the argument's type. We'll use this to
        // retest the method with the passed in types
        // TODO: if $argument_type is non-empty and !isType(NullType), instead use setUnionType?
        $parameter->getNonVariadicUnionType()->addUnionType(
            $argument_type
        );


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
        // parameter `&...$args`. Analysing that is going to
        // be a problem. Is it possible to create
        // `PassByReferenceVariableCollection extends Variable`
        // or something similar?
        if ($parameter->isVariadic()) {
            return;
        }

        if (!$argument instanceof \ast\Node) {
            return;
        }

        $variable = null;
        if ($argument->kind == \ast\AST_VAR) {
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $argument
            ))->getOrCreateVariable();
        } elseif ($argument->kind == \ast\AST_STATIC_PROP) {
            try {
                // TODO: shouldn't call getOrCreateProperty for a static property. You can't create a static property.
                $variable = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $argument
                ))->getOrCreateProperty(
                    $argument->children['prop'] ?? '',
                    true
                );
            } catch (UnanalyzableException $exception) {
                // Ignore it. There's nothing we can do. (E.g. the class name for the static property fetch couldn't be determined.
            }
        }

        // If we couldn't find a variable, give up
        if (!$variable) {
            return;
        }

        $pass_by_reference_variable =
            new PassByReferenceVariable(
                $parameter,
                $variable
            );

        // Add it to the (cloned) scope of the function wrapped
        // in a way that makes it addressable as the
        // parameter its mimicking
        $method->getInternalScope()->addVariable(
            $pass_by_reference_variable
        );
        $parameter_list[$parameter_offset] = $pass_by_reference_variable;
    }

    /**
     * @param Node $node
     * A node to check to see if it's a no-op
     *
     * @param string $issue_type
     * A message to emit if it's a no-op
     *
     * @return void
     */
    private function analyzeNoOp(Node $node, string $issue_type)
    {
        if ($this->parent_node instanceof Node &&
            $this->parent_node->kind == \ast\AST_STMT_LIST
        ) {
            $this->emitIssue(
                $issue_type,
                $node->lineno ?? 0
            );
        }
    }

    /**
     * @param Node $node
     * A decl to check to see if it's only effect
     * is the throw an exception
     *
     * @return bool
     * True when the decl can only throw an exception or return or exit()
     */
    private function declOnlyThrows(Node $node) : bool
    {
        return BlockExitStatusChecker::willUnconditionallyThrowOrReturn($node->children['stmts']);
    }
}
