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
use ast\Node\Decl;

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
     * @param Node $node
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
        $right_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
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
     * @param Node $node
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
     * @param Node $node
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
                $variable_name = $child_node->children['name'];

                // Ignore $$var type things
                if (!\is_string($variable_name)) {
                    continue;
                }

                // Don't worry about non-existent undeclared variables
                // in the global scope if configured to do so
                if (Config::getValue('ignore_undeclared_variables_in_global_scope')
                    && $this->context->isInGlobalScope()
                ) {
                    continue;
                }

                if (!$this->context->getScope()->hasVariableWithName($variable_name)
                    && !Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())
                ) {
                    $this->emitIssue(
                        Issue::UndeclaredVariable,
                        $child_node->lineno ?? 0,
                        $variable_name
                    );
                }
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
        $type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

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
        try {
            $nameNode = $node->children['name'];
            // Based on UnionTypeVisitor::visitConst
            if ($nameNode->kind == \ast\AST_NAME) {
                if (defined($nameNode->children['name'])) {
                    // Do nothing, this is an internal type such as `true` or `\ast\AST_NAME`
                } else {
                    $constant = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $node
                    ))->getConst();

                    // Mark that this constant has been referenced from
                    // this context
                    $constant->addReference($this->context);
                }
            }

        } catch (IssueException $exception) {
            // We need to do this in order to check keys and (after the first 5) values in AST arrays.
            // Other parts of the AST may also not be covered.
            // (This issue may be a duplicate)
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
        // constant
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
     * @param Decl $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Decl $node) : Context
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

        \assert(!empty($method),
            "We're supposed to be in either method or closure scope.");

        // Figure out what we intend to return
        // (For traits, lower the false positive rate by comparing against the real return type instead of the phpdoc type (#800))
        $method_return_type = $is_trait ? $method->getRealReturnType() : $method->getUnionType();

        // Figure out what is actually being returned
        $expression_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        if (null === $node->children['expr']) {
            $expression_type = VoidType::instance(false)->asUnionType();
        }

        if ($expression_type->hasStaticType()) {
            $expression_type =
                $expression_type->withStaticResolvedInContext(
                    $this->context
                );
        }

        if ($method->getHasYield()) {  // Function that is syntactically a Generator.
            return $this->context;  // Analysis was completed in PreOrderAnalysisVisitor
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
            return $this->context;
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

        if ($expression->kind == \ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getVariableName();

            if (empty($variable_name)) {
                return $this->context;
            }

            // $var() - hopefully a closure, otherwise we don't know
            if ($this->context->getScope()->hasVariableWithName(
                $variable_name
            )) {
                $variable = $this->context->getScope()
                    ->getVariableByName($variable_name);

                $union_type = $variable->getUnionType();
                if ($union_type->isEmpty()) {
                    return $this->context;
                }

                foreach ($union_type->getTypeSet() as $type) {
                    // TODO: Allow CallableType to have FQSENs as well, e.g. `$x = [MyClass::class, 'myMethod']` has an FQSEN in a sense.
                    if (!($type instanceof ClosureType)) {
                        continue;
                    }

                    $closure_fqsen =
                        FullyQualifiedFunctionName::fromFullyQualifiedString(
                            (string)$type->asFQSEN()
                        );

                    if ($this->code_base->hasFunctionWithFQSEN(
                        $closure_fqsen
                    )) {
                        // Get the closure
                        $function = $this->code_base->getFunctionByFQSEN(
                            $closure_fqsen
                        );

                        // Check the call for paraemter and argument types
                        $this->analyzeCallToMethod(
                            $this->code_base,
                            $function,
                            $node
                        );
                    }
                }
            }
        } elseif ($expression->kind == \ast\AST_NAME
            // nothing to do
        ) {
            try {
                $method = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $expression
                ))->getFunction(
                    $expression->children['name']
                        ?? $expression->children['method']
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }

            // Check the call for paraemter and argument types
            $this->analyzeCallToMethod(
                $this->code_base,
                $method,
                $node
            );
        } elseif ($expression->kind == \ast\AST_CALL
            || $expression->kind == \ast\AST_STATIC_CALL
            || $expression->kind == \ast\AST_NEW
            || $expression->kind == \ast\AST_METHOD_CALL
        ) {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getClassList();

            foreach ($class_list as $class) {
                if (!$class->hasMethodWithName(
                    $this->code_base,
                    '__invoke'
                )) {
                    continue;
                }

                $method = $class->getMethodByNameInContext(
                    $this->code_base,
                    '__invoke',
                    $this->context
                );

                // Check the call for parameter and argument types
                $this->analyzeCallToMethod(
                    $this->code_base,
                    $method,
                    $node
                );
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

            // Add a reference to each class this method
            // could be called on
            foreach ($context_node->getClassList() as $class) {
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
                foreach($class->getInterfaceFQSENList() as $interface) {
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
                foreach($class->getTraitFQSENList() as $trait) {
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

            $this->analyzeCallToMethod(
                $this->code_base,
                $method,
                $node
            );

            $class_list = $context_node->getClassList();
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
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList();
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
                $this->code_base,
                $method,
                $node
            );

            // Make sure the parameters look good
            $this->analyzeCallToMethod(
                $this->code_base,
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
                } else if ($type->asExpandedTypes($this->code_base)->canCastToUnionType($possible_ancestor_type)) {
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
     * @param Decl $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Decl $node) : Context
    {
        \assert($this->context->isInFunctionLikeScope(),
            "Must be in function-like scope to get method");

        $method = $this->context->getFunctionLikeInScope($this->code_base);

        $return_type = $method->getUnionType();

        \assert($method instanceof Method,
            "Function found where method expected");

        $has_interface_class = false;
        if ($method instanceof Method) {
            try {
                $class = $method->getClass($this->code_base);
                $has_interface_class = $class->isInterface();
            } catch (\Exception $exception) {

            }

            if (!$method->isAbstract()
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
     * @param Decl $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFuncDecl(Decl $node) : Context
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
            $this->code_base,
            $method,
            $node
        );

        // Check the call for paraemter and argument types
        $this->analyzeCallToMethod(
            $this->code_base,
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
        // Check the array type to trigger
        // TypeArraySuspicious
        try {
            $array_type = UnionTypeVisitor::unionTypeFromNode(
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
        $dim_type = UnionTypeVisitor::unionTypeFromNode(
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

            \assert(isset($node->children['expr'])
                || isset($node->children['class']),
                    "Property nodes must either have an expression or class");

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
                    \array_reduce($class_list, function($carry, $class) {
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
     * @param CodeBase $code_base
     * @param Method $method
     * @param Node $node
     *
     * @return void
     */
    private function analyzeMethodVisibility(
        CodeBase $code_base,
        Method $method,
        Node $node
    ) {
        if (
            $method->isPrivate()
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
        } else if (
            $method->isProtected()
            && (
                !$this->context->isInClassScope()
                || (
                    !$this->context->getClassFQSEN()->asType()->canCastToType(
                        $method->getClassFQSEN()->asType()
                    )
                    && !$this->context->getClassFQSEN()->asType()->isSubclassOf(
                        $code_base,
                        $method->getDefiningClassFQSEN()->asType()
                    )
                )
                && $this->context->getClassFQSEN() != $method->getDefiningClassFQSEN()
            )
        ) {
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

    /**
     * Analyze the parameters and arguments for a call
     * to the given method or function
     *
     * @param CodeBase $code_base
     * @param FunctionInterface $method
     * @param Node $node
     *
     * @return void
     */
    private function analyzeCallToMethod(
        CodeBase $code_base,
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
                    // We don't do anything with it; just create it
                    // if it doesn't exist
                    $variable = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $argument
                    ))->getOrCreateVariable();

                } elseif ($argument->kind == \ast\AST_STATIC_PROP
                    || $argument->kind == \ast\AST_PROP
                ) {
                    $property_name = $argument->children['prop'];

                    if (\is_string($property_name)) {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        try {
                            $property = (new ContextNode(
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
                    $variable = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $argument
                    ))->getOrCreateVariable();
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
                        } else if (!$variable_type->canCastToUnionType($reference_parameter_type)) {
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
            $code_base, $node->children['args'], $method
        );
    }

    /**
     * Replace the method's parameter types with the argument
     * types and re-analyze the method.
     *
     * @param CodeBase $code_base
     * The code base in which the method call was found
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
        CodeBase $code_base,
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

        // Create a copy of the method's original parameter list
        // and scope so that we can reset it after re-analyzing
        // it.
        $original_method_scope = clone($method->getInternalScope());
        $original_parameter_list = \array_map(function (Variable $parameter) : Variable {
            return clone($parameter);
        }, $method->getParameterList());

        if (\count($original_parameter_list) === 0) {
            return;  // No point in recursing if there's no changed parameters.
        }

        // always resolve all arguments outside of quick mode to detect undefined variables, other problems in call arguments.
        // Fixes https://github.com/etsy/phan/issues/583
        $argument_types = [];
        foreach ($argument_list_node->children as $i => $argument) {
            if (!$argument) {
                continue;
            }
            // Determine the type of the argument at position $i
            $argument_types[$i] = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $argument
            );
        }

        // Get the list of parameters on the method
        $parameter_list = $method->getParameterList();

        foreach ($parameter_list as $i => $parameter) {

            $argument = $argument_list_node->children[$i] ?? null;

            if (!$argument
                && $parameter->hasDefaultValue()
            ) {
                $parameter_list = $method->getParameterList();
                $parameter_list[$i] = clone($parameter);
                $parameter_type = $parameter->getDefaultValueType();
                if ($parameter_type->isType(NullType::instance(false))) {
                    // Treat a parameter default of null the same way as passing null to that parameter
                    // (Add null to the list of possibilities)
                    $parameter_list[$i]->addUnionType($parameter_type);
                } else {
                    // For other types (E.g. string), just replace the union type.
                    $parameter_list[$i]->setUnionType($parameter_type);
                }
                $method->setParameterList($parameter_list);
            }

            // If there's no parameter at that offset, we may be in
            // a ParamTooMany situation. That is caught elsewhere.
            if (!$argument
                || !$parameter->getNonVariadicUnionType()->isEmpty()
            ) {
                continue;
            }

            $this->updateParameterTypeByArgument(
                $method,
                $parameter,
                $argument,
                $argument_types[$i],
                $i
            );
        }

        // Now that we know something about the parameters used
        // to call the method, we can reanalyze the method with
        // the types of the parameter
        $method->analyzeWithNewParams($method->getContext(), $code_base);

        // Reset to the original parameter list and scope after
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
        } else if ($argument->kind == \ast\AST_STATIC_PROP) {
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

        // Substitute the new type in for the parameter's type
        $parameter_list = $method->getParameterList();
        $parameter_list[$parameter_offset] =
            $pass_by_reference_variable;
        $method->setParameterList($parameter_list);

        // Add it to the scope of the function wrapped
        // in a way that makes it addressable as the
        // parameter its mimicking
        $method->getInternalScope()->addVariable(
            $pass_by_reference_variable
        );
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
     * @param Decl $node
     * A decl to check to see if it's only effect
     * is the throw an exception
     *
     * @return bool
     * True when the decl can only throw an exception
     */
    private function declOnlyThrows(Decl $node) {
        $stmts = $node->children['stmts'] ?? null;
        return isset($stmts)
            && $stmts->kind === \ast\AST_STMT_LIST
            && \count($stmts->children) === 1
            && $stmts->children[0]->kind === \ast\AST_THROW;
    }
}
