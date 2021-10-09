<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Exception;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;

/**
 * Used to check if a method is pure.
 * Throws NodeException if it sees a node that isn't likely to be in a method that is free of side effects.
 *
 * This ignores many edge cases, including:
 * - Magic properties
 * - The possibility of emitting notices or throwing
 * - Whether or not referenced elements exist (Phan checks that elsewhere)
 *
 * @phan-file-suppress PhanThrowTypeAbsent
 */
class InferPureAndNoThrowVisitor extends InferPureSnippetVisitor
{
    use InferPureVisitorTrait {
        throwNodeException as visitAssign;
        throwNodeException as visitAssignRef;
        throwNodeException as visitUnset;

        throwNodeException as visitCall;
        throwNodeException as visitNullsafeMethodCall;
        throwNodeException as visitMethodCall;
        throwNodeException as visitStaticCall;
        throwNodeException as visitProp;

        throwNodeException as visitYield;
        throwNodeException as visitYieldFrom;

        maybeInvokeAllChildNodes as visitReturn;
        maybeInvokeAllChildNodes as visitGoto;
        maybeInvokeAllChildNodes as visitBreak;
        maybeInvokeAllChildNodes as visitContinue;
        maybeInvokeAllChildNodes as visitEcho;
        maybeInvokeAllChildNodes as visitPrint;
    }

    /**
     * Check if the statements and expressions found in $node are unlikely to throw
     * @param Node|int|string|float|null $node
     */
    public static function isUnlikelyToThrow(CodeBase $code_base, Context $context, $node): bool
    {
        if (!$node instanceof Node) {
            return true;
        }
        try {
            (new self($code_base, $context))->__invoke($node);
            return true;
        } catch (NodeException $_) {
            return false;
        }
    }

    /**
     * @param Node $node the node of the call, with 'args'
     * @unused-param $method
     * @return never
     * @override
     */
    protected function checkCalledFunction(Node $node, FunctionInterface $method): void
    {
        throw new NodeException($node, 'not implemented');
    }


    /**
     * @override
     * @throws NodeException
     */
    public function visitMatchArmList(Node $node): void
    {
        foreach ($node->children as $child) {
            if ($child instanceof Node && $child->children['cond'] === null) {
                // this has a default branch
                $this->maybeInvokeAllChildNodes($node);
                return;
            }
        }
        // match expressions without a default branch can throw UnhandledMatchError
        throw new NodeException($node);
    }

    public function visitNew(Node $node): void
    {
        $name_node = $node->children['class'];
        if (!($name_node instanceof Node && $name_node->kind === ast\AST_NAME)) {
            throw new NodeException($node);
        }
        // "Fatal error: Cannot create Closure for new expression" (for AST_CALLABLE_CONVERT) is caught elsewhere
        $this->__invoke($node->children['args']);
        try {
            $class_list = (new ContextNode($this->code_base, $this->context, $name_node))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);
        } catch (Exception $_) {
            throw new NodeException($name_node);
        }
        if (!$class_list) {
            throw new NodeException($name_node, 'no class found');
        }
        foreach ($class_list as $class) {
            if (!$class->hasMethodWithName($this->code_base, '__construct', true)) {
                continue;
            }
            if ($class->isPHPInternal()) {
                throw new NodeException($node);
            }
            $this->checkCalledFunction($node, $class->getMethodByName($this->code_base, '__construct'));
        }
    }
}
