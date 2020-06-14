<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\flags;
use ast\Node;
use Closure;

/**
 * This adds annotations for Phan analysis to a given node,
 * modifying the flags of a node in place.
 * This adds custom children ($node->children['phan_nf']) to node types that have a variable number of children or expected values for flags
 *
 * and returns the new Node.
 * The original \ast\Node objects are not modified.
 *
 * This adds to $node->children - Many AST node kinds can be used in places Phan needs to know about.
 * (for being potentially null/undefined)
 *
 * Current annotations:
 *
 * 1. Mark $x in isset($x['key']['nested']) as being acceptable to have null offsets.
 *    Same for $x in $x ?? null, empty($x['offset']), and so on.
 * 2. Mark $x and $x['key'] in "$x['key'] = $y" as being acceptable to be null or undefined.
 *    and so on (e.g. ['key' => $x[0]] = $y)
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class PhanAnnotationAdder
{
    public const PHAN_NODE_FLAGS = 'phan_nf';

    public const FLAG_INITIALIZES = 1 << 28;
    public const FLAG_IGNORE_NULLABLE = 1 << 29;
    public const FLAG_IGNORE_UNDEF = 1 << 30;

    public const FLAG_IGNORE_NULLABLE_AND_UNDEF = self::FLAG_IGNORE_UNDEF | self::FLAG_IGNORE_NULLABLE;

    public function __construct()
    {
    }

    /** @var associative-array<int,Closure(Node):void> maps values of ast\Node->kind to closures that can be used to generate annotations (on the ast\Node instance) for that node kind */
    private static $closures_for_kind;

    /**
     * Initialize the map of kinds to closures that add annotations to the corresponding node kind.
     * This is called when the class is loaded.
     */
    public static function init(): void
    {
        if (\is_array(self::$closures_for_kind)) {
            return;
        }
        self::initInner();
    }

    public const FLAGS_NODE_TYPE_SET = [
        ast\AST_VAR => true,
        ast\AST_DIM => true,
        ast\AST_PROP => true,
        ast\AST_STATIC_PROP => true,
        ast\AST_ASSIGN => true,
        ast\AST_ASSIGN_REF => true,
    ];

    /**
     * @param array<mixed,?(Node|string|float|int)> $children (should all be Nodes or null)
     * @param int $bit_set
     */
    private static function markArrayElements($children, int $bit_set): void
    {
        foreach ($children as $node) {
            if ($node instanceof Node) {
                $node->flags |= $bit_set;
            }
        }
    }

    /**
     * @param Node $node
     * @param int $bit_set the bits to add to the flags
     */
    private static function markNode(Node $node, int $bit_set): void
    {
        $kind = $node->kind;
        if (\array_key_exists($kind, self::FLAGS_NODE_TYPE_SET)) {
            $node->flags |= $bit_set;
        } elseif ($kind === ast\AST_ARRAY) {
            // flags and children are single-purpose right now
            self::markArrayElements($node->children, $bit_set);
        } else {
            $node->children[self::PHAN_NODE_FLAGS] = $bit_set;
        }
    }

    private static function initInner(): void
    {
        /**
         * @param Node $node
         */
        $binary_op_handler = static function (Node $node): void {
            if ($node->flags === flags\BINARY_COALESCE) {
                $inner_node = $node->children['left'];
                if ($inner_node instanceof Node) {
                    self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF);
                }
            }
        };
        /**
         * @param Node $node a node of kind ast\AST_ASSIGN_OP
         */
        $assign_op_handler = static function (Node $node): void {
            if ($node->flags === flags\BINARY_COALESCE) {
                $inner_node = $node->children['var'];
                if ($inner_node instanceof Node) {
                    self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF | self::FLAG_INITIALIZES);
                }
            }
        };
        /**
         * @param Node $node
         * @return void
         */
        $initializes_handler = static function (Node $node): void {
            $inner_node = $node->children['var'];
            if ($inner_node instanceof Node) {
                self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF | self::FLAG_INITIALIZES);
            }
        };
        /**
         * @param Node $node
         * @return void
         */
        $dim_handler = static function (Node $node): void {
            if ($node->flags & self::FLAG_IGNORE_NULLABLE_AND_UNDEF) {
                $inner_node = $node->children['expr'];
                if ($inner_node instanceof Node) {
                    self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF);
                }
            }
        };
        // also marks $expr the same way
        $prop_handler = $dim_handler;

        /**
         * @param Node $node
         * @return void
         */
        $ignore_nullable_and_undef_handler = static function (Node $node): void {
            $inner_node = $node->children['var'];
            if ($inner_node instanceof Node) {
                self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF);
            }
        };

        /**
         * @param Node $node
         * @return void
         */
        $ignore_nullable_and_undef_expr_handler = static function (Node $node): void {
            $inner_node = $node->children['expr'];
            if ($inner_node instanceof Node) {
                self::markNode($inner_node, self::FLAG_IGNORE_NULLABLE_AND_UNDEF);
            }
        };
        /**
         * @param Node $node
         * @return void
         */
        $ast_array_elem_handler = static function (Node $node): void {
            // Handle [$a1, $a2] = $array; - Don't warn about $node
            $bit = $node->flags & self::FLAG_IGNORE_UNDEF;
            if ($bit) {
                $inner_node = $node->children['value'];
                if (($inner_node instanceof Node)) {
                    self::markNode($inner_node, $bit);
                }
            }
        };

        self::$closures_for_kind = [
            ast\AST_BINARY_OP => $binary_op_handler,
            ast\AST_ASSIGN_OP => $assign_op_handler,
            ast\AST_DIM => $dim_handler,
            ast\AST_PROP => $prop_handler,
            ast\AST_EMPTY => $ignore_nullable_and_undef_expr_handler,
            ast\AST_ISSET => $ignore_nullable_and_undef_handler,
            ast\AST_UNSET => $ignore_nullable_and_undef_handler,
            ast\AST_ASSIGN => $initializes_handler,
            ast\AST_ASSIGN_REF => $initializes_handler,
            // Skip over AST_ARRAY
            ast\AST_ARRAY_ELEM => $ast_array_elem_handler,
        ];
    }

    /**
     * @param Node|array|int|string|float|bool|null $node
     */
    public static function applyFull($node): void
    {
        if ($node instanceof Node) {
            $closure = self::$closures_for_kind[$node->kind] ?? null;
            if (\is_object($closure)) {
                $closure($node);
            }
            foreach ($node->children as $inner) {
                self::applyFull($inner);
            }
        }
    }

    /** @internal */
    public const SCOPE_START_LIST = [
        ast\AST_CLASS,
        ast\AST_FUNC_DECL,
        ast\AST_CLOSURE,
        ast\AST_ARROW_FUNC,
    ];

    /**
     * @param Node|string|int|float|null $node
     */
    private static function applyToScopeInner($node): void
    {
        if ($node instanceof Node) {
            $kind = $node->kind;
            if (\in_array($kind, self::SCOPE_START_LIST, true)) {
                return;
            }

            $closure = self::$closures_for_kind[$kind] ?? null;
            if ($closure !== null) {
                $closure($node);
            }
            foreach ($node->children as $inner) {
                self::applyToScopeInner($inner);
            }
        }
    }

    /**
     * @param Node $node a node beginning a scope such as AST_FUNC, AST_STMT_LIST, AST_METHOD, etc. (Assumes these nodes don't have any annotations.
     */
    public static function applyToScope(Node $node): void
    {
        foreach ($node->children as $inner) {
            self::applyToScopeInner($inner);
        }
    }
}
PhanAnnotationAdder::init();
