<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Langauge\Type;
use Phan\Language\Type\NullType;
use Phan\Language\Context;
use Phan\Language\UnionType;
use ast\Node;

class ConditionVisitor extends KindVisitorImplementation
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
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
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
    public function visitBinaryOp(Node $node) : Context
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
    public function visitUnaryOp(Node $node) : Context
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
    public function visitCoalesce(Node $node) : Context
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
    public function visitIsset(Node $node) : Context
    {
        return $this->context;

        /*
        // Only look at things of the form
        // `isset($variable)`
        if ($node->children['var']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['var']
            ))->getVariable();

            $v0 = $variable;

            // Make a copy of the variable
            $variable = clone($variable);

            // Remove null from the list of possible types
            // given that we know that the variable is
            // set
            $variable->getUnionType()->removeType(
                NullType::instance()
            );

            // Overwrite the variable with its new type
            $this->context->addScopeVariable(
                $variable
            );
        } catch (\Exception $exception) {
            // Swallow it
        }

        return $this->context;
        */
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

        // Only look at things of the form
        // `$variable instanceof ClassName`
        if ($node->children['expr']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getVariable();

            // Get the type that we're checking it against
            $type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['class']
            );

            // Make a copy of the variable
            $variable = clone($variable);

            // Add the type to the variable
            $variable->getUnionType()->addUnionType($type);

            // Overwrite the variable with its new type
            $this->context->addScopeVariable(
                $variable
            );

        } catch (\Exception $exception) {
            // Swallow it
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
    public function visitEmpty(Node $node) : Context
    {
        return $this->context;
    }
}
