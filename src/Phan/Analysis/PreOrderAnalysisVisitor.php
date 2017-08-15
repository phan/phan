<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Scope\ClosureScope;
use Phan\Language\Type;
use Phan\Language\UnionType;
use ast\Node;

class PreOrderAnalysisVisitor extends ScopeVisitor
{
    /**
     * @param CodeBase $code_base
     * The code base in which we're analyzing code
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
    }

    public function visit(Node $unused_node) : Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClass(Node $node) : Context
    {
        if ($node->flags & \ast\flags\CLASS_ANONYMOUS) {
            $class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();
        } else {
            $class_name = (string)$node->children['name'];
        }

        \assert(!empty($class_name), "Class name cannot be empty");

        $alternate_id = 0;

        // Hunt for the alternate of this class defined
        // in this file
        do {
            $class_fqsen = FullyQualifiedClassName::fromStringInContext(
                $class_name,
                $this->context
            )->withAlternateId($alternate_id++);

            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                throw new CodeBaseException(
                    $class_fqsen,
                    "Can't find class {$class_fqsen} - aborting"
                );
            }

            $clazz = $this->code_base->getClassByFQSEN(
                $class_fqsen
            );

        } while ($this->context->getProjectRelativePath()
                != $clazz->getFileRef()->getProjectRelativePath()
            || $this->context->getLineNumberStart() != $clazz->getFileRef()->getLineNumberStart()
        );

        return $clazz->getContext()->withScope(
            $clazz->getInternalScope()
        );
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Node $node) : Context
    {
        $method_name = (string)$node->children['name'];

        \assert($this->context->isInClassScope(),
            "Must be in class context to see a method");

        $clazz = $this->getContextClass();

        if (!$clazz->hasMethodWithName(
            $this->code_base,
            $method_name
        )) {
            throw new CodeBaseException(
                null,
                "Can't find method {$clazz->getFQSEN()}::$method_name() - aborting"
            );
        }

        $method = $clazz->getMethodByName(
            $this->code_base,
            $method_name
        );

        // Parse the comment above the method to get
        // extra meta information about the method.
        $comment = Comment::fromStringInContext(
            $node->children['docComment'] ?? '',
            $this->code_base,
            $this->context,
            $node->lineno ?? 0,
            Comment::ON_METHOD
        );

        $context = $this->context->withScope(
            $method->getInternalScope()
        );

        // For any @var references in the method declaration,
        // add them as variables to the method's scope
        foreach ($comment->getVariableList() as $parameter) {
            $context->addScopeVariable(
                $parameter->asVariable($this->context)
            );
        }

        // Add $this to the scope of non-static methods
        if (!($node->flags & \ast\flags\MODIFIER_STATIC)) {
            \assert(
                $clazz->getInternalScope()->hasVariableWithName('this'),
                "Classes must have a \$this variable."
            );

            $context->addScopeVariable(
                $clazz->getInternalScope()->getVariableByName('this')
            );
        }

        // Add each method parameter to the scope. We clone it
        // so that changes to the variable don't alter the
        // parameter definition
        if ($method->getRecursionDepth() === 0) {
            // Add each method parameter to the scope. We clone it
            // so that changes to the variable don't alter the
            // parameter definition
            foreach ($method->getParameterList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->cloneAsNonVariadic()
                );
            }
        }

        if ($method->getHasYield()) {
            $this->setReturnTypeOfGenerator($method, $node);
        }

        return $context;
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
        $function_name = (string)$node->children['name'];

        try {
            $canonical_function = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getFunction($function_name, true);
        } catch (CodeBaseException $exception) {
            // This really ought not happen given that
            // we already successfully parsed the code
            // base
            throw $exception;
        }

        // Hunt for the alternate associated with the file we're
        // looking at currently in this context.
        $function = null;
        foreach ($canonical_function->alternateGenerator($this->code_base)
            as $i => $alternate_function
        ) {
            if ($alternate_function->getFileRef()->getProjectRelativePath()
                === $this->context->getProjectRelativePath()
            ) {
                $function = $alternate_function;
                break;
            }
        }

        if (empty($function)) {
            // No alternate was found
            throw new CodeBaseException(
                null,
                "Can't find function {$function_name} in context {$this->context} - aborting"
            );
        }

        \assert($function instanceof Func);

        $context = $this->context->withScope(
            $function->getInternalScope()
        );

        // Parse the comment above the function to get
        // extra meta information about the method.
        $comment = Comment::fromStringInContext(
            $node->children['docComment'] ?? '',
            $this->code_base,
            $this->context,
            $node->lineno ?? 0,
            Comment::ON_FUNCTION
        );

        // For any @var references in the method declaration,
        // add them as variables to the method's scope
        foreach ($comment->getVariableList() as $parameter) {
            $context->addScopeVariable(
                $parameter->asVariable($this->context)
            );
        }

        if ($function->getRecursionDepth() === 0) {
            // Add each method parameter to the scope. We clone it
            // so that changes to the variable don't alter the
            // parameter definition
            foreach ($function->getParameterList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->cloneAsNonVariadic()
                );
            }
        }

        if ($function->getHasYield()) {
            $this->setReturnTypeOfGenerator($function, $node);
        }

        return $context;
    }

    /**
     * @return ?FullyQualifiedClassName
     */
    private static function getOverrideClassFQSEN(CodeBase $code_base, Func $func)
    {
        $closure_scope = $func->getInternalScope();
		if ($closure_scope instanceof ClosureScope) {
            $class_fqsen = $closure_scope->getOverrideClassFQSEN();
            if (!$class_fqsen) {
                return null;
            }

            // Postponed the check for undeclared closure scopes to the analysis phase,
            // because classes are still being parsed in the parse phase.
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                $func_context = $func->getContext();
                Issue::maybeEmit(
                    $code_base,
                    $func_context,
                    Issue::UndeclaredClosureScope,
                    $func_context->getLineNumberStart(),
                    (string)$class_fqsen
                );
                $closure_scope->overrideClassFQSEN(null);  // Avoid an uncaught CodeBaseException due to missing class for @phan-closure-scope
                return null;
            }
            return $class_fqsen;
        }
        return null;
    }

    /**
     * If a Closure overrides the scope(class) it will be executed in (via doc comment)
     * then return a context with the new scope instead.
     * @return void
     */
    private static function addThisVariableToInternalScope(
        CodeBase $code_base,
        Context $context,
        Func $func
    ) {
        $override_this_fqsen = self::getOverrideClassFQSEN($code_base, $func);
        if ($override_this_fqsen !== null) {
            if ($context->getScope()->hasVariableWithName('this') || !$context->isInClassScope()) {
                // Handle @phan-closure-scope - Should set $this to the overriden class, as well as handling self:: and parent::
                $func->getInternalScope()->addVariable(
                    new Variable(
                        $context,
                        'this',
                        $override_this_fqsen->asUnionType(),
                        0
                    )
                );
            }
            return;
        }
        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        if ($context->getScope()->hasVariableWithName('this')) {
            // Normal case: Closures inherit $this from parent scope.
            $thisVarFromScope = $context->getScope()->getVariableByName('this');
            $func->getInternalScope()->addVariable($thisVarFromScope);
        }
    }


    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node) : Context
    {
        $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
            $this->context->withLineNumberStart($node->lineno ?? 0),
            $node
        );
        $func = $this->code_base->getFunctionByFQSEN($closure_fqsen);

        $context = $this->context;

        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        self::addThisVariableToInternalScope($this->code_base, $context, $func);

        // Make the closure reachable by FQSEN from anywhere
        $this->code_base->addFunction($func);

        if (!empty($node->children['uses'])
            && $node->children['uses']->kind == \ast\AST_CLOSURE_USES
        ) {
            $uses = $node->children['uses'];
            foreach ($uses->children as $use) {
                if ($use->kind != \ast\AST_CLOSURE_VAR) {
                    $this->emitIssue(
                        Issue::VariableUseClause,
                        $node->lineno ?? 0
                    );
                    continue;
                }

                $variable_name = (new ContextNode(
                    $this->code_base,
                    $context,
                    $use->children['name']
                ))->getVariableName();

                if (empty($variable_name)) {
                    continue;
                }

                $variable = null;

                // Check to see if the variable exists in this scope
                if (!$context->getScope()->hasVariableWithName(
                    $variable_name
                )) {
                    // If this is not pass-by-reference variable we
                    // have a problem
                    if (!($use->flags & \ast\flags\PARAM_REF)) {
                        $this->emitIssue(
                            Issue::UndeclaredVariable,
                            $node->lineno ?? 0,
                            $variable_name
                        );
                        continue;
                    } else {
                        // If the variable doesn't exist, but its
                        // a pass-by-reference variable, we can
                        // just create it
                        $variable = Variable::fromNodeInContext(
                            $use,
                            $context,
                            $this->code_base,
                            false
                        );
                    }
                } else {
                    $variable = $context->getScope()->getVariableByName(
                        $variable_name
                    );

                    // If this isn't a pass-by-reference variable, we
                    // clone the variable so state within this scope
                    // doesn't update the outer scope
                    if (!($use->flags & \ast\flags\PARAM_REF)) {
                        $variable = clone($variable);
                    }
                }

                // Pass the variable into a new scope
                $func->getInternalScope()->addVariable($variable);
            }
        }

        // Add all parameters to the scope
        if (!empty($node->children['params'])
            && $node->children['params']->kind == \ast\AST_PARAM_LIST
        ) {
            $params = $node->children['params'];
            foreach ($params->children as $param) {
                // Read the parameter
                $parameter = Parameter::fromNode(
                    $context,
                    $this->code_base,
                    $param
                );

                // Add it to the scope
                $func->getInternalScope()->addVariable($parameter);
            }
        }

        if ($func->getHasYield()) {
            $this->setReturnTypeOfGenerator($func, $node);
        }

        return $context->withScope($func->getInternalScope());
    }

    /**
     * The return type of the given FunctionInterface to a Generator.
     * Emit an Issue if the documented return type is incompatible with that.
     * @return void
     */
    private function setReturnTypeOfGenerator(FunctionInterface $func, Node $node)
    {
        // Currently, there is no way to describe the types passed to
        // a Generator in phpdoc.
        // So, nothing bothers recording the types beyond \Generator.
        $func->setHasReturn(true);  // Returns \Generator, technically
        $func->setHasYield(true);
        if ($func->getUnionType()->isEmpty()) {
            $func->setIsReturnTypeUndefined(true);
            $func->getUnionType()->addUnionType(Type::fromNamespaceAndName('\\', 'Generator', false)->asUnionType());
        }
        if (!$func->isReturnTypeUndefined()) {
            $func_return_type = $func->getUnionType();
            if (!$func_return_type->canCastToExpandedUnionType(
                    Type::fromNamespaceAndName('\\', 'Generator', false)->asUnionType(),
                    $this->code_base)) {
                // At least one of the documented return types must
                // be Generator, Iterable, or Traversable.
                // Check for the issue here instead of in visitReturn/visitYield so that
                // the check is done exactly once.
                $this->emitIssue(
                    Issue::TypeMismatchReturn,
                    $node->lineno ?? 0,
                    '\\Generator',
                    $func->getName(),
                    (string)$func_return_type
                );
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
    public function visitForeach(Node $node) : Context
    {
        $expression_union_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['expr']
        );

        // Filter out the non-generic types of the
        // expression
        $non_generic_expression_union_type =
            $expression_union_type->genericArrayElementTypes();

        if ($node->children['value']->kind == \ast\AST_ARRAY) {
            foreach ($node->children['value']->children ?? [] as $child_node) {

                // $key_node = $child_node->children['key'] ?? null;
                $value_node = $child_node->children['value'] ?? null;

                // for syntax like: foreach ([] as list(, $a));
                if ($value_node === null) {
                    continue;
                }
                \assert($value_node instanceof Node);

                $variable = Variable::fromNodeInContext(
                    $value_node,
                    $this->context,
                    $this->code_base,
                    false
                );

                // If we were able to figure out the type and its
                // a generic type, then set its element types as
                // the type of the variable
                if (!$non_generic_expression_union_type->isEmpty()) {
                    $second_order_non_generic_expression_union_type =
                        $non_generic_expression_union_type->genericArrayElementTypes();

                    if (!$second_order_non_generic_expression_union_type->isEmpty()) {
                        $variable->setUnionType(
                            $second_order_non_generic_expression_union_type
                        );
                    }

                }

                $this->context->addScopeVariable($variable);
            }

        // Otherwise, read the value as regular variable and
        // add it to the scope
        } else {
            // Create a variable for the value
            $variable = Variable::fromNodeInContext(
                $node->children['value'],
                $this->context,
                $this->code_base,
                false
            );


            // If we were able to figure out the type and its
            // a generic type, then set its element types as
            // the type of the variable
            if (!$non_generic_expression_union_type->isEmpty()) {
                $variable->setUnionType($non_generic_expression_union_type);
            }

            // Add the variable to the scope
            $this->context->addScopeVariable($variable);
        }

        // If there's a key, make a variable out of that too
        if (!empty($node->children['key'])) {
            if (($node->children['key'] instanceof \ast\Node)
                && ($node->children['key']->kind == \ast\AST_LIST)
            ) {
                throw new NodeException(
                    $node,
                    "Can't use list() as a key element - aborting"
                );
            }

            $variable = Variable::fromNodeInContext(
                $node->children['key'],
                $this->context,
                $this->code_base,
                false
            );

            $this->context->addScopeVariable($variable);
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
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
    public function visitCatch(Node $node) : Context
    {
        try {
            $union_type = UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            );

            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList();

            foreach ($class_list as $class) {
                $class->addReference($this->context);
            }

        } catch (CodeBaseException $exception) {
            $this->emitIssue(
                Issue::UndeclaredClassCatch,
                $node->lineno ?? 0,
                (string)$exception->getFQSEN()
            );
        }

        $variable_name = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        ))->getVariableName();

        if (!empty($variable_name)) {
            $variable = Variable::fromNodeInContext(
                $node->children['var'],
                $this->context,
                $this->code_base,
                false
            );

            if (!$union_type->isEmpty()) {
                $variable->setUnionType($union_type);
            }

            $this->context->addScopeVariable($variable);
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
    public function visitIfElem(Node $node) : Context
    {
        $cond = $node->children['cond'] ?? null;
        if (!($cond instanceof Node)) {
            return $this->context;
        }

        // Look to see if any proofs we do within the condition
        // can say anything about types within the statement
        // list.
        return (new ConditionVisitor(
            $this->code_base,
            $this->context
        ))($cond);
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
        $cond = $node->children['cond'];
        if (!($cond instanceof Node)) {
            return $this->context;
        }

        // Look to see if any proofs we do within the condition of the while
        // can say anything about types within the statement
        // list.
        return (new ConditionVisitor(
            $this->code_base,
            $this->context
        ))($cond);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFor(Node $node) : Context
    {
        $cond = $node->children['cond'];
        if (!($cond instanceof Node)) {
            return $this->context;
        }

        // Look to see if any proofs we do within the condition of the while
        // can say anything about types within the statement
        // list.
        return (new ConditionVisitor(
            $this->code_base,
            $this->context
        ))($cond);
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
        $name = $node->children['expr']->children['name'] ?? null;
        // Look only at nodes of the form `assert(expr, ...)`.
        if ($name !== 'assert') {
            return $this->context;
        }
        $args = $node->children['args'];
        if (!isset($node->children['args']->children[0])
            || !($node->children['args']->children[0] instanceof Node)
        ) {
            return $this->context;
        }

        // Look to see if the asserted expression says anything about
        // the types of any variables.
        return (new ConditionVisitor(
            $this->code_base,
            $this->context
        ))($args->children[0]);
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz
    {
        return $this->context->getClassInScope($this->code_base);
    }
}
