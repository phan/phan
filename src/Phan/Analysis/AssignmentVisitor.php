<?php declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast\Node;
use Phan\AST\AnalysisVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\RecursionDepthException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use function strcasecmp;

/**
 * Analyzes assignments.
 */
class AssignmentVisitor extends AnalysisVisitor
{
    /**
     * @var Node
     * The AST node containing the assignment
     */
    private $assignment_node;

    /**
     * @var UnionType
     * The type of the element on the right side of the assignment
     */
    private $right_type;

    /**
     * @var int
     * Depth of array parameters in this assignment, e.g. this is
     * 1 for `$foo[3] = 42`, 0 for `$x = 2;`, etc.
     * We need to know this in order to decide
     * if we're replacing the union type
     * or if we're adding a type to the union type.
     */
    private $dim_depth;

    /**
     * @var ?UnionType
     * Non-null if this assignment is to an array parameter such as
     * in `$foo[3] = 42` (type would be int). We need to know this in order to decide
     * to type check the assignment (e.g. array keys are int|string, string offsets are int)
     * type to the union type.
     *
     * Null for `$foo[] = 42` or when dim_depth is 0
     */
    private $dim_type;

    /**
     * @param CodeBase $code_base
     * The global code base we're operating within
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node $assignment_node
     * The AST node containing the assignment
     *
     * @param UnionType $right_type
     * The type of the element on the right side of the assignment
     *
     * @param int $dim_depth
     * Positive if this assignment is to an array parameter such as
     * in `$foo[3] = 42`. We need to know this in order to decide
     * if we're replacing the union type or if we're adding a
     * type to the union type.
     *
     * @param ?UnionType $dim_type
     * The type of the dimension.
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $assignment_node,
        UnionType $right_type,
        int $dim_depth = 0,
        UnionType $dim_type = null
    ) {
        parent::__construct($code_base, $context);

        $this->assignment_node = $assignment_node;
        $this->right_type = $right_type->withSelfResolvedInContext($context);
        $this->dim_depth = $dim_depth;
        $this->dim_type = $dim_type;  // null for `$x[] =` or when dim_depth is 0.
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
     *
     * @throws UnanalyzableException
     */
    public function visit(Node $node) : Context
    {
        // TODO: Add more details.
        // This should only happen when the polyfill parser is used on invalid ASTs
        $this->emitIssue(
            Issue::Unanalyzable,
            $node->lineno
        );
        return $this->context;
    }

    /**
     * The following is an example of how this would happen.
     * (TODO: Check if the right-hand side is an object with offsetSet() or a reference?
     *
     * ```php
     * class C {
     *     function f() {
     *         return [ 24 ];
     *     }
     * }
     * (new C)->f()[1] = 42;
     * ```
     *
     * @param Node $unused_node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $unused_node) : Context
    {
        return $this->context;
    }

    /**
     * The following is an example of how this would happen.
     * TODO: Check that the left-hand side is a reference or defines offsetSet()?
     *
     * ```php
     * function &f() {
     *     $x = [ 24 ]; return $x;
     * }
     * f()[1] = 42;
     * ```
     *
     * @param Node $unused_node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $unused_node) : Context
    {
        return $this->context;
    }

    /**
     * The following is an example of how this would happen.
     *
     * ```php
     * class A{
     *     function &f() {
     *         $x = [ 24 ]; return $x;
     *     }
     * }
     * A::f()[1] = 42;
     * ```
     *
     * @param Node $unused_node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStaticCall(Node $unused_node) : Context
    {
        return $this->context;
    }

    /**
     * This happens for code like the following
     * ```
     * list($a) = [1, 2, 3];
     * ```
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitArray(Node $node) : Context
    {
        if ($this->right_type->hasTopLevelArrayShapeTypeInstances()) {
            $this->analyzeShapedArrayAssignment($node);
        } else {
            // common case
            $this->analyzeGenericArrayAssignment($node);
        }
        return $this->context;
    }

    /**
     * Analyzes code such as list($a) = [1, 2, 3];
     * @return void
     * @see self::visitArray()
     */
    private function analyzeShapedArrayAssignment(Node $node)
    {
        // Figure out the type of elements in the list
        $fallback_element_type = null;
        $get_fallback_element_type = function () use (&$fallback_element_type) : UnionType {
            return $fallback_element_type ?? ($fallback_element_type = $this->right_type->genericArrayElementTypes());
        };

        $expect_string_keys_lineno = false;
        $expect_int_keys_lineno = false;

        $key_set = [];

        foreach ($node->children ?? [] as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                // Track the element that was thrown away.
                $key_set[] = true;
                continue;
            }

            // Get the key and value nodes for each
            // array element we're assigning to
            // TODO: Check key types are valid?
            $key_node = $child_node->children['key'];

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

            if (\is_scalar($key_value)) {
                $element_type = UnionTypeVisitor::resolveArrayShapeElementTypesForOffset($this->right_type, $key_value);
                if ($element_type === null) {
                    $element_type = $get_fallback_element_type();
                } elseif ($element_type === false) {
                    $this->emitIssue(
                        Issue::TypeInvalidDimOffsetArrayDestructuring,
                        $child_node->lineno,
                        StringUtil::jsonEncode($key_value),
                        (string)$this->right_type
                    );
                    $element_type = $get_fallback_element_type();
                }
            } else {
                $element_type = $get_fallback_element_type();
            }

            $this->analyzeValueNodeOfShapedArray($element_type, $child_node->children['value']);
        }

        if (!Config::getValue('scalar_array_key_cast')) {
            $this->checkMismatchArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno);
        }
    }

    /**
     * @param Node|string|int|float $value_node
     * @return void
     */
    private function analyzeValueNodeOfShapedArray(
        UnionType $element_type,
        $value_node
    ) {
        if (!$value_node instanceof Node) {
            return;
        }
        $kind = $value_node->kind;
        if ($kind === \ast\AST_REF) {
            $value_node = $value_node->children['expr'];
            if (!$value_node instanceof Node) {
                return;
            }
        }
        if ($kind === \ast\AST_VAR) {
            $variable = Variable::fromNodeInContext(
                $value_node,
                $this->context,
                $this->code_base,
                false
            );

            // Set the element type on each element of
            // the list
            $variable->setUnionType($element_type);

            // Note that we're not creating a new scope, just
            // adding variables to the existing scope
            $this->context->addScopeVariable($variable);
        } elseif ($kind === \ast\AST_PROP) {
            try {
                $property = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $value_node
                ))->getProperty(false);

                // Set the element type on each element of
                // the list
                $property->setUnionType($element_type);
            } catch (UnanalyzableException $_) {
                // Ignore it. There's nothing we can do.
            } catch (NodeException $_) {
                // Ignore it. There's nothing we can do.
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return;
            }
        } else {
            $this->context = (new AssignmentVisitor(
                $this->code_base,
                $this->context,
                $value_node,
                $element_type,
                0
            ))->__invoke($value_node);
        }
    }  // TODO: Warn if $value_node is not a node. NativeSyntaxCheckPlugin already does this.

    /**
     * Analyzes code such as list($a) = function_returning_array();
     * @return void
     * @see self::visitArray()
     */
    private function analyzeGenericArrayAssignment(Node $node)
    {
        // Figure out the type of elements in the list
        $right_type = $this->right_type;
        if ($right_type->isEmpty()) {
            $element_type = UnionType::empty();
        } else {
            $array_access_types = $right_type->asArrayOrArrayAccessSubTypes($this->code_base);
            if ($array_access_types->isEmpty()) {
                $this->emitIssue(
                    Issue::TypeInvalidExpressionArrayDestructuring,
                    $node->lineno,
                    $right_type,
                    'array|ArrayAccess'
                );
            }
            $element_type =
                $array_access_types->genericArrayElementTypes();
        }

        $expect_string_keys_lineno = false;
        $expect_int_keys_lineno = false;

        $scalar_array_key_cast = Config::getValue('scalar_array_key_cast');

        foreach ($node->children ?? [] as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Get the key and value nodes for each
            // array element we're assigning to
            // TODO: Check key types are valid?
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

            $value_node = $child_node->children['value'];
            if (!($value_node instanceof Node)) {
                // Skip non-nodes to avoid crash
                // TODO: Emit a new issue type for https://github.com/phan/phan/issues/1693
            } elseif ($value_node->kind === \ast\AST_VAR) {
                $variable = Variable::fromNodeInContext(
                    $value_node,
                    $this->context,
                    $this->code_base,
                    false
                );

                // Set the element type on each element of
                // the list
                $variable->setUnionType($element_type);

                // Note that we're not creating a new scope, just
                // adding variables to the existing scope
                $this->context->addScopeVariable($variable);
            } elseif ($value_node->kind === \ast\AST_PROP) {
                try {
                    $property = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $value_node
                    ))->getProperty(false);

                    // Set the element type on each element of
                    // the list
                    $property->setUnionType($element_type);
                } catch (UnanalyzableException $exception) {
                    // Ignore it. There's nothing we can do.
                } catch (NodeException $exception) {
                    // Ignore it. There's nothing we can do.
                } catch (IssueException $exception) {
                    Issue::maybeEmitInstance(
                        $this->code_base,
                        $this->context,
                        $exception->getIssueInstance()
                    );
                    continue;
                }
            } else {
                $this->context = (new AssignmentVisitor(
                    $this->code_base,
                    $this->context,
                    $value_node,
                    $element_type,
                    0
                ))->__invoke($value_node);
            }
        }

        $this->checkMismatchArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno);
    }

    /**
     * @param int|false $expect_int_keys_lineno
     * @param int|false $expect_string_keys_lineno
     * @return void
     */
    private function checkMismatchArrayDestructuringKey($expect_int_keys_lineno, $expect_string_keys_lineno)
    {
        if ($expect_int_keys_lineno !== false || $expect_string_keys_lineno !== false) {
            $right_hand_key_type = GenericArrayType::keyTypeFromUnionTypeKeys($this->right_type);
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
    public function visitDim(Node $node) : Context
    {
        $expr_node = $node->children['expr'];
        if (!($expr_node instanceof Node)) {
            $this->emitIssue(
                Issue::InvalidWriteToTemporaryExpression,
                $node->lineno,
                Type::fromObject($expr_node)
            );
            return $this->context;
        }
        if ($expr_node->kind == \ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();

            if (Variable::isHardcodedVariableInScopeWithName($variable_name, $this->context->isInGlobalScope())) {
                return $this->analyzeSuperglobalDim($node, $variable_name);
            }
        }

        // TODO: Check if the unionType is valid for the []
        // For most types, it should be int|string, but SplObjectStorage and a few user-defined types will be exceptions.
        // Infer it from offsetSet?
        $dim_node = $node->children['dim'];
        if ($dim_node instanceof Node) {
            // TODO: Use ContextNode to infer dim_value
            $dim_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $dim_node
            );
            $dim_value = $dim_type->asSingleScalarValueOrNullOrSelf();
        } elseif (\is_scalar($dim_node)) {
            $dim_value = $dim_node;
            $dim_type = Type::fromObject($dim_node)->asUnionType();
        } else {
            // TODO: If the array shape has only one set of keys, then appending should add to that shape? Possibly not a common use case.
            $dim_type = null;
            $dim_value = null;
        }

        if ($dim_type !== null && !\is_object($dim_value)) {
            $right_type = ArrayShapeType::fromFieldTypes([
                $dim_value => $this->right_type,
            ], false)->asUnionType();
        } else {
            // Make the right type a generic (i.e. int -> int[])
            if ($dim_type !== null) {
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($dim_type);
            } elseif ($dim_node !== null) {
                $key_type_enum = GenericArrayType::KEY_MIXED;
            } else {
                $key_type_enum = GenericArrayType::KEY_INT;
            }
            $right_inner_type = $this->right_type;
            if ($right_inner_type->isEmpty()) {
                if ($key_type_enum === GenericArrayType::KEY_MIXED) {
                    $right_type = ArrayType::instance(false)->asUnionType();
                } else {
                    $right_type = GenericArrayType::fromElementType(MixedType::instance(false), false, $key_type_enum)->asUnionType();
                }
            } else {
                $right_type = $right_inner_type->asGenericArrayTypes($key_type_enum);
            }
        }

        // Recurse into whatever we're []'ing
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type,
            $this->dim_depth + 1,
            $dim_type
        ))->__invoke($expr_node);

        return $context;
    }

    /**
     * Analyze an assignment where $variable_name is a superglobal, and return the new context.
     * May create a new variable in $this->context.
     * TODO: Emit issues if the assignment is incompatible with the pre-existing type?
     */
    private function analyzeSuperglobalDim(Node $node, string $variable_name) : Context
    {
        $dim = $node->children['dim'];
        if ('GLOBALS' === $variable_name) {
            if (!\is_string($dim)) {
                // You're not going to believe this, but I just
                // found a piece of code like $GLOBALS[mt_rand()].
                // Super weird, right?
                return $this->context;
            }

            if (Variable::isHardcodedVariableInScopeWithName($dim, $this->context->isInGlobalScope())) {
                // Don't override types of superglobals such as $_POST, $argv through $_GLOBALS['_POST'] = expr either. TODO: Warn.
                return $this->context;
            }

            $variable = new Variable(
                $this->context,
                $dim,
                $this->right_type,
                0
            );

            $this->context->addGlobalScopeVariable(
                $variable
            );
        }
        // TODO: Assignment sanity checks.
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse, for an instance property.
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitProp(Node $node) : Context
    {
        // Get class list first, warn if the class list is invalid.
        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT, Issue::TypeExpectedObjectPropAccess);
        } catch (CodeBaseException $exception) {
            // This really shouldn't happen since the code
            // parsed cleanly. This should fatal.
            // throw $exception;
            return $this->context;
        } catch (\Exception $exception) {
            // If we can't figure out what kind of a class
            // this is, don't worry about it
            return $this->context;
        }

        $property_name = $node->children['prop'];

        // Things like $foo->$bar
        if (!\is_string($property_name)) {
            return $this->context;
        }

        foreach ($class_list as $clazz) {
            // Check to see if this class has the property or
            // a setter
            if (!$clazz->hasPropertyWithName($this->code_base, $property_name)) {
                if (!$clazz->hasMethodWithName($this->code_base, '__set')) {
                    continue;
                }
            }

            try {
                $property = $clazz->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    false,
                    $node
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }
            try {
                return $this->analyzePropAssignment($clazz, $property, $node);
            } catch (RecursionDepthException $_) {
                return $this->context;
            }
        }

        // Check if it is a built in class with dynamic properties but (possibly) no __set, such as SimpleXMLElement or stdClass or V8Js
        $is_class_with_arbitrary_types = isset($class_list[0]) ? $class_list[0]->getHasDynamicProperties($this->code_base) : false;

        if ($is_class_with_arbitrary_types || Config::getValue('allow_missing_properties')) {
            try {
                // Create the property
                $property = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getOrCreateProperty($property_name, false);

                $this->addTypesToProperty($property, $node);
            } catch (\Exception $_) {
                // swallow it
            }
        } elseif (\count($class_list) > 0) {
            $first_class = $class_list[0];
            $this->emitIssueWithSuggestion(
                Issue::UndeclaredProperty,
                $node->lineno ?? 0,
                ["{$first_class->getFQSEN()}->$property_name"],
                IssueFixSuggester::suggestSimilarProperty(
                    $this->code_base,
                    $this->context,
                    $first_class,
                    $property_name,
                    false
                )
            );
        } else {
            // If we hit this part, we couldn't figure out
            // the class, so we ignore the issue
        }

        return $this->context;
    }

    /**
     * This analyzes an assignment to an instance or static property.
     *
     * @param Node $node the left hand side of the assignment
     */
    private function analyzePropAssignment(Clazz $clazz, Property $property, Node $node) : Context
    {
        if ($property->isReadOnly()) {
            $this->analyzeAssignmentToReadOnlyProperty($property, $node);
        }
        // TODO: Iterate over individual types, don't look at the whole type at once?

        // If we're assigning to an array element then we don't
        // know what the array structure of the parameter is
        // outside of the scope of this assignment, so we add to
        // its union type rather than replace it.
        $property_union_type = $property->getUnionType();
        if ($this->dim_depth > 0) {
            if ($this->right_type->canCastToExpandedUnionType(
                $property_union_type,
                $this->code_base
            )) {
                $this->addTypesToProperty($property, $node);
                if (Config::get_strict_property_checking() && $this->right_type->typeCount() > 1) {
                    $this->analyzePropertyAssignmentStrict($property, $this->right_type, $node);
                }
            } elseif ($property_union_type->asExpandedTypes($this->code_base)->hasArrayAccess()) {
                // Add any type if this is a subclass with array access.
                $this->addTypesToProperty($property, $node);
            } else {
                // Convert array shape types to generic arrays to reduce false positive PhanTypeMismatchProperty instances.

                // TODO: If the codebase explicitly sets a phpdoc array shape type on a property assignment,
                // then preserve the array shape type.
                $new_types = $this->typeCheckDimAssignment($property_union_type, $node)
                                  ->withFlattenedArrayShapeOrLiteralTypeInstances();

                // TODO: More precise than canCastToExpandedUnionType
                if (!$new_types->canCastToExpandedUnionType(
                    $property_union_type,
                    $this->code_base
                )) {
                    // TODO: Don't emit if array shape type is compatible with the original value of $property_union_type
                    $this->emitIssue(
                        Issue::TypeMismatchProperty,
                        $node->lineno ?? 0,
                        (string)$new_types,
                        $property->getRepresentationForIssue(),
                        (string)$property_union_type
                    );
                } else {
                    if (Config::get_strict_property_checking() && $this->right_type->typeCount() > 1) {
                        $this->analyzePropertyAssignmentStrict($property, $this->right_type, $node);
                    }
                    $this->right_type = $new_types;
                    $this->addTypesToProperty($property, $node);
                }
            }
            return $this->context;
        } elseif ($clazz->isPHPInternal() && $clazz->getFQSEN() !== FullyQualifiedClassName::getStdClassFQSEN()) {
            // We don't want to modify the types of internal classes such as \ast\Node even if they are compatible
            // This would result in unpredictable results, and types which are more specific than they really are.
            // stdClass is an exception to this, for issues such as https://github.com/phan/phan/pull/700
            return $this->context;
        } else {
            if (($node->children['expr']->kind ?? null) === \ast\AST_VAR && $node->children['expr']->children['name'] === 'this') {
                $this->handleThisPropertyAssignmentInLocalScope($property);
            }
            // This is a regular assignment, not an assignment to an offset
            if (!$this->right_type->canCastToExpandedUnionType(
                $property_union_type,
                $this->code_base
            )
                && !($this->right_type->hasTypeInBoolFamily() && $property_union_type->hasTypeInBoolFamily())
                && !$clazz->getHasDynamicProperties($this->code_base)
            ) {
                if ($this->right_type->nonNullableClone()->canCastToExpandedUnionType($property_union_type, $this->code_base) &&
                        !$this->right_type->isType(NullType::instance(false))) {
                    if ($this->shouldSuppressIssue(Issue::TypeMismatchProperty, $node->lineno)) {
                        return $this->context;
                    }
                    $this->emitIssue(
                        Issue::PossiblyNullTypeMismatchProperty,
                        $node->lineno,
                        (string)$this->right_type,
                        $property->getRepresentationForIssue(),
                        (string)$property_union_type,
                        'null'
                    );
                } else {
                    // TODO: optionally, change the message from "::" to "->"?
                    $this->emitIssue(
                        Issue::TypeMismatchProperty,
                        $node->lineno,
                        (string)$this->right_type,
                        $property->getRepresentationForIssue(),
                        (string)$property_union_type
                    );
                }
                return $this->context;
            }

            if (Config::get_strict_property_checking() && $this->right_type->typeCount() > 1) {
                $this->analyzePropertyAssignmentStrict($property, $this->right_type, $node);
            }
        }

        // After having checked it, add this type to it
        $this->addTypesToProperty($property, $node);

        return $this->context;
    }

    /**
     * Modifies $this->context (if needed) to track the assignment to a property of $this within a function-like.
     * This handles conditional branches.
     *
     * @return void
     */
    private function handleThisPropertyAssignmentInLocalScope(Property $property)
    {
        $this->context = $this->context->withThisPropertySetToType($property, $this->right_type);
    }

    private function analyzeAssignmentToReadOnlyProperty(Property $property, Node $node)
    {
        $is_from_phpdoc = $property->isFromPHPDoc();
        $context = $property->getContext();
        if (!$is_from_phpdoc && $this->context->isInFunctionLikeScope()) {
            $method = $this->context->getFunctionLikeInScope($this->code_base);
            if ($method instanceof Method && strcasecmp($method->getName(), '__construct') === 0) {
                $class_type = $method->getClassFQSEN()->asType();
                if ($class_type->asExpandedTypes($this->code_base)->hasType($property->getClassFQSEN()->asType())) {
                    // This is a constructor setting its own properties or a base class's properties.
                    // TODO: Could support private methods
                    return;
                }
            }
        }
        $this->emitIssue(
            $is_from_phpdoc ? Issue::AccessReadOnlyMagicProperty : Issue::AccessReadOnlyProperty,
            $node->lineno ?? 0,
            $property->asPropertyFQSENString(),
            $context->getFile(),
            $context->getLineNumberStart()
        );
    }

    private function analyzePropertyAssignmentStrict(Property $property, UnionType $assignment_type, Node $node)
    {
        $type_set = $assignment_type->getTypeSet();
        if (\count($type_set) < 2) {
            throw new AssertionError('Expected to have at least two types when checking if types match in strict mode');
        }

        $property_union_type = $property->getUnionType();
        if ($property_union_type->hasTemplateTypeRecursive()) {
            $property_union_type = $property_union_type->asExpandedTypes($this->code_base);
        }

        $mismatch_type_set = UnionType::empty();
        $mismatch_expanded_types = null;

        // For the strict
        foreach ($type_set as $type) {
            // Expand it to include all parent types up the chain
            $individual_type_expanded = $type->asExpandedTypes($this->code_base);

            // See if the argument can be cast to the
            // parameter
            if (!$individual_type_expanded->canCastToUnionType(
                $property_union_type
            )) {
                $mismatch_type_set = $mismatch_type_set->withType($type);
                if ($mismatch_expanded_types === null) {
                    // Warn about the first type
                    $mismatch_expanded_types = $individual_type_expanded;
                }
            }
        }


        if ($mismatch_expanded_types === null) {
            // No mismatches
            return;
        }
        if ($this->shouldSuppressIssue(Issue::TypeMismatchProperty, $node->lineno)) {
            // TypeMismatchProperty also suppresses PhanPossiblyNullTypeMismatchProperty, etc.
            return;
        }

        $this->emitIssue(
            self::getStrictPropertyMismatchIssueType($mismatch_type_set),
            $node->lineno ?? 0,
            (string)$this->right_type,
            $property->getRepresentationForIssue(),
            (string)$property_union_type,
            (string)$mismatch_expanded_types
        );
    }

    private static function getStrictPropertyMismatchIssueType(UnionType $union_type) : string
    {
        if ($union_type->typeCount() === 1) {
            $type = $union_type->getTypeSet()[0];
            if ($type instanceof NullType) {
                return Issue::PossiblyNullTypeMismatchProperty;
            }
            if ($type instanceof FalseType) {
                return Issue::PossiblyFalseTypeMismatchProperty;
            }
        }
        return Issue::PartialTypeMismatchProperty;
    }

    /**
     * @param Property $property - The property which should have types added to it
     *
     * @return void
     */
    private function addTypesToProperty(Property $property, Node $node)
    {
        $original_property_types = $property->getUnionType();
        if ($original_property_types->isEmpty()) {
            // TODO: Be more precise?
            $property->setUnionType($this->right_type->withFlattenedArrayShapeOrLiteralTypeInstances());
            return;
        }

        if ($this->dim_depth > 0) {
            $new_types = $this->typeCheckDimAssignment($original_property_types, $node);
        } else {
            $new_types = $this->right_type;
        }
        $has_literals = $original_property_types->hasLiterals();
        $new_types = $new_types->withFlattenedArrayShapeTypeInstances();

        $updated_property_types = $original_property_types;
        foreach ($new_types->getTypeSet() as $new_type) {
            if ($new_type instanceof MixedType) {
                // Don't add MixedType to a non-empty property - It makes inferences on that property useless.
                continue;
            }

            // Only allow compatible types to be added to declared properties.
            // Allow anything to be added to dynamic properties.
            // TODO: Be more permissive about declared properties without phpdoc types.
            if (!$new_type->asExpandedTypes($this->code_base)->canCastToUnionType($original_property_types) && !$property->isDynamicProperty()) {
                continue;
            }

            // Check for adding a specific array to as generic array as a workaround for #1783
            if (\get_class($new_type) === ArrayType::class && $original_property_types->hasGenericArray()) {
                continue;
            }
            if (!$has_literals) {
                $new_type = $new_type->asNonLiteralType();
            }
            $updated_property_types = $updated_property_types->withType($new_type);
        }

        // TODO: Add an option to check individual types, not just the whole union type?
        //       If that is implemented, verify that generic arrays will properly cast to regular arrays (public $x = [];)
        $property->setUnionType($updated_property_types);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @see self::visitProp()
     */
    public function visitStaticProp(Node $node) : Context
    {
        $property_name = $node->children['prop'];

        // Things like self::${$x}
        if (!\is_string($property_name)) {
            return $this->context;
        }

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, Issue::TypeExpectedObjectStaticPropAccess);
        } catch (CodeBaseException $exception) {
            // This really shouldn't happen since the code
            // parsed cleanly. This should fatal.
            // throw $exception;
            return $this->context;
        } catch (\Exception $exception) {
            // If we can't figure out what kind of a class
            // this is, don't worry about it
            return $this->context;
        }

        foreach ($class_list as $clazz) {
            // Check to see if this class has the property
            if (!$clazz->hasPropertyWithName($this->code_base, $property_name)) {
                continue;
            }

            try {
                // Look for static properties with that $property_name
                $property = $clazz->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    true
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }

            try {
                return $this->analyzePropAssignment($clazz, $property, $node);
            } catch (RecursionDepthException $_) {
                return $this->context;
            }
        }

        if (\count($class_list) > 0) {
            $this->emitIssue(
                Issue::UndeclaredStaticProperty,
                $node->lineno ?? 0,
                $property_name,
                (string)$class_list[0]->getFQSEN()
            );
        } else {
            // If we hit this part, we couldn't figure out
            // the class, so we ignore the issue
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
        try {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return $this->context;
        }
        // Don't analyze variables when we can't determine their names.
        if ($variable_name === '') {
            return $this->context;
        }

        // Check to see if the variable already exists
        if ($this->context->getScope()->hasVariableWithName(
            $variable_name
        )) {
            $variable =
                $this->context->getScope()->getVariableByName(
                    $variable_name
                );

            // If the variable isn't a pass-by-reference parameter
            // we clone it so as to not disturb its previous types
            // as we replace it.
            // TODO: Do a better job of analyzing references
            if ($variable instanceof Parameter) {
                if (!$variable->isPassByReference()) {
                    $variable = clone($variable);
                }
            } elseif (!($variable instanceof PassByReferenceVariable)) {
                $variable = clone($variable);
            }

            // If we're assigning to an array element then we don't
            // know what the array structure of the parameter is
            // outside of the scope of this assignment, so we add to
            // its union type rather than replace it.
            if ($this->dim_depth > 0) {
                $old_variable_union_type = $variable->getUnionType();
                $right_type = $this->typeCheckDimAssignment($old_variable_union_type, $node);
                if ($old_variable_union_type->isEmpty()) {
                    $old_variable_union_type = ArrayType::instance(false)->asUnionType();
                }
                // TODO: Make the behavior more precise for $x['a']['b'] = ...; when $x is an array shape.
                if ($this->dim_depth > 1 || ($old_variable_union_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->hasTopLevelNonArrayShapeTypeInstances() || $right_type->isEmpty())) {
                    $variable->setUnionType($old_variable_union_type->withUnionType(
                        $right_type
                    ));
                } else {
                    $variable->setUnionType(ArrayType::combineArrayTypesOverriding(
                        $right_type,
                        $old_variable_union_type
                    ));
                }
            } else {
                $variable->setUnionType($this->right_type);
            }

            $this->context->addScopeVariable(
                $variable
            );

            return $this->context;
        } else {
            // no such variable exists, check for invalid array Dim access
            if ($this->dim_depth > 0) {
                $this->emitIssue(
                    Issue::UndeclaredVariableDim,
                    $node->lineno ?? 0,
                    $variable_name
                );
            }
        }

        $variable = Variable::fromNodeInContext(
            $this->assignment_node,
            $this->context,
            $this->code_base,
            false
        );
        if ($this->dim_depth > 0) {
            // Reduce false positives: If $variable did not already exist, assume it may already have other array fields
            // (e.g. in a loop, or in the global scope)
            $variable->setUnionType($this->right_type->withType(ArrayType::instance(false)));
        } else {
            // Set that type on the variable
            $variable->setUnionType(
                $this->right_type
            );
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * @param UnionType $assign_type - The type which is being added to
     * @return UnionType - Usually the unmodified UnionType. Sometimes, the adjusted type, e.g. for string modification.
     */
    public function typeCheckDimAssignment(UnionType $assign_type, Node $node) : UnionType
    {
        static $int_or_string_type = null;
        static $mixed_type = null;
        static $string_array_type = null;
        static $simple_xml_element_type = null;

        if ($int_or_string_type === null) {
            $int_or_string_type = UnionType::fromFullyQualifiedString('int|string');
            $mixed_type = MixedType::instance(false);
            $string_array_type = UnionType::fromFullyQualifiedString('string[]');
            $simple_xml_element_type =
                Type::fromNamespaceAndName('\\', 'SimpleXMLElement', false);
        }
        $dim_type = $this->dim_type;
        $right_type = $this->right_type;
        if ($assign_type->isEmpty() || ($assign_type->hasGenericArray() && !$assign_type->asExpandedTypes($this->code_base)->hasArrayAccess())) {
            // For empty union types or 'array', expect the provided dimension to be able to cast to int|string
            if ($dim_type && !$dim_type->isEmpty() && !$dim_type->canCastToUnionType($int_or_string_type)) {
                $this->emitIssue(
                    Issue::TypeMismatchDimAssignment,
                    $node->lineno ?? 0,
                    (string)$assign_type,
                    (string)$dim_type,
                    (string)$int_or_string_type
                );
            }
            return $right_type;
        }
        $assign_type_expanded = $assign_type->asExpandedTypes($this->code_base);
        //echo "$assign_type_expanded : " . json_encode($assign_type_expanded->hasArrayLike()) . "\n";

        // TODO: Better heuristic to deal with false positives on ArrayAccess subclasses
        if ($assign_type_expanded->hasArrayAccess() && !$assign_type_expanded->hasGenericArray()) {
            return UnionType::empty();
        }

        if (!$assign_type_expanded->hasArrayLike()) {
            if ($assign_type->hasNonNullStringType()) {
                // Are we assigning to a variable/property of type 'string' (with no ArrayAccess or array types)?
                if (\is_null($dim_type)) {
                    $this->emitIssue(
                        Issue::TypeMismatchDimEmpty,
                        $node->lineno ?? 0,
                        (string)$assign_type,
                        'int'
                    );
                } elseif (!$dim_type->isEmpty() && !$dim_type->hasNonNullIntType()) {
                    $this->emitIssue(
                        Issue::TypeMismatchDimAssignment,
                        $node->lineno,
                        (string)$assign_type,
                        (string)$dim_type,
                        'int'
                    );
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgumentNullable false positive for static
                    if ($right_type->canCastToUnionType($string_array_type)) {
                        // e.g. $a = 'aaa'; $a[0] = 'x';
                        // (Currently special casing this, not handling deeper dimensions)
                        return StringType::instance(false)->asUnionType();
                    }
                }
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable false positive for static
            } elseif (!$assign_type->hasType($mixed_type) && !$assign_type->hasType($simple_xml_element_type)) {
                // Imitate the check in UnionTypeVisitor, don't warn for mixed, etc.
                $this->emitIssue(
                    Issue::TypeArraySuspicious,
                    $node->lineno,
                    (string)$assign_type
                );
            }
        }
        return $right_type;
    }

    /**
     * @param Node $node
     * A node to parse of type AST_REF (found only in foreach)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitRef(Node $node) : Context
    {
        // Note: AST_REF is only ever generated in AST_FOREACH, so this should be fine.
        $var = $node->children['var'];
        if ($var instanceof Node) {
            return $this->__invoke($var);
        }
        $this->emitIssue(
            Issue::Unanalyzable,
            $node->lineno
        );
        return $this->context;
    }
}
