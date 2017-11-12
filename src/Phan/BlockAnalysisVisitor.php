<?php declare(strict_types=1);
namespace Phan;

use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\Analysis\ContextMergeVisitor;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\Analysis\PreOrderAnalysisVisitor;
use Phan\Language\Context;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Plugin\ConfigPluginSet;
use ast\Node;

/**
 * Analyze blocks of code
 *
 * - Uses `\Phan\Analysis\PreOrderAnalysisVisitor` for pre-order analysis of a node (E.g. entering a function to analyze)
 * - Recursively analyzes child nodes
 * - Uses `\Phan\Analysis\PostOrderAnalysisVisitor` for post-order analysis of a node (E.g. analyzing a statement with the updated Context and emitting issues)
 * - If there is more than one possible child context, merges state from them (variable types)
 *
 * @see $this->visit
 */
class BlockAnalysisVisitor extends AnalysisVisitor
{

    /**
     * @var ?Node
     * The parent of the current node
     */
    private $parent_node;

    /**
     * @var int
     * The depth of the node being analyzed in the
     * AST
     */
    private $depth;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param ?Node $parent_node
     * The parent of the node being analyzed
     *
     * @param int $depth
     * The depth of the node being analyzed in the AST
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $parent_node = null,
        int $depth = 0
    ) {
        parent::__construct($code_base, $context);
        $this->parent_node = $parent_node;
        $this->depth = $depth;
    }

    /**
     * For non-special nodes, we propagate the context and scope
     * from the parent, through the children and return the
     * modified scope
     *
     *          │
     *          ▼
     *       ┌──●
     *       │
     *       ●──●──●
     *             │
     *          ●──┘
     *          │
     *          ▼
     *
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visit(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))($node);

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        \assert(!empty($context), 'Context cannot be null');

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($node->children ?? [] as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $child_node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    /**
     * This is an abstraction for getting a new, updated context for a child node.
     *
     * Effectively the same as (new BlockAnalysisVisitor(..., $context, $node, ..., $depth + 1, ...)($child_node))
     * but is much less repetitive and verbose, and slightly more efficient.
     *
     * @param Context $context - The original context for $node, before analyzing $child_node
     *
     * @param Node $node - The parent node of $child_node
     *
     * @param Node $child_node - The node which will be analyzed to create the updated context.
     *
     * @return Context (The unmodified $context, or a different Context instance with modifications)
     */
    private function analyzeAndGetUpdatedContext(Context $context, Node $node, Node $child_node) : Context
    {
        // Modify the original object instead of creating a new BlockAnalysisVisitor.
        // this is slightly more efficient, especially if a large number of unchanged parameters would exist.
        $old_context = $this->context;
        $old_parent_node = $this->parent_node;
        $old_depth = $this->depth++;
        $this->context = $context;
        $this->parent_node = $node;
        try {
            return Element::acceptNodeAndKindVisitor($child_node, $this);
        } finally {
            $this->context = $old_context;
            $this->parent_node = $old_parent_node;
            $this->depth = $old_depth;
        }
    }

    /**
     * For nodes that are the root of mutually exclusive child
     * nodes (if, try), we analyze each child in the parent context
     * and then merge them together to try to guess what happens
     * after the branching finishes.
     *
     *           │
     *           ▼
     *        ┌──●──┐
     *        │  │  │
     *        ●  ●  ●
     *        │  │  │
     *        └──●──┘
     *           │
     *           ▼
     *
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitBranchedContext(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $child_context_list = [];

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($node->children ?? [] as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // The conditions need to communicate to the outter
            // scope for things like assigning veriables.
            $child_context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $child_context->withLineNumberStart(
                $child_node->lineno ?? 0
            );

            // Step into each child node and get an
            // updated context for the node
            $child_context = $this->analyzeAndGetUpdatedContext($child_context, $node, $child_node);
            if (!BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($child_node)) {
                $child_context_list[] = $child_context;
            }
        }

        if (count($child_context_list) > 0) {
            // For switch/try statements, we need to merge the contexts
            // of all child context into a single scope based
            // on any possible branching structure
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                $child_context_list
            ))($node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitFor(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $init_node = $node->children['init'];
        if ($init_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($init_node->lineno ?? 0),
                $node,
                $init_node
            );
        }
        $context = $this->preOrderAnalyze($context, $node);
        \assert(!empty($context), 'Context cannot be null');

        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            // The typical case is `for (init; $x; loop) {}`
            // But `for (init; $x; loop) {}` is rare but possible, which requires evaluating those in order.
            // Evaluate the list of cond expressions in order.
            \assert($condition_node->kind === \ast\AST_EXPR_LIST);
            foreach ($condition_node->children as $condition_subnode) {
                if ($condition_subnode instanceof Node) {
                    $context = $this->analyzeAndGetUpdatedContext(
                        $context->withLineNumberStart($condition_subnode->lineno ?? 0),
                        $node,  // TODO: condition_node?
                        $condition_subnode
                    );
                }
            }
        }

        if ($stmts_node = $node->children['stmts']) {
            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno ?? 0),
                    $node,
                    $stmts_node
                );
            }
        }
        // Analyze the loop after analyzing the statements, in case it uses variables defined within the statements.
        $loop_node = $node->children['loop'];
        if ($loop_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($loop_node->lineno ?? 0),
                $node,
                $loop_node
            );
        }

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitWhile(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            // The typical case is `for (init; $x; loop) {}`
            // But `for (init; $x; loop) {}` is rare but possible, which requires evaluating those in order.
            // Evaluate the list of cond expressions in order.
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($condition_node->lineno ?? 0),
                $node,
                $condition_node
            );
        }

        if ($stmts_node = $node->children['stmts']) {
            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno ?? 0),
                    $node,
                    $stmts_node
                );
            }
        }

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitIfElem(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($condition_node->lineno ?? 0),
                $node,
                $condition_node
            );
        }

        if ($stmts_node = $node->children['stmts']) {
            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno ?? 0),
                    $node,
                    $stmts_node
                );
            }
        }

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * For 'closed context' items (classes, methods, functions,
     * closures), we analyze children in the parent context, but
     * then return the parent context itself unmodified by the
     * children.
     *
     *           │
     *           ▼
     *        ┌──●────┐
     *        │       │
     *        ●──●──● │
     *           ┌────┘
     *           ●
     *           │
     *           ▼
     *
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitClosedContext(Node $node) : Context
    {
        // Make a copy of the internal context so that we don't
        // leak any changes within the closed context to the
        // outer scope
        $context = clone($this->context->withLineNumberStart(
            $node->lineno ?? 0
        ));

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $child_context_list = [];

        $child_context = $context;

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($node->children ?? [] as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $child_context = $this->analyzeAndGetUpdatedContext($child_context, $node, $child_node);

            $child_context_list[] = $child_context;
        }

        // For if statements, we need to merge the contexts
        // of all child context into a single scope based
        // on any possible branching structure
        $context = (new ContextMergeVisitor(
            $this->code_base,
            $context,
            $child_context_list
        ))($node);

        $unused_final_context = $this->postOrderAnalyze($context, $node);

        // Return the initial context as we exit
        return $this->context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitIf(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $child_context_list = [];

        $scope = $context->getScope();
        if ($scope instanceof GlobalScope) {
            $fallthrough_context = $context->withScope(new BranchScope($scope));
        } else {
            $fallthrough_context = $context;
        }

        $child_nodes = $node->children ?? [];
        $excluded_elem_count = 0;

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($child_nodes as $child_node) {
            // The conditions need to communicate to the outter
            // scope for things like assigning veriables.
            $child_context = clone($fallthrough_context);

            assert($child_node->kind === \ast\AST_IF_ELEM);

            $child_context->withLineNumberStart(
                $child_node->lineno ?? 0
            );

            // Step into each child node and get an
            // updated context for the node
            $child_context = $this->analyzeAndGetUpdatedContext($child_context, $node, $child_node);

            // Issue #406: We can improve analysis of `if` blocks by using
            // a BlockExitStatusChecker to avoid propogating invalid inferences.
            // TODO: we may wish to check for a try block between this line's scope
            // and the parent function's (or global) scope,
            // to reduce false positives.
            // (Variables will be available in `catch` and `finally`)
            // This is mitigated by finally and catch blocks being unaware of new variables from try{} blocks.
            if (BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($child_node->children['stmts'])) {
                // e.g. "if (!is_string($x)) { return; }"
                $excluded_elem_count++;
            } else {
                $child_context_list[] = $child_context;
            }

            $cond_node = $child_node->children['cond'];
            if ($cond_node instanceof Node) {
                $fallthrough_context = (new NegatedConditionVisitor($this->code_base, $fallthrough_context))($cond_node);
            }
            // If cond_node was null, it would be an else statement.
        }

        if ($excluded_elem_count === count($child_nodes)) {
            // If all of the AST_IF_ELEM bodies would unconditionally throw or return,
            // then analyze the remaining statements with the negation of all of the conditions.
            $context = $fallthrough_context;
        } else {
            // For if statements, we need to merge the contexts
            // of all child context into a single scope based
            // on any possible branching structure

            // ContextMergeVisitor will include the incoming scope($context) if the if elements aren't comprehensive
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $fallthrough_context,  // e.g. "if (!is_string($x)) { $x = ''; }" should result in inferring $x is a string.
                $child_context_list
            ))($node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitCatchList(Node $node) : Context
    {
        return $this->visitBranchedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitTry(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $child_context_list = [];

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.

        $try_node = $node->children['try'];
        // Skip any non Node children.
        assert($try_node instanceof Node);

        // The conditions need to communicate to the outer
        // scope for things like assigning veriables.
        $try_context = $context->withScope(
            new BranchScope($context->getScope())
        );

        $try_context->withLineNumberStart(
            $try_node->lineno ?? 0
        );

        // Step into each try node and get an
        // updated context for the node
        $try_context = $this->analyzeAndGetUpdatedContext($try_context, $node, $try_node);
        // NOTE: We let ContextMergeVisitor->visitTry decide if the block exit status is valid.
        $child_context_list[] = $try_context;

        foreach ($node->children['catches']->children ?? [] as $catch_node) {
            // Note: ContextMergeVisitor expects to get each individual catch
            assert($catch_node instanceof Node);
            // The conditions need to communicate to the outer
            // scope for things like assigning veriables.
            $catch_context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $catch_context->withLineNumberStart(
                $catch_node->lineno ?? 0
            );

            // Step into each catch node and get an
            // updated context for the node
            $catch_context = $this->analyzeAndGetUpdatedContext($catch_context, $node, $catch_node);
            // NOTE: We let ContextMergeVisitor->visitTry decide if the block exit status is valid.
            $child_context_list[] = $catch_context;
        }

        $finally_node = $node->children['finally'] ?? null;
        if ($finally_node) {
            assert($finally_node instanceof Node);
            // The conditions need to communicate to the outer
            // scope for things like assigning veriables.
            $finally_context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $finally_context->withLineNumberStart(
                $finally_node->lineno ?? 0
            );

            // Step into each finally node and get an
            // updated context for the node
            $finally_context = $this->analyzeAndGetUpdatedContext($finally_context, $node, $finally_node);
            // NOTE: We let ContextMergeVisitor->visitTry decide if the block exit status is valid.
            $child_context_list[] = $finally_context;
        }

        if (count($child_context_list) > 0) {
            // For switch/try statements, we need to merge the contexts
            // of all child context into a single scope based
            // on any possible branching structure
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                $child_context_list
            ))($node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    public function visitConditional(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        // NOTE: unused for AST_CONDITIONAL
        // $context = (new PreOrderAnalysisVisitor(
        //     $this->code_base, $context
        // ))($node);

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        \assert(!empty($context), 'Context cannot be null');

        $true_node = $node->children['true'] ?? null;
        $false_node = $node->children['false'] ?? null;

        $cond_node = $node->children['cond'];
        if ($cond_node instanceof Node) {
            // Step into each child node and get an
            // updated context for the node
            // (e.g. there may be assignments such as '($x = foo()) ? $a : $b)
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $cond_node);

            // TODO: Use different contexts and merge those, in case there were assignments or assignments by reference in both sides of the conditional?
            // Reuse the BranchScope (sort of unintuitive). The ConditionVisitor returns a clone and doesn't modify the original.
            $base_context = $context;
            $base_context_scope = $base_context->getScope();
            if ($base_context_scope instanceof GlobalScope) {
                $base_context = $context->withScope(new BranchScope($base_context_scope));
            }
            $true_context = (new ConditionVisitor(
                $this->code_base,
                isset($true_node) ? $base_context : $context  // special case: (($d = foo()) ?: 'fallback')
            ))($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))($cond_node);
        } else {
            $true_context = $context;
            $false_context = $context;
        }

        $child_context_list = [];
        // In the long form, there's a $true_node, but in the short form (?:),
        // $cond_node is the (already processed) value for truthy.
        if ($true_node instanceof Node) {
            $child_context = $this->analyzeAndGetUpdatedContext($true_context, $node, $true_node);
            $child_context_list[] = $child_context;
        }

        if ($false_node instanceof Node) {
            $child_context = $this->analyzeAndGetUpdatedContext($false_context, $node, $false_node);
            $child_context_list[] = $child_context;
        }
        if (\count($child_context_list) >= 1) {
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                $child_context_list
            ))($node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitClass(Node $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitMethod(Node $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitFuncDecl(Node $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitClosure(Node $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * Common options for pre-order analysis phase of a Node.
     * Run pre-order analysis steps, then run plugins.
     *
     * @param Context $context - The context before pre-order analysis
     *
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after pre-order analysis of the node
     */
    private function preOrderAnalyze(Context $context, Node $node) : Context
    {
        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))($node);

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );
        return $context;
    }

    /**
     * Common options for post-order analysis phase of a Node.
     * Run analysis steps and run plugins.
     *
     * @param Context $context - The context before post-order analysis
     *
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after post-order analysis of the node
     */
    private function postOrderAnalyze(Context $context, Node $node) : Context
    {
        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))($node);

        // let any configured plugins analyze the node
        ConfigPluginSet::instance()->analyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node
        );
        return $context;
    }

    /**
     * Analyzes a node of type \ast\AST_GROUP_USE
     * This is the same as visit(), but does not recurse into the child nodes.
     *
     * If this function override didn't exist,
     * then visit() would recurse into \ast\AST_USE,
     * which would lack part of the namespace.
     * (E.g. for use \NS\{const X, const Y}, we don't want to analyze const X or const Y
     * without the preceding \NS\)
     */
    public function visitGroupUse(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))($node);

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        \assert(!empty($context), 'Context cannot be null');

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     * @see $this->visit() - This is similar to visit() except that the check if $is_static requires parent_node,
     * so PreOrderAnalysisVisitor can't be used to modify the Context.
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitPropElem(Node $node) : Context
    {
        $prop_name = (string)$node->children['name'];

        $class = $this->context->getClassInScope($this->code_base);
        $is_static = ($this->parent_node->flags & \ast\flags\MODIFIER_STATIC) !== 0;
        $property = $class->getPropertyByNameInContext($this->code_base, $prop_name, $this->context, $is_static);

        $context = $this->context->withScope(
            $property->getInternalScope()
        )->withLineNumberStart(
            $node->lineno ?? 0
        );

        \assert(!empty($context), 'Context cannot be null');

        // Don't bother calling PreOrderAnalysisVisitor, it does nothing

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        $default = $node->children['default'] ?? null;
        if ($default instanceof Node) {
            // Step into each child node and get an
            // updated context for the node
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $default);
        }

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }
}
