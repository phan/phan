<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\UseReturnValuePlugin;

use ast;
use ast\Node;
use Exception;
use Phan\AST\ContextNode;
use Phan\Exception\CodeBaseException;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Plugin\Internal\UseReturnValuePlugin;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;

/**
* Checks for invocations of functions/methods where the return value should be used.
* Also, gathers statistics on how often those functions/methods are used.
* @phan-file-suppress PhanAccessPropertyInternal
*/
class UseReturnValueVisitor extends PluginAwarePostAnalysisVisitor
{
    /** @var list<Node> set by plugin framework */
    protected $parent_node_list;

    /**
     * Skip unary ops when determining the parent node.
     * E.g. for `@foo();`, the parent node is AST_STMT_LIST (which we infer means the result is unused)
     * For `$x = +foo();` the parent node is AST_ASSIGN.
     * @return array{0:?Node,1:bool} - [$parent, $used]
     * $used is whether the expression is used - it should only be checked if the parent is known.
     */
    private function findNonUnaryParentNode(Node $node): array
    {
        $parent = \end($this->parent_node_list);
        if (!$parent) {
            return [null, true];
        }
        while (true) {
            switch ($parent->kind) {
                case ast\AST_UNARY_OP:
                    break;
                case ast\AST_CONDITIONAL:
                    if ($node === $parent->children['cond']) {
                        return [null, true];
                    }
                    break;
                case ast\AST_BINARY_OP:
                    if (!\in_array($parent->flags, [ast\flags\BINARY_COALESCE, ast\flags\BINARY_BOOL_OR, ast\flags\BINARY_BOOL_AND], true) || $node !== $parent->children['right']) {
                        return [null, true];
                    }
                    break;
                default:
                    break 2;
            }

            $node = $parent;
            $parent = \prev($this->parent_node_list);
            if (!$parent) {
                return [null, true];
            }
        }
        // @phan-suppress-next-line PhanPluginUnreachableCode Phan can't analyze `break 2;`
        switch ($parent->kind) {
            case ast\AST_STMT_LIST:
                return [$parent, false];
            case ast\AST_EXPR_LIST:
                return [$parent, $this->isUsedExpressionInExprList($node, $parent)];
        }
        return [$parent, true];
    }

    private function isUsedExpressionInExprList(Node $node, Node $parent): bool
    {
        return $node === \end($parent->children) && $parent === (\prev($this->parent_node_list)->children['cond'] ?? null);
    }

    /**
     * @param Node $node a node of type AST_CALL
     * @override
     */
    public function visitCall(Node $node): void
    {
        [$parent, $used] = $this->findNonUnaryParentNode($node);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        if ($used && !UseReturnValuePlugin::$use_dynamic) {
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $expression = $node->children['expr'];
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode();

            foreach ($function_list_generator as $function) {
                if ($function instanceof Method) {
                    $fqsen = $function->getDefiningFQSEN()->__toString();
                } else {
                    $fqsen = $function->getFQSEN()->__toString();
                }
                if (!UseReturnValuePlugin::$use_dynamic) {
                    $this->quickWarn($function, $fqsen, $node);
                    continue;
                }
                if ($function instanceof Func && $function->isClosure()) {
                    continue;
                }
                $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
                if (!$counter) {
                    UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($function);
                }
                if ($used) {
                    $counter->used_locations[$key] = $this->context;
                } else {
                    $counter->unused_locations[$key] = $this->context;
                }
            }
        } catch (CodeBaseException $_) {
        }
    }

    /**
     * Checks if a method has unnecessary branches leading to the same returned value
     *
     * @param Node $node a node of type AST_METHOD
     * @override
     */
    public function visitMethod(Node $node): void
    {
        $this->analyzeFunctionLike($node);
    }

    /**
     * Checks if a global function has unnecessary branches leading to the same returned value
     *
     * @param Node $node a node of type AST_FUNC_DECL
     * @override
     */
    public function visitFuncDecl(Node $node): void
    {
        $this->analyzeFunctionLike($node);
    }

    /**
     * Checks if a closure has unnecessary branches leading to the same returned value
     *
     * NOTE: There is no need to implement this for AST_ARROW_FUNC,
     * which is currently limited to only one possible returned expression.
     * @param Node $node a node of type AST_CLOSURE
     * @override
     */
    public function visitClosure(Node $node): void
    {
        $this->analyzeFunctionLike($node);
    }

    /**
     * Checks if a function-like has unnecessary branches leading to the same returned value
     */
    private function analyzeFunctionLike(Node $node): void
    {
        if (!$this->context->isInFunctionLikeScope()) {
            return;
        }
        $method = $this->context->getFunctionLikeInScope($this->code_base);
        if (!$method->isPure()) {
            return;
        }
        if ($method instanceof Method) {
            if ($method->isAbstract()) {
                return;
            }
        }
        if (!$method->hasReturn() || $method->hasYield()) {
            return;
        }
        $stmts = $node->children['stmts'];
        if ($stmts instanceof Node) {
            (new RedundantReturnVisitor($this->code_base, $this->context, $stmts))->analyze();
        }
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @override
     */
    public function visitMethodCall(Node $node): void
    {
        [$parent, $used] = $this->findNonUnaryParentNode($node);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        if ($used && !UseReturnValuePlugin::$use_dynamic) {
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false);
        } catch (Exception $_) {
            return;
        }
        $fqsen = $method->getDefiningFQSEN()->__toString();
        if (!UseReturnValuePlugin::$use_dynamic) {
            $this->quickWarn($method, $fqsen, $node);
            return;
        }
        $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
        if (!$counter) {
            UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($method);
        }
        if ($used) {
            $counter->used_locations[$key] = $this->context;
        } else {
            $counter->unused_locations[$key] = $this->context;
        }
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @override
     */
    public function visitStaticCall(Node $node): void
    {
        [$parent, $used] = $this->findNonUnaryParentNode($node);
        if (!$parent) {
            //fwrite(STDERR, "No parent in " . __METHOD__ . "\n");
            return;
        }
        if ($used && !UseReturnValuePlugin::$use_dynamic) {
            return;
        }
        $key = $this->context->getFile() . ':' . $this->context->getLineNumberStart();
        //fwrite(STDERR, "Saw parent of type " . ast\get_kind_name($parent->kind)  . "\n");

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, true, true);
        } catch (Exception $_) {
            return;
        }
        $fqsen = $method->getDefiningFQSEN()->__toString();
        if (!UseReturnValuePlugin::$use_dynamic) {
            $this->quickWarn($method, $fqsen, $node);
            return;
        }
        $counter = UseReturnValuePlugin::$stats[$fqsen] ?? null;
        if (!$counter) {
            UseReturnValuePlugin::$stats[$fqsen] = $counter = new StatsForFQSEN($method);
        }
        if ($used) {
            $counter->used_locations[$key] = $this->context;
        } else {
            $counter->unused_locations[$key] = $this->context;
        }
    }

    private static function isSecondArgumentEqualToConst(Node $node, string $const_name): bool
    {
        $args = $node->children['args']->children;
        $bool_node = $args[1] ?? null;
        if (!$bool_node instanceof Node) {
            if ($const_name === 'true') {
                return (bool)$bool_node;
            } elseif ($const_name === 'false') {
                return (bool)$bool_node;
            }
            return false;
        }
        if ($bool_node->kind !== ast\AST_CONST) {
            return false;
        }
        $name = $bool_node->children['name']->children['name'] ?? null;
        return \is_string($name) && \strcasecmp($name, $const_name) === 0;
    }

    /**
     * @return bool true if $fqsen_key should be treated as if it were read-only.
     * Precondition: $fqsen_key is found as a special case in this plugin's set of functions.
     */
    public static function doesSpecialCaseHaveSideEffects(string $fqsen_key, Node $node): bool
    {
        switch ($fqsen_key) {
            case 'var_export':
            case 'print_r':
                // var_export and print_r take a second bool argument.
                // Warn if that argument is true.
                return !self::isSecondArgumentEqualToConst($node, 'true');
            case 'class_exists':
            case 'interface_exists':
            case 'trait_exists':
                // Triggers autoloader unless second argument is false
                return !self::isSecondArgumentEqualToConst($node, 'false');
            case 'preg_match':
            case 'preg_match_all':
                return \count($node->children['args']->children) >= 3;
        }
        return true;
    }

    private function shouldNotWarnForSpecialCase(string $fqsen_key, Node $node): bool
    {
        switch ($fqsen_key) {
            case 'call_user_func':
            case 'call_user_func_array':
                return $this->shouldNotWarnForDynamicCall($node->children['args']->children[0] ?? null);
            default:
                return self::doesSpecialCaseHaveSideEffects($fqsen_key, $node);
        }
    }

    /**
     * @param ?(Node|string|int|float) $node_name
     */
    private function shouldNotWarnForDynamicCall($node_name): bool
    {
        if ($node_name instanceof Node) {
            foreach ((new ContextNode(
                $this->code_base,
                $this->context,
                $node_name
            ))->getFunctionFromNode() as $function) {
                $node_name = $function->getFQSEN()->__toString();
                break;
            }
        }
        if (!\is_string($node_name)) {
            return true;
        }
        $fqsen_key = \strtolower(\ltrim($node_name, "\\"));
        return (UseReturnValuePlugin::HARDCODED_FQSENS[$fqsen_key] ?? null) !== true;
    }

    private function quickWarn(FunctionInterface $method, string $fqsen, Node $node): void
    {
        if (!$method->isPure()) {
            $fqsen_key = \strtolower(\ltrim($fqsen, "\\"));
            $result = UseReturnValuePlugin::HARDCODED_FQSENS[$fqsen_key] ?? false;
            if (!$result) {
                return;
            }
            if ($result === UseReturnValuePlugin::SPECIAL_CASE) {
                if ($this->shouldNotWarnForSpecialCase($fqsen_key, $node)) {
                    return;
                }
            }
        }
        if ($method->isPHPInternal()) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone($this->context))->withLineNumberStart($node->lineno),
                UseReturnValuePlugin::UseReturnValueInternalKnown,
                'Expected to use the return value of the internal function/method {FUNCTION}',
                [$fqsen]
            );
            return;
        }
        if ($method->getUnionType()->isNull() || !($method->hasReturn() || $method->isFromPHPDoc())) {
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            (clone($this->context))->withLineNumberStart($node->lineno),
            UseReturnValuePlugin::UseReturnValueKnown,
            'Expected to use the return value of the user-defined function/method {FUNCTION} defined at {FILE}:{LINE}',
            [$method->getRepresentationForIssue(), $method->getContext()->getFile(), $method->getContext()->getLineNumberStart()]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
