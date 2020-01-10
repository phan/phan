<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Exception;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Plugin\Internal\UseReturnValuePlugin;
use Phan\Plugin\Internal\UseReturnValuePlugin\PureMethodGraph;
use Phan\Plugin\Internal\UseReturnValuePlugin\UseReturnValueVisitor;

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
class InferPureVisitor extends AnalysisVisitor
{
    /** @var string the function fqsen being visited */
    protected $function_fqsen_label;

    /**
     * Map from labels to functions which this had called, but were not certain to have been pure.
     *
     * @var array<string, FunctionInterface>
     */
    protected $unresolved_status_dependencies = [];

    /**
     * A graph that tracks information about whether functions are pure, and how they depend on other functions.
     *
     * @var ?PureMethodGraph
     */
    protected $pure_method_graph;

    public function __construct(CodeBase $code_base, Context $context, string $function_fqsen_label, ?PureMethodGraph $graph = null)
    {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->function_fqsen_label = $function_fqsen_label;
        $this->pure_method_graph = $graph;
    }

    /**
     * Generate a visitor from a function or method.
     * This will be used for checking if the method is pure.
     */
    public static function fromFunction(CodeBase $code_base, FunctionInterface $func, ?PureMethodGraph $graph): InferPureVisitor
    {
        return new self(
            $code_base,
            $func->getContext(),
            self::getLabelForFunction($func),
            $graph
        );
    }

    /**
     * Returns the label UseReturnValuePlugin will use to look up whether this functions/methods is pure.
     */
    public function getLabel(): string
    {
        return $this->function_fqsen_label;
    }

    /**
     * Returns an array of functions/methods with unknown pure status.
     * If any of those functions are impure, then this function is impure.
     *
     * @return array<string, FunctionInterface>
     */
    public function getUnresolvedStatusDependencies(): array
    {
        return $this->unresolved_status_dependencies;
    }

    /**
     * Returns the label UseReturnValuePlugin will use to look up whether functions/methods are pure.
     */
    public static function getLabelForFunction(FunctionInterface $func): string
    {
        return \strtolower(\ltrim($func->getFQSEN()->__toString(), '\\'));
    }

    // visitAssignRef
    // visitThrow
    // visitEcho
    // visitPrint
    // visitIncludeOrExec
    public function visit(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitVar(Node $node): void
    {
        if (!\is_scalar($node->children['name'])) {
            throw new NodeException($node);
        }
    }

    /** @override */
    public function visitClassName(Node $_): void
    {
    }

    /** @override */
    public function visitMagicConst(Node $_): void
    {
    }

    /** @override */
    public function visitConst(Node $_): void
    {
    }

    /** @override */
    public function visitEmpty(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitIsset(Node $node): void
    {
        $this->maybeInvoke($node->children['var']);
    }

    /** @override */
    public function visitContinue(Node $_): void
    {
    }

    /** @override */
    public function visitBreak(Node $_): void
    {
    }

    /** @override */
    public function visitClassConst(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitStatic(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitArray(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitArrayElem(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitEncapsList(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitInstanceof(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitPreInc(Node $node): void
    {
        $this->checkPureIncDec($node);
    }

    public function visitPreDec(Node $node): void
    {
        $this->checkPureIncDec($node);
    }

    public function visitPostInc(Node $node): void
    {
        $this->checkPureIncDec($node);
    }

    public function visitPostDec(Node $node): void
    {
        $this->checkPureIncDec($node);
    }

    private function checkPureIncDec(Node $node): void
    {
        $var = $node->children['var'];
        if (!$var instanceof Node) {
            throw new NodeException($node);
        }
        if ($var->kind !== ast\AST_VAR) {
            throw new NodeException($var);
        }
        $this->visitVar($var);
    }

    /**
     * @param Node|string|int|float|null $node
     */
    final protected function maybeInvoke($node): void
    {
        if ($node instanceof Node) {
            $this->__invoke($node);
        }
    }

    public function visitBinaryOp(Node $node): void
    {
        $this->maybeInvoke($node->children['left']);
        $this->maybeInvoke($node->children['right']);
    }

    public function visitUnaryOp(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    public function visitDim(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
        $this->maybeInvoke($node->children['dim']);
    }

    public function visitProp(Node $node): void
    {
        ['expr' => $expr, 'prop' => $prop] = $node->children;
        if (!$expr instanceof Node) {
            throw new NodeException($node);
        }
        $this->__invoke($expr);
        if ($prop instanceof Node) {
            throw new NodeException($prop);
        }
    }

    /** @override */
    public function visitStmtList(Node $node): void
    {
        foreach ($node->children as $stmt) {
            if ($stmt instanceof Node) {
                $this->__invoke($stmt);
            }
        }
    }

    /** @override */
    public function visitStaticProp(Node $node): void
    {
        ['class' => $class, 'prop' => $prop] = $node->children;
        if (!$class instanceof Node) {
            throw new NodeException($node);
        }
        $this->__invoke($class);
        if ($prop instanceof Node) {
            throw new NodeException($prop);
        }
    }

    final protected function maybeInvokeAllChildNodes(Node $node): void
    {
        foreach ($node->children as $c) {
            if ($c instanceof Node) {
                $this->__invoke($c);
            }
        }
    }

    /** @override */
    public function visitCast(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitConditional(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitWhile(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitDoWhile(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitFor(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitForeach(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitIf(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitIfElem(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitch(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitchList(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitchCase(Node $node): void
    {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitGoto(Node $_): void
    {
    }

    /** @override */
    public function visitLabel(Node $_): void
    {
    }

    /** @override */
    public function visitAssignOp(Node $node): void
    {
        $this->visitAssign($node);
    }

    /** @override */
    public function visitAssign(Node $node): void
    {
        ['var' => $var, 'expr' => $expr] = $node->children;
        if (!$var instanceof Node) {
            throw new NodeException($node);
        }
        $this->checkVarKindOfAssign($var);
        $this->__invoke($var);
        if ($expr instanceof Node) {
            $this->__invoke($expr);
        }
    }

    private function checkVarKindOfAssign(Node $var): void
    {
        if ($var->kind === ast\AST_VAR) {
            return;
        } elseif ($var->kind === ast\AST_PROP) {
            // Functions that assign to properties aren't pure,
            // unless assigning to $this->prop in a constructor.
            if (\preg_match('/::__construct$/i', $this->function_fqsen_label)) {
                $name = $var->children['expr'];
                if ($name instanceof Node && $name->kind === ast\AST_VAR && $name->children['name'] === 'this') {
                    return;
                }
            }
        }
        throw new NodeException($var);
    }

    public function visitNew(Node $node): void
    {
        $name_node = $node->children['class'];
        if (!($name_node instanceof Node && $name_node->kind === ast\AST_NAME)) {
            throw new NodeException($node);
        }
        try {
            $class_list = (new ContextNode($this->code_base, $this->context, $name_node))->getClassList(false, ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);
        } catch (Exception $_) {
            throw new NodeException($name_node);
        }
        if (!$class_list) {
            throw new NodeException($name_node, 'no class found');
        }
        foreach ($class_list as $class) {
            if ($class->isPHPInternal()) {
                // TODO build a list of internal classes where result of new() is often unused.
                continue;
            }
            if (!$class->hasMethodWithName($this->code_base, '__construct')) {
                throw new NodeException($name_node, 'no __construct found');
            }
            $this->checkCalledFunction($node, $class->getMethodByName($this->code_base, '__construct'));
        }
    }

    /** @override */
    public function visitReturn(Node $node): void
    {
        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node) {
            $this->__invoke($expr_node);
        }
    }

    /** @override */
    public function visitYield(Node $node): void
    {
        $this->maybeInvoke($node->children['key']);
        $this->maybeInvoke($node->children['value']);
    }

    /** @override */
    public function visitYieldFrom(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitName(Node $_): void
    {
        // do nothing
    }

    /** @override */
    public function visitCall(Node $node): void
    {
        $expr = $node->children['expr'];
        if (!$expr instanceof Node) {
            throw new NodeException($node);
        }
        if ($expr->kind !== ast\AST_NAME) {
            // XXX this is deliberately a limited subset of what full analysis would do,
            // so this can't infer locally set closures, etc.
            throw new NodeException($expr);
        }
        $found_function = false;
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expr
            ))->getFunctionFromNode(true);

            foreach ($function_list_generator as $function) {
                $this->checkCalledFunction($node, $function);
                $found_function = true;
            }
        } catch (CodeBaseException $_) {
            // ignore it.
        }
        if (!$found_function) {
            throw new NodeException($expr, 'not a function');
        }
        $this->visitArgList($node->children['args']);
    }

    public function visitStaticCall(Node $node): void
    {
        $method = $node->children['method'];
        if (!\is_string($method)) {
            throw new NodeException($node);
        }
        $class = $node->children['class'];
        if (!($class instanceof Node)) {
            throw new NodeException($node);
        }
        if ($class->kind !== ast\AST_NAME) {
            throw new NodeException($class, 'not a name');
        }
        try {
            $union_type = UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $class
            );
        } catch (Exception $_) {
            throw new NodeException($class, 'could not get type');
        }
        if ($union_type->typeCount() !== 1) {
            throw new NodeException($class);
        }
        $type = $union_type->getTypeSet()[0];
        if (!$type->isObjectWithKnownFQSEN()) {
            throw new NodeException($class);
        }
        $class_fqsen = $type->asFQSEN();
        if (!($class_fqsen instanceof FullyQualifiedClassName)) {
            throw new NodeException($class);
        }
        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new NodeException($class);
        }
        try {
            $class = $this->code_base->getClassByFQSEN($class_fqsen);
        } catch (Exception $_) {
            throw new NodeException($node);
        }
        if (!$class->hasMethodWithName($this->code_base, $method)) {
            throw new NodeException($node, 'no method');
        }

        $this->checkCalledFunction($node, $class->getMethodByName($this->code_base, $method));
        $this->visitArgList($node->children['args']);
    }

    public function visitMethodCall(Node $node): void
    {
        if (!$this->context->isInClassScope()) {
            // We don't track variables in UseReturnValuePlugin
            throw new NodeException($node, 'method call seen outside class scope');
        }

        $method_name = $node->children['method'];
        if (!\is_string($method_name)) {
            throw new NodeException($node);
        }
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            throw new NodeException($node);
        }
        if ($expr->kind !== ast\AST_VAR) {
            throw new NodeException($expr, 'not a var');
        }
        if ($expr->children['name'] !== 'this') {
            throw new NodeException($expr, 'not $this');
        }
        $class = $this->context->getClassInScope($this->code_base);
        if (!$class->hasMethodWithName($this->code_base, $method_name)) {
            throw new NodeException($expr, 'does not have method');
        }
        $this->checkCalledFunction($node, $class->getMethodByName($this->code_base, $method_name));

        $this->visitArgList($node->children['args']);
    }

    /**
     * @param Node $node the node of the call, with 'args'
     */
    private function checkCalledFunction(Node $node, FunctionInterface $method): void
    {
        if ($method->isPure()) {
            return;
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
        if ($method->isPHPInternal()) {
            // Something such as printf that isn't pure. Or something that isn't in the HARDCODED_FQSENS.
            throw new NodeException($node, 'internal method is not pure');
        }
        if ($label === $this->function_fqsen_label) {
            return;
        }
        if ($this->pure_method_graph) {
            $this->unresolved_status_dependencies[$label] = $method;
            return;
        }
        throw new NodeException($node, $label);
    }

    public function visitClosure(Node $node): void
    {
        $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
            (clone($this->context))->withLineNumberStart($node->lineno),
            $node
        );
        if (!$this->code_base->hasFunctionWithFQSEN($closure_fqsen)) {
            throw new NodeException($node, "Failed lookup of closure_fqsen");
        }
        $this->checkCalledFunction($node, $this->code_base->getFunctionByFQSEN($closure_fqsen));
    }

    public function visitArrowFunc(Node $node): void
    {
        $this->visitClosure($node);
    }

    public function visitArgList(Node $node): void
    {
        foreach ($node->children as $x) {
            if ($x instanceof Node) {
                $this->__invoke($x);
            }
        }
    }
}
