<?php declare(strict_types=1);

namespace Phan;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ContextMergeVisitor;
use Phan\Analysis\NegatedConditionVisitor;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\Analysis\PreOrderAnalysisVisitor;
use Phan\AST\AnalysisVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\Element;
use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use Phan\Plugin\ConfigPluginSet;

use function array_map;
use function count;
use function end;
use function explode;
use function preg_match;
use function rtrim;

/**
 * Analyze blocks of code
 *
 * - Uses `\Phan\Analysis\PreOrderAnalysisVisitor` for pre-order analysis of a node (E.g. entering a function to analyze)
 * - Recursively analyzes child nodes
 * - Uses `\Phan\Analysis\PostOrderAnalysisVisitor` for post-order analysis of a node (E.g. analyzing a statement with the updated Context and emitting issues)
 * - If there is more than one possible child context, merges state from them (variable types)
 *
 * @see self::visit()
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 */
class BlockAnalysisVisitor extends AnalysisVisitor
{

    /**
     * @var Node[]
     * The parent of the current node
     */
    private $parent_node_list = [];

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
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        Node $parent_node = null
    ) {
        parent::__construct($code_base, $context);
        if ($parent_node) {
            $this->parent_node_list[] = $parent_node;
        }
    }

    // No-ops for frequent node types
    public function visitVar(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
        );

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

    /**
     * @param Node $node @phan-unused-param this was analyzed in visitUse
     */
    public function visitUseElem(Node $node) : Context
    {
        // Could invoke plugins, but not right now
        return $this->context;
    }

    /**
     * Analyzes a namespace block or statement (e.g. `namespace NS\SubNS;` or `namespace OtherNS { ... }`)
     * @param Node $node a node of type AST_NAMESPACE
     */
    public function visitNamespace(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
        );

        // If there are multiple namespaces in the file, have to warn about unused entries in the current namespace first.
        // If this is the first namespace, then there wouldn't be any use statements yet.
        // TODO: This may not be the case if the language server is used
        // @phan-suppress-next-line PhanAccessMethodInternal
        $context->warnAboutUnusedUseElements($this->code_base);

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))->visitNamespace($node);

        // We already imported namespace constants earlier; use those.
        // @phan-suppress-next-line PhanAccessMethodInternal
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
            $context = $this->analyzeAndGetUpdatedContext($context, $node, $stmts_node);
        }

        return $this->postOrderAnalyze($context, $node);
    }

    /**
     * Analyzes a node with type AST_NAME (Relative or fully qualified name)
     */
    public function visitName(Node $node) : Context
    {
        $context = $this->context;
        // Only invoke post-order plugins, needed for NodeSelectionPlugin.
        // PostOrderAnalysisVisitor and PreOrderAnalysisVisitor don't do anything.
        // Optimized because this is frequently called
        ConfigPluginSet::instance()->postAnalyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node_list
        );
        return $context;
    }

    /**
     * For non-special nodes such as statement lists (AST_STMT_LIST),
     * we propagate the context and scope from the parent,
     * through the individual statements, and return a Context with the modified scope.
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
     */
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
                $this->handleScalarStmt($node, $context, $child_node);
                continue;
            }
            $context->clearCachedUnionTypes();

            // Step into each child node and get an
            // updated context for the node
            try {
                $context = $this->analyzeAndGetUpdatedContext($context, $node, $child_node);
            } catch (IssueException $e) {
                // This is a fallback - Exceptions should be caught at a deeper level if possible
                Issue::maybeEmitInstance($this->code_base, $context, $e->getIssueInstance());
            }
        }
        $plugin_set->postAnalyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node_list
        );
        return $context;
    }

    /**
     * @param Node $node
     * @param Context $context
     * @param int|float|string|null $child_node (probably not null)
     * @return void
     */
    private function handleScalarStmt(Node $node, Context $context, $child_node)
    {
        if (\is_string($child_node)) {
            if (\strpos($child_node, '@phan-') !== false) {
                // Add @phan-var and @phan-suppress annotations in string literals to the local scope
                $this->analyzeSubstituteVarAssert($this->code_base, $context, $child_node);
            } else {
                Issue::maybeEmit(
                    $this->code_base,
                    $context,
                    Issue::NoopStringLiteral,
                    $context->getLineNumberStart() ?: $this->getLineNumberOfParent() ?: $node->lineno,
                    StringUtil::jsonEncode($child_node)
                );
            }
        } elseif (\is_scalar($child_node)) {
            Issue::maybeEmit(
                $this->code_base,
                $context,
                Issue::NoopNumericLiteral,
                $context->getLineNumberStart() ?: $this->getLineNumberOfParent() ?: $node->lineno,
                \var_export($child_node, true)
            );
        }
    }

    private function getLineNumberOfParent() : int
    {
        $parent = end($this->parent_node_list);
        if (!($parent instanceof Node)) {
            return 0;
        }
        return $parent->lineno;
    }

    const PHAN_FILE_SUPPRESS_REGEX =
        '/@phan-file-suppress\s+' . Builder::SUPPRESS_ISSUE_LIST . '/';  // @phan-suppress-current-line PhanAccessClassConstantInternal


    const PHAN_VAR_REGEX =
        '/@(phan-var(?:-force)?)\b\s*(' . UnionType::union_type_regex . ')\s*&?\\$' . Builder::WORD_REGEX . '/';
    // @phan-suppress-previous-line PhanAccessClassConstantInternal

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
        if (\preg_match_all(self::PHAN_VAR_REGEX, $text, $matches, \PREG_SET_ORDER) > 0) {
            $has_known_annotations = true;
            foreach ($matches as $group) {
                $annotation_name = $group[1];
                $type_string = $group[2];
                $var_name = $group[16];
                $type = UnionType::fromStringInContext($type_string, $context, Type::FROM_PHPDOC);
                self::createVarForInlineComment($code_base, $context, $var_name, $type, $annotation_name === 'phan-var-force');
            }
        }

        if (\preg_match_all(self::PHAN_FILE_SUPPRESS_REGEX, $text, $matches, \PREG_SET_ORDER) > 0) {
            $has_known_annotations = true;
            if (!Config::getValue('disable_file_based_suppression')) {
                foreach ($matches as $group) {
                    $issue_name_list = $group[1];
                    foreach (array_map('trim', explode(',', $issue_name_list)) as $issue_name) {
                        $code_base->addFileLevelSuppression($context->getFile(), $issue_name);
                    }
                }
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
     * @see ConditionVarUtil::getVariableFromScope()
     */
    private static function createVarForInlineComment(CodeBase $code_base, Context $context, string $var_name, UnionType $type, bool $create_variable)
    {
        if (!$context->getScope()->hasVariableWithName($var_name)) {
            if (Variable::isHardcodedVariableInScopeWithName($var_name, $context->isInGlobalScope())) {
                return;
            }
            if (!$create_variable && !($context->isInGlobalScope() && Config::getValue('ignore_undeclared_variables_in_global_scope'))) {
                Issue::maybeEmitWithParameters(
                    $code_base,
                    $context,
                    Issue::UndeclaredVariable,
                    $context->getLineNumberStart(),
                    [$var_name],
                    IssueFixSuggester::suggestVariableTypoFix($code_base, $context, $var_name)
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
        $variable = clone($context->getScope()->getVariableByName(
            $var_name
        ));
        $variable->setUnionType($type);
        $context->addScopeVariable($variable);
    }

    /**
     * For non-special nodes, we propagate the context and scope
     * from the parent, through the children and return the
     * modified scope,
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
        $context = $this->context;
        $context->setLineNumberStart($node->lineno);

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
     * Effectively the same as (new BlockAnalysisVisitor(..., $context, $node, ...)child_node))
     * but is much less repetitive and verbose, and slightly more efficient.
     *
     * NOTE: This is called extremely frequently, so the real signature types were omitted for performance.
     *
     * @param Context $context - The original context for $node, before analyzing $child_node
     *
     * @param Node $node - The parent node of $child_node
     *
     * @param Node $child_node - The node which will be analyzed to create the updated context.
     *
     * @return Context (The unmodified $context, or a different Context instance with modifications)
     */
    private function analyzeAndGetUpdatedContext($context, $node, $child_node)
    {
        // Modify the original object instead of creating a new BlockAnalysisVisitor.
        // this is slightly more efficient, especially if a large number of unchanged parameters would exist.
        $old_context = $this->context;
        $this->context = $context;
        $this->parent_node_list[] = $node;
        try {
            return $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        } finally {
            $this->context = $old_context;
            \array_pop($this->parent_node_list);
        }
    }

    /**
     * For "for loop" nodes, we analyze the components in the following order as a heuristic:
     *
     * 1. propagate the context and scope from the parent,
     * 2. Update the scope with the initializer of the loop,
     * 3. Update the scope with the side effects (e.g. assignments) of the condition of the loop
     * 4. Update the scope with the child statements both inside and outside the loop (ignoring branches which will continue/break),
     * 5. Update the scope with the statement evaluated after the loop
     *
     * Then, Phan returns the context with the modified scope.
     *
     * TODO: merge the contexts together, for better analysis of possibly undefined variables
     *
     *               │
     *        cond   ▼
     *   ●──────●────● init
     *   │
     *   │         (TODO: merge contexts instead)
     *   ●──●──▶●
     *   stmts  │
     *          │
     *          ● 'loop' child node (after inner statements)
     *          │
     *          ▼
     *
     * Note: Loop analysis uses heuristics for performance and simplicity.
     * If we analyzed the stmts of the inner loop body another time,
     * we might discover even more possible types of input/resulting variables.
     *
     * Current limitations:
     *
     * - contexts from individual break/continue stmts aren't merged
     * - contexts from individual break/continue stmts aren't merged
     *
     * @param Node $node
     * An AST node (for a for loop) we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitFor(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
        );

        $init_node = $node->children['init'];
        if ($init_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($init_node->lineno),
                $node,
                $init_node
            );
        }

        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            // The typical case is `for (init; $x; loop) {}`
            // But `for (init; $x, $y; loop) {}` is rare but possible, which requires evaluating those in order.
            // Evaluate the list of cond expressions in order.
            foreach ($condition_node->children as $condition_subnode) {
                if ($condition_subnode instanceof Node) {
                    $context = $this->analyzeAndGetUpdatedContext(
                        $context->withLineNumberStart($condition_subnode->lineno),
                        $node,  // TODO: condition_node?
                        $condition_subnode
                    );
                }
            }
        }

        if ($stmts_node = $node->children['stmts']) {
            // Look to see if any proofs we do within the condition of the for
            // can say anything about types within the statement
            // list.
            // TODO: Distinguish between inner and outer context.
            //   E.g. `for (; $x = cond(); ) {}` will have truthy $x within the loop
            //   but falsey outside the loop, if there are no breaks.
            if ($condition_node instanceof Node) {
                $context = (new ConditionVisitor(
                    $this->code_base,
                    $context
                ))->__invoke($condition_node);
            }
            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno),
                    $node,
                    $stmts_node
                );
            }
        }
        // Analyze the loop after analyzing the statements, in case it uses variables defined within the statements.
        $loop_node = $node->children['loop'];
        if ($loop_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($loop_node->lineno),
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
     * For "while loop" nodes, we analyze the components in the following order as a heuristic:
     * (This is pretty much the same as analyzing a for loop with the 'init' and 'loop' nodes left blank)
     *
     * 1. propagate the context and scope from the parent,
     * 2. Update the scope with the side effects (e.g. assignments) of the condition of the loop
     * 3. Update the scope with the child statements both inside and outside the loop (ignoring branches which will continue/break),
     *
     * Then, Phan returns the context with the modified scope.
     *
     * TODO: merge the contexts together, for better analysis of possibly undefined variables
     *
     * NOTE: "Do while" loops are just handled by visit(), Phan sees and analyzes 'stmts' before 'cond'.
     *
     *
     *          │
     *          ▼
     *   ●──────● cond
     *   │
     *   │         (TODO: merge contexts instead)
     *   ●──●──▶●
     *   stmts  │
     *          │
     *          │
     *          │
     *          ▼
     *
     * @param Node $node
     * An AST node (for a while loop) we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitWhile(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
        );

        // Let any configured plugins do a pre-order
        // analysis of the node.
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            // Analyze the cond expression.
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($condition_node->lineno),
                $node,
                $condition_node
            );
        }

        if ($stmts_node = $node->children['stmts']) {
            // Look to see if any proofs we do within the condition of the while
            // can say anything about types within the statement
            // list.
            // TODO: Distinguish between inner and outer context.
            //   E.g. `while ($x = cond()) {}` will have truthy $x within the loop
            //   but falsey outside the loop, if there are no breaks.
            if ($condition_node instanceof Node) {
                $context = (new ConditionVisitor(
                    $this->code_base,
                    $this->context
                ))->__invoke($condition_node);
            }

            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno),
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
            $node->lineno
        );

        // NOTE: This is different from other analysis visitors because analyzing 'cond' with `||` has side effects
        // after supporting `BlockAnalysisVisitor->visitBinaryOp()`
        // TODO: Calling analyzeAndGetUpdatedContext before preOrderAnalyze is a hack.

        // TODO: This is redundant and has worse knowledge of the specific types of blocks than ConditionVisitor does.
        // TODO: Implement a hybrid BlockAnalysisVisitor+ConditionVisitor that will do a better job of inferences and reducing false positives? (and reduce the redundant work)

        // E.g. the below code would update the context of BlockAnalysisVisitor in BlockAnalysisVisitor->visitBinaryOp()
        //
        //     if (!(is_string($x) || $x === null)) {}
        //
        // But we want to let BlockAnalysisVisitor modify the context for cases such as the below:
        //
        // $result = !($x instanceof User) || $x->meetsCondition()
        $condition_node = $node->children['cond'];
        if ($condition_node instanceof Node) {
            $context = $this->analyzeAndGetUpdatedContext(
                $context->withLineNumberStart($condition_node->lineno),
                $node,
                $condition_node
            );
        }

        $context = $this->preOrderAnalyze($context, $node);

        if ($stmts_node = $node->children['stmts']) {
            if ($stmts_node instanceof Node) {
                $context = $this->analyzeAndGetUpdatedContext(
                    $context->withScope(
                        new BranchScope($context->getScope())
                    )->withLineNumberStart($stmts_node->lineno),
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
        $context = $this->context;
        $context->setLineNumberStart($node->lineno);
        $context = $this->preOrderAnalyze(clone($context), $node);

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
            $context,
            $child_context_list
        ))->__invoke($node);

        $this->postOrderAnalyze($context, $node);

        // Return the initial context as we exit
        return $this->context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     */
    public function visitSwitchList(Node $node) : Context
    {
        // Make a copy of the internal context so that we don't
        // leak any changes within the closed context to the
        // outer scope
        $context = $this->context;
        $context->setLineNumberStart($node->lineno);
        $context = $this->preOrderAnalyze(clone($context), $node);

        $scope = $context->getScope();
        $child_context_list = [];

        // TODO: Improve inferences in switch statements?
        // TODO: Behave differently if switch lists don't cover every case (e.g. if there is no default)
        $has_default = false;
        list($switch_variable_node, $switch_variable_condition) = $this->createSwitchConditionAnalyzer(
            end($this->parent_node_list)->children['cond']
        );
        $previous_child_context = null;
        foreach ($node->children as $i => $child_node) {
            $cond_node = $child_node->children['cond'];
            // Step into each child node and get an
            // updated context for the node
            $child_context = $context->withScope(new BranchScope($scope));
            $child_context->withLineNumberStart($child_node->lineno);
            if ($cond_node !== null) {
                if ($switch_variable_condition) {
                    // Add the variable type from the above case statements, if it was possible for it to fall through
                    // TODO: Also support switch(get_class($variable))
                    $visitor = new ConditionVisitor($this->code_base, $child_context);
                    $child_context = $switch_variable_condition($child_context, $cond_node);
                    if ($previous_child_context !== null) {
                        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this being non-null is implied by switch_variable_condition
                        $variable = $visitor->getVariableFromScope($switch_variable_node, $child_context);
                        if ($variable) {
                            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this being non-null is implied by switch_variable_condition
                            $old_variable = $visitor->getVariableFromScope($switch_variable_node, $previous_child_context);

                            if ($old_variable) {
                                $variable = clone($variable);
                                $variable->setUnionType($variable->getUnionType()->withUnionType($old_variable->getUnionType()));
                                $child_context->addScopeVariable($variable);
                            }
                        }
                    }
                }
            }

            $child_context = $this->analyzeAndGetUpdatedContext($child_context, $node, $child_node);

            if ($cond_node === null) {
                $has_default = true;
            }
            // We can improve analysis of `case` blocks by using
            // a BlockExitStatusChecker to avoid propagating invalid inferences.
            $stmts_node = $child_node->children['stmts'];
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
            $block_exit_status = (new BlockExitStatusChecker())->__invoke($stmts_node);
            // equivalent to !willUnconditionallyThrowOrReturn()
            $previous_child_context = null;
            if (($block_exit_status & ~BlockExitStatusChecker::STATUS_THROW_OR_RETURN_BITMASK)) {
                // Skip over case statements that only ever throw or return
                if (count($stmts_node->children ?? []) !== 0 || $i === count($node->children) - 1) {
                    // and skip over empty statement lists, unless they're the last in a long line of empty statement lists
                    $child_context_list[] = $child_context;
                }

                if ($block_exit_status & BlockExitStatusChecker::STATUS_PROCEED) {
                    $previous_child_context = $child_context;
                }
            }
        }

        if (count($child_context_list) > 0) {
            if (!$has_default) {
                $child_context_list[] = $context;
            }
            if (count($child_context_list) >= 2) {
                // For case statements, we need to merge the contexts
                // of all child context into a single scope based
                // on any possible branching structure
                $context = (new ContextMergeVisitor(
                    $context,
                    $child_context_list
                ))->combineChildContextList();
            } else {
                $context = $child_context_list[0];
            }
        }

        return $this->postOrderAnalyze($context, $node);
    }

    /**
     * @param Node|int|string|float $switch_case_node
     * @return array{0:?Node,1:?Closure}
     */
    private function createSwitchConditionAnalyzer($switch_case_node) : array
    {
        if (!$switch_case_node instanceof Node) {
            return [null, null];
        }
        $switch_kind = ($switch_case_node->kind ?? null);
        try {
            if ($switch_kind === ast\AST_VAR) {
                $switch_variable = (new ConditionVisitor($this->code_base, $this->context))->getVariableFromScope($switch_case_node, $this->context);
                if (!$switch_variable) {
                    return [null, null];
                }
                return [
                    $switch_case_node,
                    /**
                     * @param Node|string|int|float $cond_node
                     */
                    function (Context $child_context, $cond_node) use ($switch_case_node) : Context {
                        $visitor = new ConditionVisitor($this->code_base, $child_context);
                        return $visitor->updateVariableToBeIdentical($switch_case_node, $cond_node, $child_context);
                    },
                ];
            } elseif ($switch_kind === ast\AST_CALL) {
                $name = $switch_case_node->children['expr']->children['name'] ?? null;
                if (\is_string($name)) {
                    $name = \strtolower($name);
                    if ($name === 'get_class') {
                        $switch_variable_node = $switch_case_node->children['args']->children[0] ?? null;
                        if (($switch_variable_node->kind ?? null) !== ast\AST_VAR) {
                            return [null, null];
                        }
                        $switch_variable = (new ConditionVisitor($this->code_base, $this->context))->getVariableFromScope($switch_variable_node, $this->context);
                        if (!$switch_variable) {
                            return [null, null];
                        }
                        return [
                            $switch_variable_node,
                            /**
                             * @param Node|string|int|float $cond_node
                             */
                            function (Context $child_context, $cond_node) use ($switch_variable_node) : Context {
                                $visitor = new ConditionVisitor($this->code_base, $child_context);
                                return $visitor->analyzeClassAssertion(
                                    $switch_variable_node,
                                    $cond_node
                                ) ?? $child_context;
                            },
                        ];
                    }
                }
            }
        } catch (IssueException $_) {
            // do nothing, we warn elsewhere
        }
        return [null, null];
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
            $node->lineno
        );

        $context = $this->preOrderAnalyze($context, $node);

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
            // The conditions need to communicate to the outer
            // scope for things like assigning variables.
            $child_context = clone($fallthrough_context);

            $child_context->withLineNumberStart(
                $child_node->lineno
            );

            // Step into each child node and get an
            // updated context for the node
            $child_context = $this->analyzeAndGetUpdatedContext($child_context, $node, $child_node);

            // Issue #406: We can improve analysis of `if` blocks by using
            // a BlockExitStatusChecker to avoid propagating invalid inferences.
            // TODO: we may wish to check for a try block between this line's scope
            // and the parent function's (or global) scope,
            // to reduce false positives.
            // (Variables will be available in `catch` and `finally`)
            // This is mitigated by finally and catch blocks being unaware of new variables from try{} blocks.
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
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
                $fallthrough_context,  // e.g. "if (!is_string($x)) { $x = ''; }" should result in inferring $x is a string.
                $child_context_list
            ))->visitIf($node);
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
            $node->lineno
        );

        $context = $this->preOrderAnalyze($context, $node);

        // With a context that is inside of the node passed
        // to this method, we analyze all children of the
        // node.

        $try_node = $node->children['try'];

        // The conditions need to communicate to the outer
        // scope for things like assigning variables.
        $try_context = $context->withScope(
            new BranchScope($context->getScope())
        );

        $try_context->withLineNumberStart(
            $try_node->lineno
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
        $context = (new ContextMergeVisitor(
            $context,
            [$try_context]
        ))->mergeTryContext($node);

        // We collect all child context so that the
        // PostOrderAnalysisVisitor can optionally operate on
        // them
        $catch_context_list = [$try_context];

        $catch_nodes = $node->children['catches']->children ?? [];

        foreach ($catch_nodes as $catch_node) {
            // Note: ContextMergeVisitor expects to get each individual catch
            if (!$catch_node instanceof Node) {
                throw new AssertionError("Expected catch_node to be a Node");
            }

            // The conditions need to communicate to the outer
            // scope for things like assigning variables.
            $catch_context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $catch_context->withLineNumberStart(
                $catch_node->lineno
            );

            // Step into each catch node and get an
            // updated context for the node
            $catch_context = $this->analyzeAndGetUpdatedContext($catch_context, $node, $catch_node);
            // NOTE: We let ContextMergeVisitor->mergeCatchContext decide if the block exit status is valid.
            $catch_context_list[] = $catch_context;
        }

        $this->checkUnreachableCatch($catch_nodes, $context);

        // first context is the try. If there's a second context, it's a catch.
        if (count($catch_context_list) >= 2) {
            // For switch/try statements, we need to merge the contexts
            // of all child context into a single scope based
            // on any possible branching structure
            $context = (new ContextMergeVisitor(
                $context,
                $catch_context_list
            ))->mergeCatchContext($node);
        }

        $finally_node = $node->children['finally'] ?? null;
        if ($finally_node) {
            if (!($finally_node instanceof Node)) {
                throw new AssertionError("Expected non-null finally node to be a Node");
            }
            // Because finally is always executed, we reuse $context

            // The conditions need to communicate to the outer
            // scope for things like assigning variables.
            $context = $context->withScope(
                new BranchScope($context->getScope())
            );

            $context->withLineNumberStart(
                $finally_node->lineno
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
     * @param array<int,Node> $catch_nodes
     * @param Context $context
     * @return void
     */
    private function checkUnreachableCatch(array $catch_nodes, Context $context)
    {
        if (count($catch_nodes) <= 1) {
            return;
        }
        $caught_union_types = [];
        $code_base = $this->code_base;

        foreach ($catch_nodes as $catch_node) {
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall should be impossible to throw
            $union_type = UnionTypeVisitor::unionTypeFromClassNode(
                $code_base,
                $context,
                $catch_node->children['class']
            )->objectTypesWithKnownFQSENs();

            $catch_line = $catch_node->lineno;

            foreach ($union_type->getTypeSet() as $type) {
                foreach ($type->asExpandedTypes($code_base)->getTypeSet() as $ancestor_type) {
                    // Check if any of the ancestors were already caught by a previous catch statement
                    $line = $caught_union_types[\spl_object_id($ancestor_type)] ?? null;

                    if ($line !== null) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::UnreachableCatch,
                            $catch_line,
                            (string)$type,
                            $line,
                            (string)$ancestor_type
                        );
                        break;
                    }
                }
            }
            foreach ($union_type->getTypeSet() as $type) {
                // Track where this ancestor type was thrown
                $caught_union_types[\spl_object_id($type)] = $catch_line;
            }
        }
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
        if ($flags === ast\flags\BINARY_BOOL_AND) {
            return $this->analyzeBinaryBoolAnd($node);
        } elseif ($flags === ast\flags\BINARY_BOOL_OR) {
            return $this->analyzeBinaryBoolOr($node);
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
    public function analyzeBinaryBoolAnd(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
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
        // 2. Create a context to analyze the right-hand side with any inferences possible from left (e.g. ($x instanceof MyClass) && $x->foo()
        // 3. Analyze the right-hand side
        // 4. Merge the possibly evaluated $right_context for the right-hand side into the original context. (The left_node is guaranteed to have been evaluated, so it becomes $context)

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
            ))->__invoke($left_node);
            $context_with_false_left_condition = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))->__invoke($left_node);
        } else {
            $context_with_left_condition = $context;
            $context_with_false_left_condition = $context;
        }

        if ($right_node instanceof Node) {
            $right_context = $this->analyzeAndGetUpdatedContext($context_with_left_condition, $node, $right_node);
            $context = (new ContextMergeVisitor(
                $context,
                [$context, $context_with_false_left_condition, $right_context]
            ))->combineChildContextList();
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
    public function analyzeBinaryBoolOr(Node $node) : Context
    {
        $context = $this->context->withLineNumberStart(
            $node->lineno
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
        // 2. Create a context to analyze the right-hand side with any inferences possible from left (e.g. (!($x instanceof MyClass)) || $x->foo()
        // 3. Analyze the right-hand side
        // 4. Merge the possibly evaluated $right_context for the right-hand side into the original context. (The left_node is guaranteed to have been evaluated, so it becomes $context)

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
            $node->lineno
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
            ))->__invoke($cond_node);
            $false_context = (new NegatedConditionVisitor(
                $this->code_base,
                $base_context
            ))->__invoke($cond_node);
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
        if (count($child_context_list) >= 1) {
            if (count($child_context_list) < 2) {
                $child_context_list[] = $context;
            }
            $context = (new ContextMergeVisitor(
                $context,
                $child_context_list
            ))->combineChildContextList();
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
     *
     * @see self::visitClosedContext()
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
     *
     * @see self::visitClosedContext()
     */
    public function visitMethod(Node $node) : Context
    {
        // Make a copy of the internal context so that we don't
        // leak any changes within the method to the
        // outer scope
        $context = $this->context;
        $context->setLineNumberStart($node->lineno);
        $context = $this->preOrderAnalyze(clone($context), $node);

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
            $this->analyzeAndGetUpdatedContext($context, $node, $child_node);
        }

        $this->postOrderAnalyze($context, $node);

        // Return the initial context as we exit
        return $this->context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     *
     * @see self::visitClosedContext()
     */
    public function visitFuncDecl(Node $node) : Context
    {
        // Analyze nodes with AST_FUNC_DECL the same way as AST_METHOD
        return $this->visitMethod($node);
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     *
     * @return Context
     * The updated context after visiting the node
     *
     * @see self::visitClosedContext()
     */
    public function visitClosure(Node $node) : Context
    {
        return $this->visitClosedContext($node);
    }

    /**
     * Run the common steps for pre-order analysis phase of a Node.
     *
     * 1. Run the pre-order analysis steps, updating the context and scope
     * 2. Run plugins with pre-order steps, usually (but not always) updating the context and scope.
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
     *
     * 1. Run analysis steps and update the scope and context
     * 2. Run plugins (usually (but not always) without updating the scope)
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
            $context->withLineNumberStart($node->lineno),
            $this->parent_node_list
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

        // let any configured plugins analyze the node
        ConfigPluginSet::instance()->postAnalyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node_list
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
            $node->lineno
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

        $context = $this->postOrderAnalyze($context, $node);

        return $context;
    }

    /**
     * @param Node $node
     * An AST node we'd like to analyze the statements for
     * @see self::visit() - This is similar to visit() except that the check if $is_static requires parent_node,
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

        $context = $this->context->withScope(new PropertyScope(
            $context->getScope(),
            FullyQualifiedPropertyName::make($class->getFQSEN(), $prop_name)
        ))->withLineNumberStart(
            $node->lineno
        );

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
