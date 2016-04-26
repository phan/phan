<?php declare(strict_types=1);
namespace Phan;

use Phan\AST\AnalysisVisitor;
use Phan\Analysis\ContextMergeVisitor;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\Analysis\PreOrderAnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Scope\BranchScope;
use Phan\Plugin\ConfigPluginSet;
use ast\Node;
use ast\Node\Decl;

/**
 * Analyze blocks of code
 */
class BlockAnalysisVisitor extends AnalysisVisitor {

    /**
     * @var Node
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
     * @param Node $parent_node
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
     * An AST node we'd like to determine the UnionType
     * for
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
            $this->code_base, $context
        ))($node);

        assert(!empty($context), 'Context cannot be null');

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($node->children ?? [] as $child_node) {
            // Skip any non Node children or boring nodes
            // that are too deep.
            if (!($child_node instanceof Node)
                || !Analysis::shouldVisit($child_node)
            ) {
                $context->withLineNumberStart(
                    $child_node->lineno ?? 0
                );
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $context = (new BlockAnalysisVisitor(
                $this->code_base, $context, $node, $this->depth + 1
            ))($child_node);
        }

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))($node);

        // Let any configured plugins analyze the node
        ConfigPluginSet::instance()->analyzeNode(
            $this->code_base, $context, $node, $this->parent_node
        );

        return $context;
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
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitBranchedContext(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base, $context
        ))($node);

        assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $child_context_list = [];

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.
        foreach ($node->children ?? [] as $node_key => $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            if (!Analysis::shouldVisit($child_node)) {
                continue;
            }

            // The conditions need to communicate to the outter
            // scope for things like assigning veriables.
            if ($child_node->kind != \ast\AST_IF_ELEM) {
                $child_context = $context->withScope(
                    new BranchScope($context->getScope())
                );
            } else {
                $child_context = $context;
            }

            $child_context->withLineNumberStart(
                $child_node->lineno ?? 0
            );

            // Step into each child node and get an
            // updated context for the node
            $child_context = (new BlockAnalysisVisitor(
                $this->code_base, $child_context, $node, $this->depth + 1
            ))($child_node);

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

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))($node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitIfElem(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base, $context
        ))($node);

        assert(!empty($context), 'Context cannot be null');

        if (($condition_node = $node->children['cond'])
            && $condition_node instanceof Node
        ) {
            $context = (new BlockAnalysisVisitor(
                $this->code_base,
                $context->withLineNumberStart($condition_node->lineno ?? 0),
                $node,
                $this->depth + 1
            ))($condition_node);
        }

        if ($stmts_node = $node->children['stmts']) {
            $context = (new BlockAnalysisVisitor(
                $this->code_base,
                $context->withScope(
                    new BranchScope($context->getScope())
                )->withLineNumberStart($stmts_node->lineno ?? 0),
                $node,
                $this->depth + 1
            ))($stmts_node);
        }

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))($node);


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
     * An AST node we'd like to determine the UnionType
     * for
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

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base, $context
        ))($node);

        assert(!empty($context), 'Context cannot be null');

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

            if (!Analysis::shouldVisit($child_node)) {
                $child_context->withLineNumberStart(
                    $child_node->lineno ?? 0
                );
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $child_context = (new BlockAnalysisVisitor(
                $this->code_base, $child_context, $node, $this->depth + 1
            ))($child_node);

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

        // Now that we know all about our context (like what
        // 'self' means), we can analyze statements like
        // assignments and method calls.
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))($node);

        // Return the initial context as we exit
        return $this->context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitIf(Node $node) : Context
    {
        return $this->visitBranchedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
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
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitTry(Node $node) : Context
    {
        return $this->visitBranchedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitClass(Decl $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitMethod(Decl $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitFuncDecl(Decl $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitClosure(Decl $node) : Context
    {
        return $this->visitClosedContext($node);
    }
}
