<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\AnalysisVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use ast\Node;

class AssignmentVisitor extends AnalysisVisitor
{
    /**
     * @var Node
     */
    private $assignment_node;

    /**
     * @var UnionType
     */
    private $right_type;

    /**
     * @var bool
     * True if this assignment is to an array parameter such as
     * in `$foo[3] = 42`. We need to know this in order to decide
     * if we're replacing the union type or if we're adding a
     * type to the union type.
     */
    private $is_dim_assignment;

    /**
     * @var ?UnionType
     * Non-null if this this assignment is to an array parameter such as
     * in `$foo[3] = 42` (type would be int). We need to know this in order to decide
     * to type check the assignment (e.g. array keys are int|string, string offsets are int)
     * type to the union type.
     *
     * Null for `$foo[] = 42` or when is_dim_assignment is false.
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
     * @param bool $is_dim_assignment
     * True if this assignment is to an array parameter such as
     * in `$foo[3] = 42`. We need to know this in order to decide
     * if we're replacing the union type or if we're adding a
     * type to the union type.
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $assignment_node,
        UnionType $right_type,
        bool $is_dim_assignment = false,
        UnionType $dim_type = null
    ) {
        parent::__construct($code_base, $context);

        $this->assignment_node = $assignment_node;
        $this->right_type = $right_type;
        $this->is_dim_assignment = $is_dim_assignment;
        $this->dim_type = $dim_type;  // null for `$x[] =` or when is_dim_assignment is false.
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
        throw new \AssertionError(
            "Unknown left side of assignment in {$this->context} with node type "
            . Debug::nodeName($node)
        );
    }

    /**
     * The following is an example of how this'd happen.
     * (TODO: Check if the right hand side is an object with offsetSet() or a reference?
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
     * The following is an example of how this'd happen.
     * TODO: Check that the left hand side is a reference or defines offsetSet()?
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
     * The following is an example of how this'd happen.
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
        // Figure out the type of elements in the list
        $element_type =
            $this->right_type->genericArrayElementTypes();

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
            // $key_node = $child_node->children['key'];
            $value_node = $child_node->children['value'];

            if ($value_node->kind == \ast\AST_VAR) {
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
            } elseif ($value_node->kind == \ast\AST_PROP) {
                try {
                    $property = (new ContextNode(
                        $this->code_base,
                        $this->context,
                        $value_node
                    ))->getProperty($value_node->children['prop'], false);

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
                    $node,
                    $element_type,
                    false
                ))($value_node);
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
    public function visitDim(Node $node) : Context
    {
        // Make the right type a generic (i.e. int -> int[])
        $right_type =
            $this->right_type->asGenericArrayTypes();

        if ($node->children['expr']->kind == \ast\AST_VAR) {
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
            $dim_type = UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $node->children['dim']
            );
        } elseif (\is_scalar($dim_node) && $dim_node !== null) {
            $dim_type = Type::fromObject($dim_node)->asUnionType();
        } else {
            $dim_type = null;
        }

        // Recurse into whatever we're []'ing
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type,
            true,
            $dim_type
        ))($node->children['expr']);

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
                $node->flags ?? 0
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
                    false
                );
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }
            // TODO: Iterate over individual types, don't look at the whole type at once?

            // If we're assigning to an array element then we don't
            // know what the constitutation of the parameter is
            // outside of the scope of this assignment, so we add to
            // its union type rather than replace it.
            $property_union_type = $property->getUnionType();
            if ($this->is_dim_assignment) {
                if ($this->right_type->canCastToExpandedUnionType(
                    $property_union_type,
                    $this->code_base
                )
                ) {
                    $this->addTypesToProperty($property, $node);
                } elseif ($property_union_type->asExpandedTypes($this->code_base)->hasArrayAccess()) {
                    // Add any type if this is a subclass with array access.
                    $this->addTypesToProperty($property, $node);
                } else {
                    $new_types = $this->typeCheckDimAssignment($property_union_type, $node);
                    if ($new_types === $this->right_type || !$new_types->canCastToExpandedUnionType(
                        $property_union_type,
                        $this->code_base
                    )) {
                        $this->emitIssue(
                            Issue::TypeMismatchProperty,
                            $node->lineno ?? 0,
                            (string)$new_types,
                            "{$clazz->getFQSEN()}::{$property->getName()}",
                            (string)$property_union_type
                        );
                    } else {
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
                if (!$this->right_type->canCastToExpandedUnionType(
                    $property_union_type,
                    $this->code_base
                )
                    && !($this->right_type->hasTypeInBoolFamily() && $property_union_type->hasTypeInBoolFamily())
                    && !$clazz->getHasDynamicProperties($this->code_base)
                ) {
                    // TODO: optionally, change the message from "::" to "->"?
                    $this->emitIssue(
                        Issue::TypeMismatchProperty,
                        $node->lineno ?? 0,
                        (string)$this->right_type,
                        "{$clazz->getFQSEN()}::{$property->getName()}",
                        (string)$property_union_type
                    );
                    return $this->context;
                }
            }

            // After having checked it, add this type to it
            $this->addTypesToProperty($property, $node);

            return $this->context;
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
            } catch (\Exception $exception) {
                // swallow it
            }
        } elseif (!empty($class_list)) {
            $this->emitIssue(
                Issue::UndeclaredProperty,
                $node->lineno ?? 0,
                "{$class_list[0]->getFQSEN()}->$property_name"
            );
        } else {
            // If we hit this part, we couldn't figure out
            // the class, so we ignore the issue
        }

        return $this->context;
    }

    /**
     * @param Property $property - The property which should have types added to it
     *
     * @return void
     */
    private function addTypesToProperty(Property $property, Node $node)
    {
        $property_types = $property->getUnionType();
        if ($property_types->isEmpty()) {
            $property_types->addUnionType($this->right_type);
            return;
        }
        if ($this->is_dim_assignment) {
            $new_types = $this->typeCheckDimAssignment($property_types, $node);
        } else {
            $new_types = $this->right_type;
        }
        // Don't add MixedType to a non-empty property - It makes inferences on that property useless.
        if ($new_types->hasType(MixedType::instance(false))) {
            $new_types = clone($new_types);
            $new_types->removeType(MixedType::instance(false));
        }
        // TODO: Add an option to check individual types, not just the whole union type?
        //       If that is implemented, verify that generic arrays will properly cast to regular arrays (public $x = [];)
        $property_types->addUnionType($new_types);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     *
     * @see $this->visitProp
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

            if (!$this->right_type->canCastToExpandedUnionType(
                $property->getUnionType(),
                $this->code_base
            )
                && !($this->right_type->hasTypeInBoolFamily() && $property->getUnionType()->hasTypeInBoolFamily())
            ) {
                // Currently, same warning type for static and non-static property type mismatches.
                $this->emitIssue(
                    Issue::TypeMismatchProperty,
                    $node->lineno ?? 0,
                    (string)$this->right_type,
                    "{$clazz->getFQSEN()}::{$property->getName()}",
                    (string)$property->getUnionType()
                );

                return $this->context;
            } else {
                // If we're assigning to an array element then we don't
                // know what the constitutation of the parameter is
                // outside of the scope of this assignment, so we add to
                // its union type rather than replace it.
                if ($this->is_dim_assignment) {
                    $right_type = $this->typeCheckDimAssignment($property->getUnionType(), $node);
                    $property->getUnionType()->addUnionType(
                        $right_type
                    );
                    return $this->context;
                }
            }

            // After having checked it, add this type to it
            $property->getUnionType()->addUnionType(
                $this->right_type
            );

            return $this->context;
        }

        if (!empty($class_list)) {
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

            // If we're assigning to an array element then we don't
            // know what the constitutation of the parameter is
            // outside of the scope of this assignment, so we add to
            // its union type rather than replace it.
            if ($this->is_dim_assignment) {
                $right_type = $this->typeCheckDimAssignment($variable->getUnionType(), $node);
                $variable->getUnionType()->addUnionType(
                    $right_type
                );
            } else {
                // If the variable isn't a pass-by-reference parameter
                // we clone it so as to not disturb its previous types
                // as we replace it.
                if ($variable instanceof Parameter) {
                    if (!$variable->isPassByReference()) {
                        $variable = clone($variable);
                    }
                } elseif (!($variable instanceof PassByReferenceVariable)) {
                    $variable = clone($variable);
                }

                $variable->setUnionType($this->right_type);
            }

            $this->context->addScopeVariable(
                $variable
            );

            return $this->context;
        } else {
            // no such variable exists, check for invalid array Dim access
            if ($this->is_dim_assignment) {
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

        // Set that type on the variable
        $variable->getUnionType()->addUnionType(
            $this->right_type
        );

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
        static $int_type = null;
        static $string_array_type = null;
        if ($int_or_string_type === null) {
            // clone these if they're returned by the function, they may be modified by callers.
            $int_or_string_type = UnionType::fromFullyQualifiedString('int|string');
            $int_type = IntType::instance(false);
            $string_array_type = UnionType::fromFullyQualifiedString('string[]');
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
        if ($assign_type->hasType(StringType::instance(false)) && !$assign_type->asExpandedTypes($this->code_base)->hasArrayLike()) {
            // Are we assigning to a variable/property of type 'string' (with no ArrayAccess or array types)?
            if (\is_null($dim_type)) {
                $this->emitIssue(
                    Issue::TypeMismatchDimEmpty,
                    $node->lineno ?? 0,
                    (string)$assign_type,
                    (string)$int_type
                );
            } elseif (!$dim_type->isEmpty() && !$dim_type->hasType($int_type)) {
                $this->emitIssue(
                    Issue::TypeMismatchDimAssignment,
                    $node->lineno ?? 0,
                    (string)$assign_type,
                    (string)$dim_type,
                    (string)$int_type
                );
            } else {
                if ($right_type->canCastToUnionType($string_array_type)) {
                    // e.g. $a = 'aaa'; $a[0] = 'x';
                    // (Currently special casing this, not handling deeper dimensions)
                    return StringType::instance(false)->asUnionType();
                }
            }
            return $right_type;
        }
        return $right_type;
    }
}
