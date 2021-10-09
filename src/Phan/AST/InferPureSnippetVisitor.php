<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Plugin\Internal\UseReturnValuePlugin;
use Phan\Plugin\Internal\UseReturnValuePlugin\UseReturnValueVisitor;

use function is_string;

/**
 * Used to check if a snippet in a method is pure.
 * Throws NodeException if it sees a node that isn't likely to be in a method that is free of side effects.
 * (or if the snippet can jump to a location outside of the snippet)
 *
 * This ignores many edge cases, including:
 * - Magic properties
 * - The possibility of emitting notices or throwing
 * - Whether or not referenced elements exist (Phan checks that elsewhere)
 *
 * @phan-file-suppress PhanThrowTypeAbsent
 */
class InferPureSnippetVisitor extends InferPureVisitor
{
    use InferPureVisitorTrait {
        throwNodeException as visitReturn;
        throwNodeException as visitThrow;
        throwNodeException as visitYield;
        throwNodeException as visitYieldFrom;

        // TODO(optional) track actual goto labels
        throwNodeException as visitGoto;
        throwNodeException as visitUnset;
    }

    public function __construct(CodeBase $code_base, Context $context)
    {
        parent::__construct($code_base, $context, '{unknown}');
    }

    /**
     * Returns true if the snippet $node is likely free of side effects and is not going to jump to outside of the snippet
     *
     * TODO: Use the types of local variables as a heuristic in this subclass, e.g. $knownClass->sideEffectFreeMethod()
     *
     * @param Node|int|string|float|null $node
     */
    public static function isSideEffectFreeSnippet(CodeBase $code_base, Context $context, $node): bool
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

    // visitThrow throws already

    // TODO(optional): Bother tracking actual loop/switch depth
    public function visitBreak(Node $node): void
    {
        if ($node->children['depth'] > 1) {
            throw new NodeException($node);
        }
    }

    public function visitContinue(Node $node): void
    {
        if ($node->children['depth'] > 1) {
            throw new NodeException($node);
        }
    }

    // NOTE: Checks of assignment, increment or decrement are deferred to --unused-variable-detection

    // TODO: Return all classes in union and intersection types instead
    protected function getClassForVariable(Node $expr): Clazz
    {
        if ($expr->kind !== ast\AST_VAR) {
            // TODO: Support static properties, (new X()), other expressions with inferable types
            throw new NodeException($expr, 'expected simple variable');
        }
        $var_name = $expr->children['name'];
        if (!is_string($var_name)) {
            throw new NodeException($expr, 'variable name is not a string');
        }
        if ($var_name !== 'this') {
            $variable = $this->context->getScope()->getVariableByNameOrNull($var_name);
            if (!$variable) {
                throw new NodeException($expr, 'unknown variable');
            }

            $union_type = $variable->getUnionType()->asNormalizedTypes();
            $known_fqsen = null;

            foreach ($union_type->getUniqueFlattenedTypeSet() as $type) {
                if (!$type->isObjectWithKnownFQSEN()) {
                    continue;
                }
                $fqsen = $type->asFQSEN();
                if ($known_fqsen && $known_fqsen !== $fqsen) {
                    throw new NodeException($expr, 'unknown class');
                }
                $known_fqsen = $fqsen;
            }
            if (!$known_fqsen instanceof FullyQualifiedClassName) {
                throw new NodeException($expr, 'unknown class');
            }
            if (!$this->code_base->hasClassWithFQSEN($known_fqsen)) {
                throw new NodeException($expr, 'unknown class');
            }
            return $this->code_base->getClassByFQSEN($known_fqsen);
        }
        if (!$this->context->isInClassScope()) {
            throw new NodeException($expr, 'Not in class scope');
        }
        return $this->context->getClassInScope($this->code_base);
    }


    /**
     * @param Node $node the node of the call, with 'args'
     * @override
     */
    protected function checkCalledFunction(Node $node, FunctionInterface $method): void
    {
        if ($method->isPure()) {
            // avoid false positives - throw when calling void methods that were marked as free of side effects.
            if ($method->isPHPInternal() || (($method instanceof Method && $method->isAbstract()) || $method->hasReturn() || $method->hasYield())) {
                return;
            }
        }
        $label = self::getLabelForFunction($method);

        $value = (UseReturnValuePlugin::HARDCODED_FQSENS[$label] ?? false);
        if ($value === true) {
            return;
        } elseif ($value === UseReturnValuePlugin::SPECIAL_CASE) {
            if (UseReturnValueVisitor::doesSpecialCaseHaveSideEffects($label, $node)) {
                // infer that var_export($x, true) is pure but not var_export($x)
                throw new NodeException($node, $label);
            }
            return;
        }
        throw new NodeException($node, $label);
    }
}
