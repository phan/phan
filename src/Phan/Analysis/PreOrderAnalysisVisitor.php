<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClosureScope;
use Phan\Language\Type;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\VoidType;
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
        $code_base = $this->code_base;
        $context = $this->context;

        \assert(
            $context->isInClassScope(),
            "Must be in class context to see a method"
        );

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

        // TODO: Why is the check for yield in PreOrderAnalysisVisitor?
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
        $code_base = $this->code_base;
        $original_context = $this->context;

        try {
            $canonical_function = (new ContextNode(
                $code_base,
                $original_context,
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
        foreach ($canonical_function->alternateGenerator($code_base) as $alternate_function) {
            if ($alternate_function->getFileRef()->getProjectRelativePath()
                === $original_context->getProjectRelativePath()
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
        $function->ensureScopeInitialized($code_base);

        $context = $original_context->withScope(
            $function->getInternalScope()
        );

        // Parse the comment above the function to get
        // extra meta information about the method.
        // TODO: Investigate caching information from Comment::fromStringInContext?
        $comment = $function->getComment();

        // For any @var references in the method declaration,
        // add them as variables to the method's scope
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
        $code_base = $this->code_base;
        $context = $this->context;
        $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
            $context->withLineNumberStart($node->lineno ?? 0),
            $node
        );
        $func = $code_base->getFunctionByFQSEN($closure_fqsen);
        $func->ensureScopeInitialized($code_base);

        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        self::addThisVariableToInternalScope($code_base, $context, $func);

        // Make the closure reachable by FQSEN from anywhere
        $code_base->addFunction($func);

        if (!empty($node->children['uses'])
            && $node->children['uses']->kind == \ast\AST_CLOSURE_USES
        ) {
            $uses = $node->children['uses'];
            foreach ($uses->children as $use) {
                if (!($use instanceof Node) || $use->kind != \ast\AST_CLOSURE_VAR) {
                    $this->emitIssue(
                        Issue::VariableUseClause,
                        $node->lineno ?? 0
                    );
                    continue;
                }

                $variable_name = (new ContextNode(
                    $code_base,
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
                        Issue::maybeEmitWithParameters(
                            $this->code_base,
                            clone($context)->withLineNumberStart($use->lineno),
                            Issue::UndeclaredVariable,
                            $node->lineno ?? 0,
                            [$variable_name]
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
                        // And add it to the scope of the parent (For https://github.com/phan/phan/issues/367)
                        $context->getScope()->addVariable($variable);
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
            $func_return_type_can_cast = $func_return_type->canCastToExpandedUnionType(
                Type::fromNamespaceAndName('\\', 'Generator', false)->asUnionType(),
                $this->code_base
            );
            if (!$func_return_type_can_cast) {
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
     * An unchanged context resulting from parsing the node
     */
    public function visitAssign(Node $node) : Context
    {
        // In php 7.0, a **valid** parsed AST would be an \ast\AST_LIST.
        // However, --force-polyfill-parser will emit \ast\AST_ARRAY.
        $var_node = $node->children['var'];
        if (Config::get_closest_target_php_version_id() < 70100 && $var_node instanceof Node && $var_node->kind === \ast\AST_ARRAY) {
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
     */
    public function visitForeach(Node $node) : Context
    {
        $expression_union_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );

        // Check the expression type to make sure its
        // something we can iterate over
        if ($expression_union_type->isScalar()) {
            $this->emitIssue(
                Issue::TypeMismatchForeach,
                $node->lineno ?? 0,
                (string)$expression_union_type
            );
        }

        $value_node = $node->children['value'];
        if (!($value_node instanceof Node)) {
            return $this->context;
        }
        if ($value_node->kind == \ast\AST_ARRAY) {
            if (Config::get_closest_target_php_version_id() < 70100) {
                $this->analyzeArrayAssignBackwardsCompatibility($value_node);
            }
            $this->inferTypesForForeachArrayDestructuring($expression_union_type->genericArrayElementTypes(), $value_node);

        // Otherwise, read the value as regular variable and
        // add it to the scope
        } else {
            // Filter out the non-generic types of the
            // expression
            $non_generic_expression_union_type =
                $expression_union_type->genericArrayElementTypes();
            // Create a variable for the value
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
                $variable->setUnionType($non_generic_expression_union_type);
            }

            // Add the variable to the scope
            $this->context->addScopeVariable($variable);
        }

        // If there's a key, make a variable out of that too
        $key_node = $node->children['key'];
        if ($key_node instanceof \ast\Node) {
            if ($key_node->kind == \ast\AST_LIST) {
                throw new NodeException(
                    $node,
                    "Can't use list() as a key element - aborting"
                );
            }

            $variable = Variable::fromNodeInContext(
                $key_node,
                $this->context,
                $this->code_base,
                false
            );
            if (!$expression_union_type->isEmpty()) {
                // TODO: Support Traversable<Key, T> then return Key.
                // If we see array<int,T> or array<string,T> and no other array types, we're reasonably sure the foreach key is an integer or a string, so set it.
                $union_type_of_array_key = UnionTypeVisitor::arrayKeyUnionTypeOfUnionType($expression_union_type);
                if ($union_type_of_array_key !== null) {
                    $variable->setUnionType($union_type_of_array_key);
                }
            }

            $this->context->addScopeVariable($variable);
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        return $this->context;
    }

    private function analyzeArrayAssignBackwardsCompatibility(Node $node)
    {
        if ($node->flags !== \ast\flags\ARRAY_SYNTAX_LIST) {
            $this->emitIssue(
                Issue::CompatibleShortArrayAssignPHP70,
                $node->lineno ?? 0
            );
        }
        foreach ($node->children as $array_elem) {
            if (isset($array_elem->children['key'])) {
                $this->emitIssue(
                    Issue::CompatibleKeyedArrayAssignPHP70,
                    $array_elem->lineno ?? 0
                );
                break;
            }
        }
    }

    /**
     * @return void
     */
    private function inferTypesForForeachArrayDestructuring(
        UnionType $element_union_type,
        Node $value_node
    ) {
        if ($element_union_type->hasTopLevelArrayShapeTypeInstances()) {
            $this->inferTypesForShapedForeachArrayDestructuring($element_union_type, $value_node);
        } else {
            $this->inferTypesForGenericForeachArrayDestructuring($element_union_type, $value_node);
        }
    }

    /**
     * @param UnionType $element_union_type (The T[]|S[] types from the expression of the foreach)
     * @param Node $value_node a node of type ast\AST_ARRAY that is in the foreach's `as` value
     * @return void
     */
    private function inferTypesForShapedForeachArrayDestructuring(
        UnionType $element_union_type,
        Node $value_node
    ) {
        $key_set = [];

        $expect_int_keys_lineno = false;
        $expect_string_keys_lineno = false;

        $fallback_second_order_element_type = null;
        $get_fallback_second_order_element_type = function () use (&$fallback_second_order_element_type, $element_union_type) : UnionType {
            return $fallback_second_order_element_type ?? ($fallback_second_order_element_type = $element_union_type->genericArrayElementTypes());
        };
        foreach ($value_node->children as $child_node) {
            // $key_node = $child_node->children['key'] ?? null;
            $value_elem_node = $child_node->children['value'] ?? null;

            // for syntax like: foreach ([] as list(, $a));
            if ($value_elem_node === null) {
                continue;
            }
            \assert($value_elem_node instanceof Node);

            // Get the key and value nodes for each
            // array element we're assigning to
            // TODO: Check key types are valid?
            $key_node = $child_node->children['key'];
            $key_value = null;

            if ($key_node === null) {
                $key_set[] = true;
                \end($key_set);
                $key_value = \key($key_set);

                $expect_int_keys_lineno = $child_node->lineno;  // list($x, $y) = ... is equivalent to list(0 => $x, 1 => $y) = ...
            } else {
                if ($key_node instanceof Node) {
                    $key_value = (new ContextNode($this->code_base, $this->context, $key_node))->getEquivalentPHPScalarValue();
                } else {
                    $key_value = $key_node;
                }
                if (\is_scalar($key_value)) {
                    $key_set[$key_value] = true;
                    if (\is_int($key_value)) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif (\is_string($key_value)) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                } else {
                    $key_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key_node);
                    $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($key_type);
                    // TODO: Warn about types that can't cast to int|string
                    if ($key_type_enum === GenericArrayType::KEY_INT) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif ($key_type_enum === GenericArrayType::KEY_STRING) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                }
            }

            $variable = Variable::fromNodeInContext(
                $value_elem_node,
                $this->context,
                $this->code_base,
                false
            );

            if (\is_scalar($key_value)) {
                $second_order_non_generic_expression_union_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($element_union_type, $key_value);
                if ($second_order_non_generic_expression_union_type === null) {
                    $second_order_non_generic_expression_union_type = $get_fallback_second_order_element_type();
                } elseif ($second_order_non_generic_expression_union_type === false) {
                    $this->emitIssue(
                        Issue::TypeInvalidDimOffsetArrayDestructuring,
                        $child_node->lineno,
                        json_encode($key_value),
                        (string)$element_union_type
                    );
                    $second_order_non_generic_expression_union_type = $get_fallback_second_order_element_type();
                }
            } else {
                $second_order_non_generic_expression_union_type = $get_fallback_second_order_element_type();
            }

            // If we were able to figure out the type and its
            // a generic type, then set its element types as
            // the type of the variable
            $variable->setUnionType(
                $second_order_non_generic_expression_union_type
            );

            $this->context->addScopeVariable($variable);
        }

        $this->checkMismatchForeachArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno, $element_union_type);
    }

    /**
     * @param UnionType $element_union_type (The T[]|S[] types from the expression of the foreach)
     * @param Node $value_node a node of type ast\AST_ARRAY that is in the foreach's `as` value
     * @return void
     */
    private function inferTypesForGenericForeachArrayDestructuring(
        UnionType $element_union_type,
        Node $value_node
    ) {
        $scalar_array_key_cast = Config::getValue('scalar_array_key_cast');
        $expect_string_keys_lineno = false;
        $expect_int_keys_lineno = false;

        foreach ($value_node->children as $child_node) {
            $value_elem_node = $child_node->children['value'] ?? null;

            // for syntax like: foreach ([] as list(, $a));
            if ($value_elem_node === null) {
                continue;
            }
            \assert($value_elem_node instanceof Node);

            $key_node = $child_node->children['key'];
            if (!$scalar_array_key_cast) {
                if ($key_node === null) {
                    $expect_int_keys_lineno = $child_node->lineno;  // list($x, $y) = ... is equivalent to list(0 => $x, 1 => $y) = ...
                } else {
                    $key_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $key_node);
                    $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($key_type);
                    // TODO: Warn about types that can't cast to int|string
                    if ($key_type_enum === GenericArrayType::KEY_INT) {
                        $expect_int_keys_lineno = $child_node->lineno;
                    } elseif ($key_type_enum === GenericArrayType::KEY_STRING) {
                        $expect_string_keys_lineno = $child_node->lineno;
                    }
                }
            }

            $variable = Variable::fromNodeInContext(
                $value_elem_node,
                $this->context,
                $this->code_base,
                false
            );

            // If we were able to figure out the type and its
            // a generic type, then set its element types as
            // the type of the variable
            if (!$element_union_type->isEmpty()) {
                $second_order_non_generic_expression_union_type =
                    $element_union_type->genericArrayElementTypes();

                if (!$second_order_non_generic_expression_union_type->isEmpty()) {
                    $variable->setUnionType(
                        $second_order_non_generic_expression_union_type
                    );
                }
            }

            $this->context->addScopeVariable($variable);
        }
        // TODO: Issue::TypeMismatchArrayDestructuringKey,

        $this->checkMismatchForeachArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno, $element_union_type);
    }

    /**
     * TODO: Deduplicate these checks in a trait
     * @param int|false $expect_int_keys_lineno
     * @param int|false $expect_string_keys_lineno
     * @param UnionType $element_union_type
     * @return void
     */
    private function checkMismatchForeachArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno, UnionType $element_union_type)
    {
        if ($expect_int_keys_lineno !== false || $expect_string_keys_lineno !== false) {
            $right_hand_key_type = GenericArrayType::keyTypeFromUnionTypeKeys($element_union_type);
            if ($expect_int_keys_lineno !== false && ($right_hand_key_type & GenericArrayType::KEY_INT) === 0) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeMismatchArrayDestructuringKey,
                    $expect_int_keys_lineno,
                    'int',
                    'string'
                );
            } elseif ($expect_string_keys_lineno !== false && ($right_hand_key_type & GenericArrayType::KEY_STRING) === 0) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::TypeMismatchArrayDestructuringKey,
                    $expect_string_keys_lineno,
                    'string',
                    'int'
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
    public function visitCatch(Node $node) : Context
    {
        $union_type = UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $node->children['class']
        );

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);

            foreach ($class_list as $class) {
                $class->addReference($this->context);
            }
        } catch (CodeBaseException $exception) {
            $this->emitIssue(
                Issue::UndeclaredClassCatch,
                $node->lineno ?? 0,
                (string)$exception->getFQSEN()
            );

            $union_type = $union_type->withType(Type::fromFullyQualifiedString('\Throwable'));
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
