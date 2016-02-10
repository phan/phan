<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\Phan;
use \Phan\AST\ContextNode;
use \Phan\AST\UnionTypeVisitor;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\IssueException;
use \Phan\Exception\NodeException;
use \Phan\Exception\TypeException;
use \Phan\Issue;
use \Phan\Langauge\Type;
use \Phan\Language\Context;
use \Phan\Language\Element\ClassConstant;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\FunctionInterface;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Func;
use \Phan\Language\Element\PassByReferenceVariable;
use \Phan\Language\Element\Property;
use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\Type\CallableType;
use \Phan\Language\Type\NullType;
use \Phan\Language\Type\VoidType;
use \Phan\Language\UnionType;
use \ast\Node;
use \ast\Node\Decl;

class PostOrderAnalysisVisitor extends KindVisitorImplementation
{
    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

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
        $this->code_base = $code_base;
        $this->context = $context;
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

        assert(
            $node->children['var'] instanceof Node,
            "Expected left side of assignment to be a var in {$this->context}"
        );

        // Handle the assignment based on the type of the
        // right side of the equation and the kind of item
        // on the left
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type
        ))($node->children['var']);

        // Analyze the assignment for compatibility with some
        // breaking changes betweeen PHP5 and PHP7.
        (new ContextNode(
            $this->code_base,
            $this->context,
            $node
        ))->analyzeBackwardCompatibility();

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
    public function visitList(Node $node) : Context
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
    public function visitIfElem(Node $node) : Context
    {
        if (!isset($node->children['cond'])
            || !($node->children['cond'] instanceof Node)
        ) {
            return $this->context;
        }

        // Get the type just to make sure everything
        // is defined.
        $expression_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['cond']
        );

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
            Issue::emit(
                Issue::TypeMismatchForeach,
                $this->context->getFile(),
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

        if ($type->isType(ArrayType::instance())
            || $type->isGenericArray()
        ) {
            Issue::emit(
                Issue::TypeConversionFromArray,
                $this->context->getFile(),
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
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getConst();

            // Mark that this constant has been referenced from
            // this context
            $constant->addReference($this->context);

        } catch (\Exception $exception) {
            // Swallow any exceptions. We'll log the errors
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

        } catch (\Exception $exception) {
            // Swallow any exceptions. We'll log the errors
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
    public function visitClosure(Decl $node) : Context
    {
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
        // Don't check return types in traits
        if ($this->context->isInClassScope()) {
            $clazz = $this->context->getClassInScope($this->code_base);
            if ($clazz->isTrait()) {
                return $this->context;
            }
        }

        // Make sure we're actually returning from a method.
        if (!$this->context->isMethodScope()
            && !$this->context->isClosureScope()) {
            return $this->context;
        }

        // Get the method/function/closure we're in
        $method = null;
        if ($this->context->isClosureScope()) {
            $method = $this->context->getClosureInScope($this->code_base);
        } elseif ($this->context->isMethodScope()) {
            $method = $this->context->getMethodInScope($this->code_base);
        }

        assert(
            !empty($method),
            "We're supposed to be in either method or closure scope."
        );

        // Figure out what we intend to return
        $method_return_type = $method->getUnionType();

        // Figure out what is actually being returned
        $expression_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        // If there is no declared type, see if we can deduce
        // what it should be based on the return type
        if ($method_return_type->isEmpty()
            || $method->isReturnTypeUndefined()
        ) {
            $method->setIsReturnTypeUndefined(true);

            // Set the inferred type of the method based
            // on what we're returning
            $method->getUnionType()->addUnionType($expression_type);

            // No point in comparing this type to the
            // type we just set
            return $this->context;
        }

        if (!$method->isReturnTypeUndefined()
            && !$expression_type->canCastToExpandedUnionType(
                $method_return_type,
                $this->code_base
            )
        ) {
            Issue::emit(
                Issue::TypeMismatchReturn,
                $this->context->getFile(),
                $node->lineno ?? 0,
                (string)$expression_type,
                $method->getName(),
                (string)$method_return_type
            );
        }

        if ($method->isReturnTypeUndefined()) {
            // Add the new type to the set of values returned by the
            // method
            $method->getUnionType()->addUnionType($expression_type);
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

        (new ContextNode(
            $this->code_base,
            $this->context,
            $node
        ))->analyzeBackwardCompatibility();

        foreach ($node->children['args']->children ?? [] as $arg_node) {
            if ($arg_node instanceof Node) {
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $arg_node
                ))->analyzeBackwardCompatibility();
            }
        }

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
                    ->getVariableWithName($variable_name);

                $union_type = $variable->getUnionType();
                if ($union_type->isEmpty()) {
                    return $this->context;
                }

                foreach ($union_type->getTypeSet() as $type) {
                    if (!($type instanceof CallableType)) {
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
                Phan::getIssueCollector()->collectIssue($exception->getIssueInstance());
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

                // Check the call for paraemter and argument types
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
                false
            );

            // Add a reference to each class this method
            // could be called on
            foreach ($context_node->getClassList() as $class) {
                $class->addReference($this->context);

                if ($class->isDeprecated()) {
                    Issue::emit(
                        Issue::DeprecatedClass,
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN(),
                        $class->getContext()->getFile(),
                        $class->getContext()->getLineNumberStart()
                    );
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
                    && (!$this->context->hasClassFQSEN()
                    || $class->getFQSEN() != $this->context->getClassFQSEN())
                ) {
                    Issue::emit(
                        Issue::TypeInstantiateAbstract,
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN()
                    );
                }

                // Make sure we're not instantiating an interface
                if ($class->isInterface()) {
                    Issue::emit(
                        Issue::TypeInstantiateInterface,
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        (string)$class->getFQSEN()
                    );
                }

            }


        } catch (IssueException $exception) {
            Phan::getIssueCollector()->collectIssue($exception->getIssueInstance());
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
            Issue::emit(
                Issue::UndeclaredClassInstanceof,
                $this->context->getFile(),
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
        if (!is_string($method_name)) {
            return $this->context;
        }

        // Get the name of the static class being referenced
        $static_class = '';
        if ($node->children['class']->kind == \ast\AST_NAME) {
            $static_class = $node->children['class']->children['name'];
        }

        // Short circuit on a constructor being called statically
        // on something other than 'parent'
        if ($method_name === '__construct') {
            if ($static_class !== 'parent') {
                Issue::emit(
                    Issue::UndeclaredStaticMethod,
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    "{$static_class}::{$method_name}()"
                );
            }

            return $this->context;
        }

        try {
            // Get a reference to the method being called
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, true);

            // If the method isn't static and we're not calling
            // it on 'parent', we're in a bad spot.
            if (!$method->isStatic() && 'parent' !== $static_class) {

                $class_list = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node->children['class']
                ))->getClassList();

                if (!empty($class_list)) {
                    $class = array_values($class_list)[0];
                    Issue::emit(
                        Issue::StaticCallToNonStatic,
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        "{$class->getFQSEN()}::{$method_name}()",
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart()
                    );
                }
            }

            // Make sure the parameters look good
            $this->analyzeCallToMethod(
                $this->code_base,
                $method,
                $node
            );
        } catch (IssueException $exception) {
            Phan::getIssueCollector()->collectIssue($exception->getIssueInstance());

        } catch (\Exception $exception) {

            // If we can't figure out the class for this method
            // call, cry YOLO and mark every method with that
            // name with a reference.
            if (Config::get()->dead_code_detection
                && Config::get()->dead_code_detection_prefer_false_negative
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
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Decl $node) : Context
    {
        $method =
            $this->context->getMethodInScope($this->code_base);

        $return_type = $method->getUnionType();

        assert($method instanceof Method,
            "Function found where method expected at {$this->context}");

        $has_interface_class = false;
        if ($method instanceof Method) {
            try {
                $class = $method->getDefiningClass($this->code_base);
                $has_interface_class = $class->isInterface();
            } catch (\Exception $exception) {

            }
        }

        if ($method instanceof Method) {
            if (!$method->isAbstract()
                && !$has_interface_class
                && !$return_type->isEmpty()
                && !$method->getHasReturn()
                && !$return_type->hasType(VoidType::instance())
                && !$return_type->hasType(NullType::instance())
            ) {
                Issue::emit(
                    Issue::TypeMissingReturn,
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    $method->getFQSEN(),
                    (string)$return_type
                );
            }
        }

        if ($method instanceof Func) {
            if (!$return_type->isEmpty()
                && !$method->getHasReturn()
                && !$return_type->hasType(VoidType::instance())
                && !$return_type->hasType(NullType::instance())
            ) {
                Issue::emit(
                    Issue::TypeMissingReturn,
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    $method->getFQSEN(),
                    (string)$return_type
                );
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
    public function visitFuncDecl(Decl $node) : Context
    {
        $method =
            $this->context->getMethodInScope($this->code_base);

        $return_type = $method->getUnionType();

        if (!$return_type->isEmpty()
            && !$method->getHasReturn()
            && !$return_type->hasType(VoidType::instance())
            && !$return_type->hasType(NullType::instance())
        ) {
            Issue::emit(
                Issue::TypeMissingReturn,
                $this->context->getFile(),
                $node->lineno ?? 0,
                $method->getFQSEN(),
                (string)$return_type
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
    public function visitMethodCall(Node $node) : Context
    {
        $method_name = $node->children['method'];

        if (!is_string($method_name)) {
            return $this->context;
        }

        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false);


        } catch (IssueException $exception) {
            Phan::getIssueCollector()->collectIssue($exception->getIssueInstance());
            return $this->context;
        } catch (NodeException $exception) {

            // If we can't figure out the class for this method
            // call, cry YOLO and mark every method with that
            // name with a reference.
            if (Config::get()->dead_code_detection
                && Config::get()->dead_code_detection_prefer_false_negative
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
        if (!Config::get()->backward_compatibility_checks) {
            return $this->context;
        }

        if (!($node->children['expr'] instanceof Node
            && ($node->children['expr']->children['name'] ?? null) instanceof Node)
        ) {
            return $this->context;
        }

        // check for $$var[]
        if ($node->children['expr']->kind == \ast\AST_VAR
            && $node->children['expr']->children['name']->kind == \ast\AST_VAR
        ) {
            $temp = $node->children['expr']->children['name'];
            $depth = 1;
            while ($temp instanceof Node) {
                assert(
                    isset($temp->children['name']),
                    "Expected to find a name at {$this->context}, something else found."
                );
                $temp = $temp->children['name'];
                $depth++;
            }
            $dollars = str_repeat('$', $depth);
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if (strpos($line, '{') === false
                || strpos($line, '}') === false
            ) {
                Issue::emit(
                    Issue::CompatibleExpressionPHP7,
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    "{$dollars}{$temp}[]"
                );
            }

        // $foo->$bar['baz'];
        } elseif (!empty($node->children['expr']->children[1])
            && ($node->children['expr']->children[1] instanceof Node)
            && ($node->children['expr']->kind == \ast\AST_PROP)
            && ($node->children['expr']->children[0]->kind == \ast\AST_VAR)
            && ($node->children['expr']->children[1]->kind == \ast\AST_VAR)
        ) {
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if (strpos($line, '{') === false
                || strpos($line, '}') === false
            ) {
                Issue::emit(
                    Issue::CompatiblePHP7,
                    $this->context->getFile(),
                    $node->lineno ?? 0
                );
            }
        }

        return $this->context;
    }

    public function visitStaticProp(Node $node) : Context
    {
        return $this->visitProp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitProp(Node $node) : Context
    {
        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getProperty($node->children['prop']);

            // Mark that this property has been referenced from
            // this context
            $property->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow any exceptions. We'll log the errors
            // elsewhere.
        }

        if (isset($property)) {
            $this->analyzeNoOp($node, Issue::NoopProperty);
        } else {

            // Get the set of classes that are being referenced
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getClassList(true);

            // Find out of any of them have a __get magic method
            $has_getter =
                array_reduce($class_list, function($carry, $class) {
                    return (
                        $carry ||
                        $class->hasMethodWithName(
                            $this->code_base,
                            '__get'
                        )
                    );
                }, false);

            // If they don't, then analyze for Noops.
            if (!$has_getter) {
                $this->analyzeNoOp($node, Issue::NoopProperty);
            }
        }

        return $this->context;
    }

    /**
     * Analyze the parameters and arguments for a call
     * to the given method or function
     *
     * @param CodeBase $code_base
     * @param Method $method
     * @param Node $node
     *
     * @return null
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
            $parameter = $method->getParameterList()[$i] ?? null;

            if (!$parameter || !is_object($argument)) {
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

                    if (is_string($property_name)) {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        try {
                            $property = (new ContextNode(
                                $this->code_base,
                                $this->context,
                                $argument
                            ))->getOrCreateProperty($argument->children['prop']);
                        } catch (IssueException $exception) {
                            $exception->getIssueInstance()();
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
            $parameter = $method->getParameterList()[$i] ?? null;

            if (!$parameter || !is_object($argument)) {
                continue;
            }

            if (Config::get()->dead_code_detection) {
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

                    if (is_string($property_name)) {
                        // We don't do anything with it; just create it
                        // if it doesn't exist
                        try {
                            $variable = (new ContextNode(
                                $this->code_base,
                                $this->context,
                                $argument
                            ))->getOrCreateProperty($argument->children['prop']);

                        } catch (IssueException $exception) {
                            $exception->getIssueInstance()();
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
                    $variable->getUnionType()->addUnionType(
                        $parameter->getUnionType()
                    );
                }
            }
        }

        // If we're in quick mode, don't retest methods based on
        // parameter types passed in
        if (Config::get()->quick_mode) {
            return;
        }

        // We're going to hunt to see if any of the arguments
        // have a mismatch with the parameters. If so, we'll
        // re-check the method to see how the parameters impact
        // its return type
        $has_argument_parameter_mismatch = false;

        // Now that we've made sure the arguments are sufficient
        // for definitions on the method, we iterate over the
        // arguments again and add their types to the parameter
        // types so we can test the method again
        $argument_list = $node->children['args'];

        // We create a copy of the parameter list so we can switch
        // back to it after
        $original_parameter_list = $method->getParameterList();

        // Create a backup of the method's scope so that we can
        // reset it after fucking with it below
        $original_method_scope = $method->getContext()->getScope();

        foreach ($argument_list->children as $i => $argument) {
            $parameter = $method->getParameterList()[$i] ?? null;

            if (!$parameter) {
                continue;
            }

            // If the parameter has no type, pass the
            // argument's type to it
            if ($parameter->getUnionType()->isEmpty()) {
                $has_argument_parameter_mismatch = true;
                $argument_type = UnionType::fromNode(
                    $this->context,
                    $this->code_base,
                    $argument
                );

                // If this isn't an internal function or method
                // and it has no type, add the argument's type
                // to it so we can compare it to subsequent
                // calls
                if (!$parameter->isInternal()) {
                    // Clone the parameter in the original
                    // parameter list so we can reset it
                    // later
                    $original_parameter_list[$i] = clone($parameter);

                    // Then set the new type on that parameter based
                    // on the argument's type. We'll use this to
                    // retest the method with the passed in types
                    $parameter->getUnionType()->addUnionType(
                        $argument_type
                    );

                    if (!is_object($argument)) {
                        continue;
                    }

                    // If we're passing by reference, get the variable
                    // we're dealing with wrapped up and shoved into
                    // the scope of the method
                    if ($parameter->isPassByReference()) {
                        if ($argument->kind == \ast\AST_VAR) {
                            // Get the variable
                            $variable = (new ContextNode(
                                $this->code_base,
                                $this->context,
                                $argument
                            ))->getOrCreateVariable();

                            $parameter_list = $method->getParameterList();
                            $parameter_list[$i] = $variable;
                            $method->setParameterList($parameter_list);

                            // Add it to the scope of the function wrapped
                            // in a way that makes it addressable as the
                            // parameter its mimicking
                            $method->getContext()->addScopeVariable(
                                new PassByReferenceVariable(
                                    $parameter,
                                    $variable
                                )
                            );
                        }
                    } else {
                        // Overwrite the method's variable representation
                        // of the parameter with the parameter with the
                        // new type
                        $method->getContext()->addScopeVariable($parameter);
                    }
                }
            }
        }

        // Now that we know something about the parameters used
        // to call the method, we can reanalyze the method with
        // the types of the parameter, making sure we don't get
        // into an infinite loop of checking calls to the current
        // method in scope
        if ($has_argument_parameter_mismatch
            && !$method->isInternal()
            && (!$this->context->isMethodScope()
                || $method->getFQSEN() !== $this->context->getMethodFQSEN())
        ) {
            $method->analyze($method->getContext(), $code_base);
        }

        // Reset to the original parameter list after having
        // tested the parameters with the types passed in
        $method->setParameterList($original_parameter_list);

        // Reset the scope to its original version before we
        // put new parameters in it
        $method->getContext()->setScope($original_method_scope);
    }

    /**
     * @param Node $node
     * A node to check to see if its a no-op
     *
     * @param string $message
     * A message to emit if its a no-op
     *
     * @return null
     */
    private function analyzeNoOp(Node $node, string $issue_type)
    {
        if ($this->parent_node instanceof Node &&
            $this->parent_node->kind == \ast\AST_STMT_LIST
        ) {
            Issue::emit(
                $issue_type,
                $this->context->getFile(),
                $node->lineno ?? 0
            );
        }
    }
}
