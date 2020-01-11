<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\IssueFixingPlugin;

use AssertionError;
use Microsoft\PhpParser;
use Microsoft\PhpParser\FilePositionMap;
use Microsoft\PhpParser\Parser;

/**
 * Represents file contents to be edited,
 * and utilities for working with the contents in fixers.
 *
 * @deprecated - should not be used directly. Just use FileCacheEntry.
 */
class FileContents
{
    /** @var string the raw file contents */
    private $contents;
    /** @var ?PhpParser\Node the raw node for the contents */
    private $ast;
    /** @var ?associative-array<int,list<PhpParser\Node>> the nodes at each line - computed lazily*/
    private $nodes_at_lines;

    /** @var ?FilePositionMap - computed lazily and shared by all fixers */
    private $file_position_map;

    /** @var ?non-empty-list<int> positions of each line (1-based) (computed lazily) */
    private $line_offset_map = null;

    /** @var ?associative-array<int,string> a 1-based array of lines */
    private $lines;

    /**
     * Create a representation of the file contents.
     *
     * Other data structures are instantiated when they are first fetched.
     * (different fixers would use different structures)
     */
    public function __construct(string $contents)
    {
        $this->contents = $contents;
    }

    /**
     * Gets the raw file contents
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * Gets the AST with all tokens (this assumes that the AST is valid)
     */
    public function getAST(): PhpParser\Node
    {
        return $this->ast ?? ($this->ast = (new Parser())->parseSourceFile($this->contents));
    }

    /**
     * Get the nodes which start at a specific line number
     * @return list<PhpParser\Node>
     */
    public function getNodesAtLine(int $line): array
    {
        $line_node_map = $this->nodes_at_lines ?? ($this->nodes_at_lines = $this->computeNodesAtLineMap());
        return $line_node_map[$line] ?? [];
    }

    /**
     * Compute a map from lines to the nodes at the line.
     *
     * This is efficient if called multiple times, but less efficient(e.g. uses more memory) if only called once.
     * @return associative-array<int,list<PhpParser\Node>>
     */
    public function computeNodesAtLineMap(): array
    {
        $result = [];
        $file_position_map = new FilePositionMap($this->contents);
        foreach ($this->getAST()->getDescendantNodes() as $node) {
            $line_for_node = $file_position_map->getStartLine($node);
            $result[$line_for_node][] = $node;
        }
        return $result;
    }

    /**
     * Fetches the shared file position map
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getFilePositionMap(): FilePositionMap
    {
        return $this->file_position_map ?? ($this->file_position_map = new FilePositionMap($this->contents));
    }

    /**
     * @return ?int the byte offset of the start of the given line (1-based)
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getLineOffset(int $line): ?int
    {
        if ($this->line_offset_map === null) {
            $this->line_offset_map = self::computeLineOffsetMap($this->contents);
        }
        return $this->line_offset_map[$line] ?? null;
    }

    /**
     * Returns a mapping from the 1-based line number to the byte offset of the start of each line
     * @internal
     * @return non-empty-list<int>
     */
    public static function computeLineOffsetMap(string $contents): array
    {
        // start of line 1 is the 0th byte
        $offsets = [0, 0];
        $offset = 0;
        while (($next = \strpos($contents, "\n", $offset)) !== false) {
            $offset = $next + 1;
            $offsets[] = $offset;
        }
        $offsets[] = \strlen($contents);
        return $offsets;
    }

    /**
     * @return associative-array<int,string> a 1-based array of lines
     */
    public function getLines(): array
    {
        if (\is_array($this->lines)) {
            return $this->lines;
        }
        $lines = \preg_split("/^/m", $this->contents);
        // TODO: Use a better way to not include false when arguments are both valid
        if (!\is_array($lines)) {
            throw new AssertionError("Expected lines to be an array");
        }
        unset($lines[0]);
        $this->lines = $lines;
        return $lines;
    }


    /**
     * Helper method to get individual lines from a file.
     * This is more efficient than using \SplFileObject if multiple lines may need to be fetched.
     *
     * @param int $lineno - A line number, starting with line 1
     */
    public function getLine(int $lineno): ?string
    {
        $lines = $this->getLines();
        return $lines[$lineno] ?? null;
    }
}
