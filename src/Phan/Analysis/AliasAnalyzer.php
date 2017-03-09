<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use ast\Node;

/**
 * Analyze files looking for top-level class_alias calls to
 * add into the CodeBase. Does not handle conditional calls,
 * or calls that happen inside functions/methods/closures/etc.
 */
class AliasAnalyzer
{
    /**
     * This pass parses code and looks for top level
     * class_alias calls.
     *
     * @param CodeBase $code_base
     * The CodeBase represents state across the entire
     * code base. This is a mutable object which is
     * populated as we parse files
     *
     * @param string $file_path
     * The full path to a file we'd like to parse
     *
     * @return Context
     */
    public static function parseFile(CodeBase $code_base, string $file_path) : Context
    {
        $context = (new Context)->withFile($file_path);

        // Convert the file to an Abstract Syntax Tree
        // before passing it on to the recursive version
        // of this method
        try {
            $node = \ast\parse_file(
                Config::projectPath($file_path),
                Config::get()->ast_version
            );
        } catch (\ParseError $parse_error) {
            // Previously handled by Analysis::parseFile
            return $context;
        }

        if (empty($node)) {
            // Previously handled by Analysis::parseFile
            return $context;
        }

        return self::parseNodeInContext(
            $code_base,
            $context,
            $node
        );
    }

    /**
     * Parse the given node in the given context populating
     * the code base within the context as a side effect. The
     * returned context is the new context from within the
     * given node.
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     *
     * @param Context $context
     * The context in which this node exists
     *
     * @param Node $node
     * A node to parse and scan for errors
     *
     * @return Context
     * The context from within the node is returned
     */
    public static function parseNodeInContext(CodeBase $code_base, Context $context, Node $node) : Context
    {
        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new AliasVisitor(
            $code_base,
            $context->withLineNumberStart($node->lineno ?? 0)
        ))($node);

        assert(!empty($context), 'Context cannot be null');

        // Recurse into each child node
        $child_context = $context;
        foreach ($node->children ?? [] as $child_node) {

            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            if (!self::shouldVisitNode($child_node)) {
                $child_context->withLineNumberStart(
                    $child_node->lineno ?? 0
                );
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $child_context = self::parseNodeInContext($code_base, $child_context, $child_node);

            assert(!empty($child_context), 'Context cannot be null');
        }

        // Pass the context back up to our parent
        return $context;
    }

    /**
     * Only recurse into the set of nodes that are executed
     * at the top level, when the file is initially parsed
     * by php.
     *
     * @return bool - true if a node should be visited
     */
    public static function shouldVisitNode(Node $node) : bool {
        switch ($node->kind) {
            case \ast\AST_CALL:
            case \ast\AST_NAMESPACE:
            case \ast\AST_STMT_LIST:
                return true;
        }

        return false;
    }
}
