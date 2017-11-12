<?php declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

/**
 * For internal use, name may change
 *
 * Source: https://github.com/TysonAndre/tolerant-php-parser-to-php-ast
 * Uses Microsoft/tolerant-php-parser to create an instance of ast\Node.
 * Useful if the php-ast extension isn't actually installed.
 *
 * @author Tyson Andre
 */
class FilePositionMap
{
    /** @var string */
    private $file_contents;

    /** @var int */
    private $file_contents_length;

    /** @var int */
    private $current_offset;

    /** @var int (updated whenever current_offset is updated) */
    private $line_for_current_offset;

    /** @var int[] */
    private $offset_to_line_map = [];

    public function __construct(string $file_contents, Node $source_node)
    {
        $this->file_contents = $file_contents;
        $this->file_contents_length = \strlen($file_contents);
        $this->current_offset = 0;
        $this->line_for_current_offset = 1;
        $this->init($source_node);
    }

    private function init(Node $source_node)
    {
        $offsets_set = [];
        foreach ($source_node->getDescendantNodesAndTokens() as $child) {
            if ($child instanceof Node) {
                $start_offset = $child->getStart();
                $end_offset = $child->getEndPosition();
            } elseif ($child instanceof Token) {
                $start_offset = $child->start;
                $end_offset = $child->getEndPosition();
            } else {
                break;
            }
            $offsets_set[$start_offset] = true;
            // TODO: May be unnecessary, since the end of a token is the start of another token?
            // But maybe not for the leading padding
            $offsets_set[$end_offset] = true;
        }
        ksort($offsets_set);
        foreach ($offsets_set as $offset => $_) {
            $this->offset_to_line_map[$offset] = $this->computeLineNumberForOffset($offset);
        }
    }

    // TODO update if https://github.com/Microsoft/tolerant-php-parser/issues/166
    // Add an alias?
    public function getNodeStartLine(Node $node) : int
    {
        return $this->fetchLineNumberForOffset($node->getStart());
    }

    public function getTokenStartLine(Token $token) : int
    {
        return $this->fetchLineNumberForOffset($token->start);
    }

    /** @param Node|Token $node */
    public function getStartLine($node) : int
    {
        if ($node instanceof Token) {
            $offset = $node->start;
        } else {
            $offset = $node->getStart();
        }
        return $this->fetchLineNumberForOffset($offset);
    }

    /** @param Node|Token $node */
    public function getEndLine($node) : int
    {
        return $this->fetchLineNumberForOffset($node->getEndPosition());
    }

    public function fetchLineNumberForOffset(int $offset) : int
    {
        $line = $this->offset_to_line_map[$offset] ?? null;
        if ($line !== null) {
            return $line;
        }
        $line = $this->computeLineNumberForOffset($offset);
        $this->offset_to_line_map[$offset] = $line;
        return $line;
    }

    private function computeLineNumberForOffset(int $offset) : int
    {
        if ($offset < 0) {
            $offset = 0;
        } elseif ($offset >= $this->file_contents_length) {
            $offset = $this->file_contents_length;
        }
        $current_offset = $this->current_offset;
        if ($offset > $current_offset) {
            $this->line_for_current_offset += \substr_count($this->file_contents, "\n", $current_offset, $offset - $current_offset);
            $this->current_offset = $offset;
        } elseif ($offset < $this->current_offset) {
            $this->line_for_current_offset -= \substr_count($this->file_contents, "\n", $offset, $current_offset - $offset);
            $this->current_offset = $offset;
        }
        return $this->line_for_current_offset;
    }
}
