<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\Analyze\Analyzable;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\IssueException;
use \Phan\Exception\NodeException;
use \Phan\Exception\TypeException;
use \Phan\Issue;
use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Parameter;
use \Phan\Language\Element\Property;
use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;
use \ast\Node\Decl;

class AssignmentVisitor extends KindVisitorImplementation {

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
    private $is_dim_assignment = false;

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
        bool $is_dim_assignment = false
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->assignment_node = $assignment_node;
        $this->right_type = $right_type;
        $this->is_dim_assignment = $is_dim_assignment;
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
    public function visit(Node $node) : Context {
        assert(false,
            "Unknown left side of assignment {$this->context}");

        return $this->visitVar($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitList(Node $node) : Context {
        // Figure out the type of elements in the list
        $element_type =
            $this->right_type->genericArrayElementTypes();

        foreach($node->children ?? [] as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                continue;
            }

            $variable = Variable::fromNodeInContext(
                $child_node,
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
    public function visitDim(Node $node) : Context {

        // Make the right type a generic (i.e. int -> int[])
        $right_type =
            $this->right_type->asGenericArrayTypes();

        if ($node->children['expr']->kind == \ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getVariableName();

            if ('GLOBALS' === $variable_name) {
                $dim = $node->children['dim'];

                if(is_string($dim)) {
                    // You're not going to believe this, but I just
                    // found a piece of code like $GLOBALS[mt_rand()].
                    // Super weird, right?
                    assert(is_string($dim),
                        "dim is not a string at {$this->context}");

                    $variable = new Variable(
                        $this->context,
                        $dim,
                        $this->right_type,
                        $node->flags ?? 0
                    );

                    $this->context->getScope()
                        ->withGlobalVariable($variable);

                    return $this->context;
                }
            }
        }

        // Recurse into whatever we're []'ing
        $context = (new AssignmentVisitor(
            $this->code_base,
            $this->context,
            $node,
            $right_type,
            true
        ))($node->children['expr']);

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
    public function visitProp(Node $node) : Context {

        $property_name = $node->children['prop'];

        // Things like $foo->$bar
        if (!is_string($property_name)) {
            return $this->context;
        }

        assert(is_string($property_name),
            "Property must be string in context {$this->context}");

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getClassList();
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
                    $this->context
                );
            } catch (IssueException $exception) {
                $exception->getIssueInstance()();
                return $this->context;
            }

            if (!$this->right_type->canCastToExpandedUnionType(
                $property->getUnionType(),
                $this->code_base
            )) {
                Issue::emit(
                    Issue::TypeMismatchProperty,
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    (string)$this->right_type,
                    "{$clazz->getFQSEN()}::{$property->getName()}",
                    (string)$property->getUnionType()
                );

                return $this->context;
            }

            // After having checked it, add this type to it
            $property->getUnionType()->addUnionType(
                $this->right_type
            );

            return $this->context;
        }

        if (Config::get()->allow_missing_properties) {
            try {
                // Create the property
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getOrCreateProperty($property_name);
            } catch (\Exception $exception) {
                // swallow it
            }
        } else if (!empty($class_list)) {
            Issue::emit(
                Issue::UndeclaredProperty,
                $this->context->getFile(),
                $node->lineno ?? 0,
                $property_name
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
    public function visitStaticProp(Node $node) : Context {
        return $this->visitVar($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node) : Context {
        $variable_name = (new ContextNode(
            $this->code_base,
            $this->context,
            $node
        ))->getVariableName();

        // Check to see if the variable already exists
        if ($this->context->getScope()->hasVariableWithName(
            $variable_name
        )) {
            $variable =
                $this->context->getScope()->getVariableWithName(
                    $variable_name
                );

            // If we're assigning to an array element then we don't
            // know what the constitutation of the parameter is
            // outside of the scope of this assignment, so we add to
            // its union type rather than replace it.
            if ($this->is_dim_assignment) {
                $variable->getUnionType()->addUnionType(
                    $this->right_type
                );

            } else {
                // If the variable isn't a pass-by-reference paramter
                // we clone it so as to not disturb its previous types
                // as we replace it.
                if ($variable instanceof Parameter) {
                    if ($variable->isPassByReference()) {
                    } else {
                        $variable = clone($variable);
                    }
                } else {
                    $variable = clone($variable);
                }

                $variable->setUnionType($this->right_type);
            }

            $this->context->addScopeVariable(
                $variable
            );

            return $this->context;
        }

        $variable = Variable::fromNodeInContext(
            $this->assignment_node,
            $this->context,
            $this->code_base
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

}
