<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Token;

/**
 * This is a subclass of TolerantASTConverter
 * that **almost always** maps the original AST to the corresponding generated ast\Node.
 *
 * This is used for code modification, and will be used for code style checks.
 *
 * @phan-file-suppress PhanUndeclaredProperty
 */
class TolerantASTConverterPreservingOriginal extends TolerantASTConverter
{
    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool|null - whatever ast\parse_code would return as the equivalent.
     * @throws InvalidNodeException when self::$should_add_placeholders is false, like many of these methods.
     * @override
     */
    protected static function phpParserNodeToAstNodeOrPlaceholderExpr($n)
    {
        // fprintf(STDERR, "Comparing %s to %s\n", get_class($n), get_class(self::$closest_node_or_token));
        $ast_node = parent::phpParserNodeToAstNodeOrPlaceholderExpr($n);
        if ($ast_node instanceof ast\Node) {
            $ast_node->tolerant_ast_node = $n;
        }
        return $ast_node;
    }

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool|null - whatever ast\parse_code would return as the equivalent.
     * @override
     */
    protected static function phpParserNodeToAstNode($n)
    {
        $ast_node = parent::phpParserNodeToAstNode($n);
        if ($ast_node instanceof ast\Node) {
            $ast_node->tolerant_ast_node = $n;
        }
        return $ast_node;
    }

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool|null - whatever ast\parse_code would return as the equivalent.
     * @override
     */
    protected static function phpParserNonValueNodeToAstNode($n)
    {
        $ast_node = parent::phpParserNonValueNodeToAstNode($n);
        if ($ast_node instanceof ast\Node) {
            $ast_node->tolerant_ast_node = $n;
        }
        return $ast_node;
    }

    /**
     * @override
     */
    protected static function astStmtUseOrGroupUseFromUseClause(
        PhpParser\Node\NamespaceUseClause $use_clause,
        ?int $parser_use_kind,
        int $start_line
    ): ast\Node {
        // fwrite(STDERR, "Calling astStmtUseOrGroupUseFromUseClause for " . json_encode($use_clause) . "\n");
        $ast_node = parent::astStmtUseOrGroupUseFromUseClause($use_clause, $parser_use_kind, $start_line);
        $ast_node->tolerant_ast_node = $use_clause;
        return $ast_node;
    }

    /**
     * @param PhpParser\Node\QualifiedName|Token|null $type
     * @override
     */
    protected static function phpParserTypeToAstNode($type, int $line): ?\ast\Node
    {
        $ast_node = parent::phpParserTypeToAstNode($type, $line);
        if ($ast_node instanceof ast\Node) {
            $ast_node->tolerant_ast_node = $type;
        }
        return $ast_node;
    }
}
