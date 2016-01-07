<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\Element\Variable;
use \Phan\Log;
use \ast\Node;
use \ast\Node\Decl;

class ArgumentVisitor extends KindVisitorImplementation {

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
     */
    public function visit(Node $node) {
    }

    public function visitVar(Node $node) {
        try {
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getOrCreateVariable();
            $variable->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    public function visitStaticProp(Node $node) {
        $this->visitProp($node);
    }

    public function visitProp(Node $node) {
        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getOrCreateProperty($node->children['prop']);

            $property->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    public function visitClosure(Decl $node) {
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClosure();

            $method->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

}
