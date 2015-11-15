<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Analyze\Analyzable;
use \Phan\Exception\AccessException;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
use \Phan\Debug;
use \Phan\Language\AST;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{
    Comment,
    Property,
    Variable
};
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

class AssignmentVisitor extends KindVisitorImplementation {
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
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        Context $context,
        Node $assignment_node,
        UnionType $right_type
    ) {
        $this->context = $context;
        $this->assignment_node = $assignment_node;
        $this->right_type = $right_type;
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
            $this->right_type->asNonGenericTypes();

        foreach($node->children as $child_node) {
            // Some times folks like to pass a null to
            // a list to throw the element away. I'm not
            // here to judge.
            if (!($child_node instanceof Node)) {
                continue;
            }

            $variable = Variable::fromNodeInContext(
                $child_node,
                $this->context,
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
            $this->right_type->asGenericTypes();


        if ($node->children['expr']->kind == \ast\AST_VAR) {
            $variable_name = AST::variableName($node);

            if ('GLOBALS' === $variable_name) {
                $dim = $node->children['dim'];

                $variable = new Variable(
                    $this->context,
                    Comment::fromStringInContext(
                        $node->docComment ?? '',
                        $this->context
                    ),
                    $dim,
                    $this->right_type,
                    $node->flags
                );

                $this->context->getScope()
                    ->withGlobalVariable($variable);

                return $this->context;
            }
        }

        // Recurse into whatever we're []'ing
        $context =
            (new Element($node->children['expr']))->acceptKindVisitor(
                new AssignmentVisitor(
                    $this->context,
                    $node,
                    $right_type
                )
            );


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
            $clazz =
                AST::classFromNodeInContext($node, $this->context);
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EFATAL,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );
        } catch (NodeException $exception) {
            // If we can't figure out what kind of a class
            // this is, don't worry about it
            return $this->context;
        }

        if (!$clazz->hasPropertyWithName($property_name)) {

            // Check to see if the class has a __set method
            if (!$clazz->hasMethodWithName('__set')) {
                Log::err(
                    Log::EAVAIL,
                    "Missing property with name '$property_name'",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            return $this->context;
        }

        try {
            $property = $clazz->getPropertyWithNameFromContext(
                $property_name,
                $this->context
            );
        } catch (AccessException $exception) {
            Log::err(
                Log::EACCESS,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        if (!$this->right_type->canCastToExpandedUnionType(
            $property->getUnionType(),
            $this->context->getCodeBase()
        )) {
            Log::err(
                Log::ETYPE,
                "assigning {$this->right_type} to property but {$clazz->getFQSEN()}::{$property->getName()} is {$property->getUnionType()}",
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        // After having checked it, add this type to it
        $property->getUnionType()->addUnionType(
            $this->right_type
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
        $variable_name = AST::variableName($node);

        // Check to see if the variable already exists
        if ($this->context->getScope()->hasVariableWithName(
            $variable_name
        )) {
            $variable =
                $this->context->getScope()->getVariableWithName(
                    $variable_name
                );

            $variable->setUnionType($this->right_type);

            $this->context->addScopeVariable(
                $variable
            );

            return $this->context;
        }

        $variable = Variable::fromNodeInContext(
            $this->assignment_node,
            $this->context
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
