<?php declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Diagnostic;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;

/**
 * This is a subclass of TolerantASTConverter
 * that maps the original AST to the corresponding generated ast\Node.
 *
 * This is planned for use with "Go to definition" requests, completion requests, etc.
 *
 * (This lets you know the byte offset of a given node and how long that node is)
 *
 * Workflow:
 *
 * 1. A request is received for finding the type definition of the ast\Node at byte offset 100 in a given file
 * 2. Phan will figure out which PhpParser\Token that is referring to.
 *
 *    If this is a property, method invocation, constant, etc.,
 *    this will refer to the property access (Not the name), constant access, etc.
 *
 *    This is done via iterating over the tokens, finding the token that contains the offset,
 *    then walking back up to the parent
 * 3. Then, Phan will use $node_mapping to update the corresponding AST node
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanUndeclaredProperty deliberately adding dynamic property
 *
 * The logging to STDERR can be uncommented if you have issues debugging why
 * Phan can't locate a given node's definition.
 */
class TolerantASTConverterWithNodeMapping extends TolerantASTConverter
{
    /**
     * @var PhpParser\Node|PhpParser\Token|null
     * TODO: If this is null, then just create a parent instance and call that.
     */
    private static $closest_node_or_token;

    /** @var int */
    private $expected_byte_offset;

    public function __construct(int $expected_byte_offset)
    {
        $this->expected_byte_offset = $expected_byte_offset;
    }

    /**
     * @param Diagnostic[] &$errors @phan-output-reference
     *
     * @return \ast\Node
     */
    public function parseCodeAsPHPAST(string $file_contents, int $version, array &$errors = [])
    {
        $found = false;
        // Force the byte offset to be within the
        $byte_offset = \max(0, \min(\strlen($file_contents), $this->expected_byte_offset));

        if (!\in_array($version, self::SUPPORTED_AST_VERSIONS)) {
            throw new \InvalidArgumentException(sprintf("Unexpected version: want %s, got %d", \implode(', ', self::SUPPORTED_AST_VERSIONS), $version));
        }

        // Aside: this can be implemented as a stub.
        try {
            $parser_node = static::phpParserParse($file_contents, $errors);
            self::findNodeAtOffset($parser_node, $byte_offset);
            // fwrite(STDERR, "Seeking node: " . json_encode(self::$closest_node_or_token). "\n");
            $result = $this->phpParserToPhpast($parser_node, $version, $file_contents);
            $original_node = $parser_node;
            return $result;
        } catch (\Throwable $e) {
            // fwrite(STDERR, "saw exception: " . $e->getMessage());
            throw $e;
        } finally {
            self::$closest_node_or_token = null;
        }
    }

    /**
     * Records the closest node or token to the given offset.
     * Heuristics are used to ensure that this can map to an ast\Node.
     * TODO: Finish implementing
     *
     * @return void
     */
    private static function findNodeAtOffset(PhpParser\Node $parser_node, int $offset)
    {
        self::$closest_node_or_token = null;
        self::findNodeAtOffsetRecursive($parser_node, $offset);
    }

    const _KINDS_TO_RETURN_PARENT = [
        TokenKind::Name,
        TokenKind::VariableName,
        TokenKind::StringLiteralToken,  // TODO: Make this depend on context
    ];

    /**
     * @return bool|PhpParser\Node|PhpParser\Token (Returns $parser_node if that node was what the cursor is pointing directly to)
     */
    private static function findNodeAtOffsetRecursive($parser_node, int $offset)
    {
        foreach ($parser_node->getChildNodesAndTokens() as $key => $node_or_token) {
            if ($node_or_token instanceof Token) {
                if ($node_or_token->getEndPosition() > $offset) {
                    if ($node_or_token->start > $offset) {
                        // The cursor is hovering over whitespace.
                        // Give up
                        return true;
                    }
                    if (\in_array($node_or_token->kind, self::_KINDS_TO_RETURN_PARENT, true)) {
                        // We want the parent of a Name, e.g. a class
                        self::$closest_node_or_token = $parser_node;
                        // fwrite(STDERR, "Found node: " . json_encode($parser_node) . "\n");
                        return $parser_node;
                    }
                    // fwrite(STDERR, "Found token: " . json_encode($parser_node));
                    self::$closest_node_or_token = $node_or_token;
                    // TODO: Handle other cases
                    return $node_or_token;
                }
            }
            if ($node_or_token instanceof PhpParser\Node) {
                $state = self::findNodeAtOffsetRecursive($node_or_token, $offset);
                if ($state) {
                    // fwrite(STDERR, "Found parent node for $key: " . get_class($parser_node) . "\n");
                    // fwrite(STDERR, "Found parent node for $key: " . json_encode($parser_node) . "\n");
                    if ($state instanceof PhpParser\Node) {
                        return self::adjustClosestNodeOrToken($parser_node, $key);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return PhpParser\Node|true
     */
    private static function adjustClosestNodeOrToken(PhpParser\Node $node, $key) {
        // TODO: Better heuristic
        if ($key === 'memberName' || $key === 'callableExpression') {
            // fwrite(STDERR, "Adjusted node: " . json_encode($node) . "\n");
            self::$closest_node_or_token = $node;
            return $node;
        }
        if ($key === 'callableExpression') {
            // fwrite(STDERR, "Adjusted node: " . json_encode($node) . "\n");
            self::$closest_node_or_token = $node;
            return $node;
        }
        return true;
    }

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool - whatever ast\parse_code would return as the equivalent.
     * @throws InvalidNodeException when self::$should_add_placeholders is false, like many of these methods.
     */
    protected static function phpParserNodeToAstNodeOrPlaceholderExpr($n)
    {
        // fprintf(STDERR, "Comparing %s to %s\n", get_class($n), get_class(self::$closest_node_or_token));
        $ast_node = parent::phpParserNodeToAstNodeOrPlaceholderExpr($n);
        if ($n === self::$closest_node_or_token) {
            // fwrite(STDERR, "Marking corresponding node as flagged: " . json_encode($n) . "\n");
            // fflush(STDERR);
            if ($ast_node instanceof ast\Node) {
                $ast_node->isSelected = true;
            }
        }
        return $ast_node;
    }

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool|null - whatever ast\parse_code would return as the equivalent.
     */
    protected static function phpParserNodeToAstNode($n)
    {
        $ast_node = parent::phpParserNodeToAstNode($n);
        if ($n === self::$closest_node_or_token) {
            // fwrite(STDERR, "Marking corresponding node as flagged: " . json_encode($n) . "\n");
            if ($ast_node instanceof ast\Node) {
                $ast_node->isSelected = true;
            }
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
        // fprintf(STDERR, "Comparing %s to %s\n", get_class($n), get_class(self::$closest_node_or_token));
        $ast_node = parent::phpParserNonValueNodeToAstNode($n);
        if ($n === self::$closest_node_or_token) {
            // fwrite(STDERR, "Marking corresponding node as flagged: " . json_encode($n) . "\n");
            if ($ast_node instanceof ast\Node) {
                // Create a dynamic property
                $ast_node->isSelected = true;
            }
        }
        return $ast_node;
    }

    /**
     * TODO: Call this for property name accesses, etc.
     *
     * Use in the base class if a node is created without calling phpParserNodeToAstNode
     *
     * @param PhpParser\Node|PhpParser\Token $n
     * @param ast\Node $ast_node
     * @return void
     */
    protected static function linkNode($n, $ast_node)
    {
        // fwrite(STDERR, "Marking corresponding node as flagged: " . json_encode($n) . "\n");
        if ($n === self::$closest_node_or_token && $ast_node instanceof ast\Node) {
            $ast_node->isSelected = true;
        }
    }
}
