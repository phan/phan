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
use Phan\Language\Element\Comment;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Scope\PropertyScope;
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

    // No-ops for frequent node types
    public function visitVar(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

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
        $name_node = $node->children['name'];
        // E.g. ${expr()} is valid PHP. Recurse if that's a node.
        if ($name_node instanceof Node) {
            // Step into each child node and get an
            // updated context for the node
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $name_node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    public function visitParam(Node $node) : Context
    {
        // Could invoke plugins, but not right now
        return $this->context;
    }

    public function visitUseElem(Node $node) : Context
    {
        // Could invoke plugins, but not right now
        return $this->context;
    }

    /**
     * @suppress PhanAccessMethodInternal
     */
    public function visitNamespace(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        // If there are multiple namespaces in the file, have to warn about unused entries in the current namespace first.
        // If this is the first namespace, then there wouldn't be any use statements yet.
        $context->warnAboutUnusedUseElements($this->code_base);

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))->visitNamespace($node);

        \assert(!empty($context), 'Context cannot be null');

        // We already imported namespace constants earlier; use those.
        $context->importNamespaceMapFromParsePhase($this->code_base);

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        // The namespace may either have a list of statements (`namespace Foo {}`)
        // or be null (`namespace Foo;`)
        $stmts_node = $node->children['stmts'];
        if ($stmts_node instanceof Node) {
            assert($stmts_node->kind === \ast\AST_STMT_LIST);
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $stmts_node);
        }

        return $this->postOrderAnalyze($context, $node);
    }

    public function visitName(Node $node) : Context
    {
        // Could invoke plugins, but not right now
        return $this->context;
    }

    public function visitStmtList(Node $node) : Context
    {
        $context = $this->context;
        $plugin_set = ConfigPluginSet::instance();
        $plugin_set->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );
        foreach ($node->children as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                if (\is_string($child_node) && \strpos($child_node, '@phan-') !== false) {
                    // Add @phan-var and @phan-suppress annotations in string literals to the local scope
                    $this->analyzeSubstituteVarAssert($this->code_base, $context, $child_node);
                }
                continue;
            }
            $context->clearCachedUnionTypes();

            // Step into each child node and get an
            // updated context for the node
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $child_node);
        }
        $plugin_set->analyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node
        );
        return $context;
    }

    const PHAN_VAR_REGEX =
        '/@(phan-var(?:-force)?)\b\s*(' . UnionType::union_type_regex . ')\s*&?\\$' . Comment::WORD_REGEX . '/';

    const PHAN_SUPPRESS_REGEX =
        '/@phan-file-suppress\s+' . Comment::WORD_REGEX . '/';

    /**
     * Parses annotations such as "(at)phan-var int $myVar" and "(at)phan-var-force ?MyClass $varName" annotations from inline string literals.
     * (php-ast isn't able to parse inline doc comments, so string literals are used for rare edge cases where assert/if statements don't work)
     *
     * Modifies the type of the variable (in the scope of $context) to be identical to the annotated union type.
     * @return void
     */
    private function analyzeSubstituteVarAssert(CodeBase $code_base, Context $context, string $text)
    {
        $has_known_annotations = false;
        if (\preg_match_all(self::PHAN_VAR_REGEX, $text, $matches, PREG_SET_ORDER) > 0) {
            $has_known_annotations = true;
            foreach ($matches as $group) {
                $annotation_name = $group[1];
                $type_string = $group[2];
                $var_name = $group[18];
                $type = UnionType::fromStringInContext($type_string, $context, Type::FROM_PHPDOC);
                $this->createVarForInlineComment($code_base, $context, $var_name, $type, $annotation_name === 'phan-var-force');
            }
        }

        if (\preg_match_all(self::PHAN_SUPPRESS_REGEX, $text, $matches, PREG_SET_ORDER) > 0) {
            $has_known_annotations = true;
            foreach ($matches as $group) {
                $issue_name = $group[1];
                $code_base->addFileLevelSuppression($context->getFile(), $issue_name);
            }
        }

        if (!$has_known_annotations && preg_match('/@phan-.*/', $text, $match) > 0) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::UnextractableAnnotation,
                $context->getLineNumberStart(),
                rtrim($match[0])
            );
        }
        return;
    }

    /**
     * @return void
     * @see ConditionVarUtil::getVariableFromScope
     */
    private function createVarForInlineComment(CodeBase $code_base, Context $context, string $var_name, UnionType $type, bool $create_variable)
    {
        if (!$context->getScope()->hasVariableWithName($var_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope())) {
                return;
            }
            if (!$create_variable && !($context->isInGlobalScope() && Config::getValue('ignore_undeclared_variables_in_global_scope'))) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::UndeclaredVariable,
                    $context->getLineNumberStart(),
                    $var_name
                );
                return;
            }
            $variable = new Variable(
                $context,
                $var_name,
                $type,
                0
            );
            $context->addScopeVariable($variable);
            return;
        }
        $variable = $context->getScope()->getVariableByName(
            $var_name
        );
        $variable->setUnionType($type);
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
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

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
        foreach ($node->children as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $child_node);
        }

        return $this->postOrderAnalyze($context, $node);
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

        // NOTE: This is different from other analysis visitors because analyzing 'cond' with `||` has side effects
        // after supporting visitAnd() and visitOr() in BlockAnalysisVisitor
        // TODO: Calling analyzeAndGetUpdatedContext before preOrderAnalyze is a hack.

        // TODO: This is redundant and has worse knowledge of the specific types of blocks than ConditionVisitor does.
        // TODO: Implement a hybrid BlockAnalysisVisitor+ConditionVisitor that will do a better job of inferences and reducing false positives? (and reduce the redundant work)

        // E.g. the below code would update the context of BlockAnalysisVisitor in BlockAnalysisVisitor->visitOr()
        //
        //     if (!(is_string($x) || $x === null)) {}
        //
        // But we want to let BlockAnalysisVisitor modify the context for cases such as the below:
        //
        // $result = !($x instanceof User) || $x->meetsCondition()
        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($condition_node->lineno ?? 0),
                $node,
                $condition_node
            );
        }

        $context = $this->preOrderAnalyze($context, $node);

        \assert(!empty($context), 'Context cannot be null');

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
        foreach ($node->children as $child_node) {
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

        $child_nodes = $node->children;
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
     * TODO: Diagram similar to visit() for this and other visitors handling branches?
     *
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

        // Analyze the catch blocks and finally blocks with a mix of the types
        // from the try block and the catch blocks.
        // NOTE: when strict_mode = 1, variables that are only defined in some Contexts
        // but not others will be treated as absent.
        // TODO: Improve in future releases
        // NOTE: We let ContextMergeVisitor->visitTry decide if the block exit status is valid.
        $original_context = $context;
        $context = (new ContextMergeVisitor(
            $this->code_base,
            $context,
            [$try_context]
        ))->mergeTryContext($node);

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $catch_context_list = [$try_context];

        foreach ($node->children['catches']->children as $catch_node) {
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
            // NOTE: We let ContextMergeVisitor->mergeCatchContext decide if the block exit status is valid.
            $catch_context_list[] = $catch_context;
        }

        // first context is the try. If there's a second context, it's a catch.
        if (count($catch_context_list) >= 2) {
            // For switch/try statements, we need to merge the contexts
            // of all child context into a single scope based
            // on any possible branching structure
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                $catch_context_list
            ))->mergeCatchContext($node);
        }

        $finally_node = $node->children['finally'] ?? null;
        if ($finally_node) {
            assert($finally_node instanceof Node);
            // Because finally is always executed, we reuse $context

            // The conditions need to communicate to the outer
            // scope for things like assigning veriables.
            $context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $context->withLineNumberStart(
                $finally_node->lineno ?? 0
            );

            // Step into each finally node and get an
            // updated context for the node.
            // Don't bother checking if finally unconditionally returns here
            // If it does, dead code detection would also warn.
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $finally_node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        // When coming out of a scoped element, we pop the
        // context to be the incoming context. Otherwise,
        // we pass our new context up to our parent
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
    public function visitBinaryOp(Node $node) : Context
    {
        $flags = $node->flags;
        if ($flags === \ast\flags\BINARY_BOOL_AND) {
            return $this->visitAnd($node);
        } elseif ($flags === \ast\flags\BINARY_BOOL_OR) {
            return $this->visitOr($node);
        }
        return $this->visit($node);
    }

    /**
     * @param Node $node
     * A node to parse (for `&&` or `and` operator)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAnd(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        $left_node = $node->children['left'];
        $right_node = $node->children['right'];

        // With (left) && (right)
        // 1. Update context with any side effects of left
        // 2. Create a context to analyze the right hand side with any inferences possible from left (e.g. ($x instanceof MyClass) && $x->foo()
        // 3. Analyze the right hand side
        // 4. Merge the possibly evaluated $right_context for the right hand side into the original context. (The left_node is guaranteed to have been evaluated, so it becomes $context)

        // TODO: Warn about non-node, they're guaranteed to be always false or true
        if ($left_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $left_node);

            $base_context = $context;
            $base_context_scope = $base_context->getScope();
            if ($base_context_scope instanceof GlobalScope) {
                $base_context = $context->withScope(new BranchScope($base_context_scope));
            }
            $context_with_left_condition = (new ConditionVisitor(
                $this->code_base,
                $base_context
            ))($left_node);
        } else {
            $context_with_left_condition = $context;
        }

        if ($right_node instanceof Node) {
            $right_context = $this->analyzeAndGetUpdatedContext($context_with_left_condition, $node, $right_node);
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                [$context, $context_with_left_condition, $right_context]
            ))($node);
        }

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    /**
     * @param Node $node
     * A node to parse (for `||` or `or` operator)
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitOr(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno ?? 0
        );

        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        $left_node = $node->children['left'];
        $right_node = $node->children['right'];

        // With (left) || (right)
        // 1. Update context with any side effects of left
        // 2. Create a context to analyze the right hand side with any inferences possible from left (e.g. (!($x instanceof MyClass)) || $x->foo()
        // 3. Analyze the right hand side
        // 4. Merge the possibly evaluated $right_context for the right hand side into the original context. (The left_node is guaranteed to have been evaluated, so it becomes $context)

        // TODO: Warn about non-node, they're guaranteed to be always false or true
        if ($left_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $left_node);

            $base_context = $context;
            $base_context_scope = $base_context->getScope();
            if ($base_context_scope instanceof GlobalScope) {
                $base_context = $context->withScope(new BranchScope($base_context_scope));
            }
            $context_with_false_left_condition = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))($left_node);
            $context_with_true_left_condition = (new ConditionVisitor(
                $this->code_base,
                $base_context
            ))($left_node);
        } else {
            $context_with_false_left_condition = $context;
            $context_with_true_left_condition = $context;
        }

        if ($right_node instanceof Node) {
            $right_context = $this->analyzeAndGetUpdatedContext($context_with_false_left_condition, $node, $right_node);
            $context = (new ContextMergeVisitor(
                $this->code_base,
                $context,
                [$context, $context_with_true_left_condition, $right_context]
            ))->combineChildContextList();
        }

        $context = $this->postOrderAnalyze($context, $node);

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
        // Equivalent to (new PostOrderAnalysisVisitor(...)($node)) but faster than using __invoke()
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

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
        // Equivalent to (new PostOrderAnalysisVisitor(...)($node)) but faster than using __invoke()
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $this->parent_node
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

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
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

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

        $context = $this->context;
        $class = $context->getClassInScope($this->code_base);
        $is_static = ($this->parent_node->flags & \ast\flags\MODIFIER_STATIC) !== 0;
        $property = $class->getPropertyByNameInContext($this->code_base, $prop_name, $context, $is_static);

        $context = $this->context->withScope(new PropertyScope(
            $context->getScope(),
            FullyQualifiedPropertyName::make($class->getFQSEN(), $prop_name)
        ))->withLineNumberStart(
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
