<?php declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\NodeException;
use Phan\Exception\RecursionDepthException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClosureScope;
use Phan\Language\Type;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;

/**
 * PreOrderAnalysisVisitor is where we do the pre-order part of the analysis
 * during Phan's analysis phase.
 *
 * This is called in pre-order by BlockAnalysisVisitor
 * (i.e. this is called before visiting all children of the current node)
 */
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
    /*
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
    }
     */

    /** @param Node $unused_node implementation for unhandled nodes */
    public function visit(Node $unused_node) : Context
    {
        return $this->context;
    }

    /**
     * Visit a node with kind `ast\AST_CLASS`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @throws UnanalyzableException
     * if the class name is unexpectedly empty
     *
     * @throws CodeBaseException
     * if the class could not be located
     */
    public function visitClass(Node $node) : Context
    {
        if ($node->flags & ast\flags\CLASS_ANONYMOUS) {
            $class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();
        } else {
            $class_name = (string)$node->children['name'];
        }

        if (!$class_name) {
            // Should only occur with --use-fallback-parser
            throw new UnanalyzableException($node, "Class name cannot be empty");
        }

        $alternate_id = 0;

        // Hunt for the alternate of this class defined
        // in this file
        do {
            // @phan-suppress-next-line PhanThrowTypeMismatchForCall
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
            || $node->children['__declId'] != $clazz->getDeclId()
            || $this->context->getLineNumberStart() != $clazz->getFileRef()->getLineNumberStart()
        );

        return $clazz->getContext()->withScope(
            $clazz->getInternalScope()
        );
    }

    /**
     * Visit a node with kind `ast\AST_METHOD`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @throws CodeBaseException if the method could not be found
     */
    public function visitMethod(Node $node) : Context
    {
        $method_name = (string)$node->children['name'];
        $code_base = $this->code_base;
        $context = $this->context;

        if (!($context->isInClassScope())) {
            throw new AssertionError("Must be in class context to see a method");
        }

        $clazz = $this->getContextClass();

        if (!$clazz->hasMethodWithName(
            $code_base,
            $method_name
        )) {
            throw new CodeBaseException(
                null,
                "Can't find method {$clazz->getFQSEN()}::$method_name() - aborting"
            );
        }

        $method = $clazz->getMethodByName(
            $code_base,
            $method_name
        );
        $method->ensureScopeInitialized($code_base);
        // Fix #2504 - add flags to ensure that DimOffset warnings aren't emitted inside closures
        Analyzable::ensureDidAnnotate($node);

        // Parse the comment above the method to get
        // extra meta information about the method.
        $comment = $method->getComment();

        $context = $this->context->withScope(
            $method->getInternalScope()
        );

        // For any @var references in the method declaration,
        // add them as variables to the method's scope
        if ($comment !== null) {
            foreach ($comment->getVariableList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->asVariable($this->context)
                );
            }
        }

        // Add $this to the scope of non-static methods
        if (!($node->flags & ast\flags\MODIFIER_STATIC)) {
            if (!($clazz->getInternalScope()->hasVariableWithName('this'))) {
                throw new AssertionError("Classes must have a \$this variable.");
            }

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

        // TODO: Why is the check for yield in PreOrderAnalysisVisitor?
        if ($method->getHasYield()) {
            $this->setReturnTypeOfGenerator($method, $node);
        }

        return $context;
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
     * @throws CodeBaseException
     * if this function declaration could not be found
     */
    public function visitFuncDecl(Node $node) : Context
    {
        $function_name = (string)$node->children['name'];
        $code_base = $this->code_base;
        $original_context = $this->context;

        // This really ought not to throw given that
        // we already successfully parsed the code
        // base (the AST names should be valid)
        // @phan-suppress-next-line PhanThrowTypeMismatchForCall
        $canonical_function = (new ContextNode(
            $code_base,
            $original_context,
            $node
        ))->getFunction($function_name, true);

        // Hunt for the alternate associated with the file we're
        // looking at currently in this context.
        $function = null;
        foreach ($canonical_function->alternateGenerator($code_base) as $alternate_function) {
            if ($alternate_function->getFileRef()->getProjectRelativePath()
                === $original_context->getProjectRelativePath()
            ) {
                $function = $alternate_function;
                break;
            }
        }

        if (!($function instanceof Func)) {
            // No alternate was found
            throw new CodeBaseException(
                null,
                "Can't find function {$function_name} in context {$this->context} - aborting"
            );
        }

        $function->ensureScopeInitialized($code_base);
        // Fix #2504 - add flags to ensure that DimOffset warnings aren't emitted inside closures
        Analyzable::ensureDidAnnotate($node);

        $context = $original_context->withScope(
            $function->getInternalScope()
        );

        // Parse the comment above the function to get
        // extra meta information about the function.
        // TODO: Investigate caching information from Comment::fromStringInContext?
        $comment = $function->getComment();

        // For any @var references in the function declaration,
        // add them as variables to the function's scope
        if ($comment !== null) {
            foreach ($comment->getVariableList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->asVariable($this->context)
                );
            }
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
        if (!$function->getHasReturn() && $function->getUnionType()->isEmpty()) {
            $function->setUnionType(VoidType::instance(false)->asUnionType());
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
        // skip adding $this to internal scope if the closure is a static one
        if ($func->getFlags() == ast\flags\MODIFIER_STATIC) {
            return;
        }

        $override_this_fqsen = self::getOverrideClassFQSEN($code_base, $func);
        if ($override_this_fqsen !== null) {
            if ($context->getScope()->hasVariableWithName('this') || !$context->isInClassScope()) {
                // Handle @phan-closure-scope - Should set $this to the overridden class, as well as handling self:: and parent::
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
            $this_var_from_scope = $context->getScope()->getVariableByName('this');
            $func->getInternalScope()->addVariable($this_var_from_scope);
        }
    }


    /**
     * Visit a node with kind `ast\AST_CLOSURE`
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
        $code_base = $this->code_base;
        $context = $this->context;
        $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
            $context->withLineNumberStart($node->lineno),
            $node
        );
        $func = $code_base->getFunctionByFQSEN($closure_fqsen);
        $func->ensureScopeInitialized($code_base);
        // Fix #2504 - add flags to ensure that DimOffset warnings aren't emitted inside closures
        Analyzable::ensureDidAnnotate($node);

        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        self::addThisVariableToInternalScope($code_base, $context, $func);

        // Make the closure reachable by FQSEN from anywhere
        $code_base->addFunction($func);

        if (($node->children['uses']->kind ?? null) == ast\AST_CLOSURE_USES) {
            $uses = $node->children['uses'];
            foreach ($uses->children as $use) {
                if (!($use instanceof Node) || $use->kind != ast\AST_CLOSURE_VAR) {
                    $this->emitIssue(
                        Issue::VariableUseClause,
                        $node->lineno
                    );
                    continue;
                }

                $variable_name = (new ContextNode(
                    $code_base,
                    $context,
                    $use->children['name']
                ))->getVariableName();

                if (!$variable_name) {
                    continue;
                }

                $variable = null;

                // Check to see if the variable exists in this scope
                if (!$context->getScope()->hasVariableWithName(
                    $variable_name
                )) {
                    // If this is not pass-by-reference variable we
                    // have a problem
                    if (!($use->flags & ast\flags\PARAM_REF)) {
                        Issue::maybeEmitWithParameters(
                            $this->code_base,
                            clone($context)->withLineNumberStart($use->lineno),
                            Issue::UndeclaredVariable,
                            $node->lineno,
                            [$variable_name],
                            IssueFixSuggester::suggestVariableTypoFix($this->code_base, $context, $variable_name)
                        );
                        continue;
                    } else {
                        // If the variable doesn't exist, but it's
                        // a pass-by-reference variable, we can
                        // just create it
                        $variable = Variable::fromNodeInContext(
                            $use,
                            $context,
                            $this->code_base,
                            false
                        );
                        // And add it to the scope of the parent (For https://github.com/phan/phan/issues/367)
                        $context->addScopeVariable($variable);
                    }
                } else {
                    $variable = $context->getScope()->getVariableByName(
                        $variable_name
                    );

                    // If this isn't a pass-by-reference variable, we
                    // clone the variable so state within this scope
                    // doesn't update the outer scope
                    if (!($use->flags & ast\flags\PARAM_REF)) {
                        $variable = clone($variable);
                    }
                }

                // Pass the variable into a new scope
                $func->getInternalScope()->addVariable($variable);
            }
        }
        if (!$func->getHasReturn() && $func->getUnionType()->isEmpty()) {
            $func->setUnionType(VoidType::instance(false)->asUnionType());
        }

        // Add parameters to the context.
        $context = $context->withScope($func->getInternalScope());

        $comment = $func->getComment();

        // For any @var references in the method declaration,
        // add them as variables to the method's scope
        if ($comment !== null) {
            foreach ($comment->getVariableList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->asVariable($this->context)
                );
            }
        }
        if ($func->getRecursionDepth() === 0) {
            // Add each closure parameter to the scope. We clone it
            // so that changes to the variable don't alter the
            // parameter definition
            foreach ($func->getParameterList() as $parameter) {
                $context->addScopeVariable(
                    $parameter->cloneAsNonVariadic()
                );
            }
        }

        if ($func->getHasYield()) {
            $this->setReturnTypeOfGenerator($func, $node);
        }

        return $context;
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
            $func->setUnionType($func->getUnionType()->withType(Type::fromNamespaceAndName('\\', 'Generator', false)));
        }
        if (!$func->isReturnTypeUndefined()) {
            $func_return_type = $func->getUnionType();
            try {
                $func_return_type_can_cast = $func_return_type->canCastToExpandedUnionType(
                    Type::fromNamespaceAndName('\\', 'Generator', false)->asUnionType(),
                    $this->code_base
                );
            } catch (RecursionDepthException $_) {
                return;
            }
            if (!$func_return_type_can_cast) {
                // At least one of the documented return types must
                // be Generator, Iterable, or Traversable.
                // Check for the issue here instead of in visitReturn/visitYield so that
                // the check is done exactly once.
                $this->emitIssue(
                    Issue::TypeMismatchReturn,
                    $node->lineno,
                    '\\Generator',
                    $func->getNameForIssue(),
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
     * An unchanged context resulting from parsing the node
     */
    public function visitAssign(Node $node) : Context
    {
        $var_node = $node->children['var'];
        if (Config::get_closest_target_php_version_id() < 70100 && $var_node instanceof Node && $var_node->kind === ast\AST_ARRAY) {
            $this->analyzeArrayAssignBackwardsCompatibility($var_node);
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
     *
     * @throws NodeException
     * if the key is invalid
     */
    public function visitForeach(Node $node) : Context
    {
        $code_base = $this->code_base;
        $context = $this->context;

        $expression_union_type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node->children['expr']
        );

        // Check the expression type to make sure it's
        // something we can iterate over
        $this->checkCanIterate($expression_union_type, $node);

        $value_node = $node->children['value'];
        if (!($value_node instanceof Node)) {
            return $context;
        }
        if ($value_node->kind == ast\AST_ARRAY) {
            if (Config::get_closest_target_php_version_id() < 70100) {
                $this->analyzeArrayAssignBackwardsCompatibility($value_node);
            }
        }

        $context = (new AssignmentVisitor(
            $code_base,
            $context,
            $value_node,
            $expression_union_type->iterableValueUnionType($code_base)
        ))->__invoke($value_node);

        // If there's a key, make a variable out of that too
        $key_node = $node->children['key'];
        if ($key_node instanceof Node) {
            if ($key_node->kind === ast\AST_ARRAY) {
                $this->emitIssue(
                    Issue::InvalidNode,
                    $key_node->lineno,
                    "Can't use list() as a key element - aborting"
                );
            } else {
                // TODO: Support Traversable<Key, T> then return Key.
                // If we see array<int,T> or array<string,T> and no other array types, we're reasonably sure the foreach key is an integer or a string, so set it.
                // (Or if we see iterable<int,T>
                $context = (new AssignmentVisitor(
                    $code_base,
                    $context,
                    $key_node,
                    $expression_union_type->iterableKeyUnionType($code_base)
                ))->__invoke($key_node);
            }
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        return $context;
    }

    /**
     * @param UnionType $union_type the type of $node->children['expr']
     * @param Node $node a node of kind AST_FOREACH
     */
    private function checkCanIterate(UnionType $union_type, Node $node)
    {
        if ($union_type->isScalar()) {
            $this->emitIssue(
                Issue::TypeMismatchForeach,
                $node->children['expr']->lineno ?? $node->lineno,
                (string)$union_type
            );
        }
        foreach ($union_type->getTypeSet() as $type) {
            try {
                if ($type->asExpandedTypes($this->code_base)->hasTraversable()) {
                    continue;
                }
            } catch (RecursionDepthException $_) {
            }
            if (!$type->isObjectWithKnownFQSEN()) {
                continue;
            }
            $this->warnAboutNonTraversableType($node, $type);
        }
    }

    private function warnAboutNonTraversableType(Node $node, Type $type)
    {
        $fqsen = FullyQualifiedClassName::fromType($type);
        if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
            return;
        }
        if ($fqsen->__toString() === '\stdClass') {
            // stdClass is the only non-Traversable that I'm aware of that's commonly traversed over.
            return;
        }
        $class = $this->code_base->getClassByFQSEN($fqsen);
        $status = $class->checkCanIterateFromContext(
            $this->code_base,
            $this->context
        );
        switch ($status) {
            case Clazz::CAN_ITERATE_STATUS_NO_ACCESSIBLE_PROPERTIES:
                $issue = Issue::TypeNoAccessiblePropertiesForeach;
                break;
            case Clazz::CAN_ITERATE_STATUS_NO_PROPERTIES:
                $issue = Issue::TypeNoPropertiesForeach;
                break;
            default:
                $issue = Issue::TypeSuspiciousNonTraversableForeach;
                break;
        }

        $this->emitIssue(
            $issue,
            $node->children['expr']->lineno ?? $node->lineno,
            $type
        );
    }

    private function analyzeArrayAssignBackwardsCompatibility(Node $node)
    {
        if ($node->flags !== ast\flags\ARRAY_SYNTAX_LIST) {
            $this->emitIssue(
                Issue::CompatibleShortArrayAssignPHP70,
                $node->lineno
            );
        }
        foreach ($node->children as $array_elem) {
            if (isset($array_elem->children['key'])) {
                $this->emitIssue(
                    Issue::CompatibleKeyedArrayAssignPHP70,
                    $array_elem->lineno
                );
                break;
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
    public function visitCatch(Node $node) : Context
    {
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        $union_type = UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $node->children['class']
        );

        try {
            $class_list = \iterator_to_array($union_type->asClassList($this->code_base, $this->context));

            if (Config::get_closest_target_php_version_id() < 70100 && \count($class_list) > 1) {
                $this->emitIssue(
                    Issue::CompatibleMultiExceptionCatchPHP70,
                    $node->lineno
                );
            }

            foreach ($class_list as $class) {
                $class->addReference($this->context);
            }
        } catch (CodeBaseException $exception) {
            Issue::maybeEmitWithParameters(
                $this->code_base,
                $this->context,
                Issue::UndeclaredClassCatch,
                $node->lineno,
                [(string)$exception->getFQSEN()],
                IssueFixSuggester::suggestSimilarClassForGenericFQSEN($this->code_base, $this->context, $exception->getFQSEN())
            );
        }

        $throwable_type = Type::throwableInstance();
        if ($union_type->isEmpty() || !$union_type->asExpandedTypes($this->code_base)->hasType($throwable_type)) {
            $union_type = $union_type->withType($throwable_type);
        }

        $variable_name = (new ContextNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        ))->getVariableName();

        if ($variable_name) {
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
        ))->__invoke($cond);
    }

    // visitWhile is unnecessary, this has special logic in BlockAnalysisVisitor to handle conditions assigning variables to the loop

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
        ))->__invoke($cond);
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
        $args_first_child = $node->children['args']->children[0] ?? null;
        if (!($args_first_child instanceof Node)) {
            return $this->context;
        }

        // Look to see if the asserted expression says anything about
        // the types of any variables.
        return (new ConditionVisitor(
            $this->code_base,
            $this->context
        ))->__invoke($args_first_child);
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
