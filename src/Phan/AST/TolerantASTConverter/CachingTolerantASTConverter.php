<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser\Diagnostic;
use Microsoft\PhpParser\Node\SourceFileNode;

/**
 * This is a plain ast\Node generator that adds caching of the PhpParser\Nodes.
 */
final class CachingTolerantASTConverter extends TolerantASTConverter
{
    const MAX_CACHE_SIZE = 10;

    /**
     * @var array<string,PhpParserNodeEntry> - an LRU cache of nodes used to generate php-ast nodes.
     * The same PhpParser\Node can be used to create ast\Node instances in multiple ways (e.g. should_add_placeholders changes what gets generated)
     */
    private static $php_parser_node_cache = [];

    /**
     * @param Diagnostic[] &$errors @phan-output-reference (TODO: param-out)
     * @override
     */
    public static function phpParserParse(string $file_contents, array &$errors = []): SourceFileNode
    {
        $entry = self::$php_parser_node_cache[$file_contents] ?? null;
        if ($entry) {
            // This was recently used, move the entry to the end of the associative array.
            unset(self::$php_parser_node_cache[$file_contents]);
            self::$php_parser_node_cache[$file_contents] = $entry;
            $errors = $entry->errors;
            // \fwrite(\STDERR, "Found a cached entry for " . \md5($file_contents) . ' #errors=' . \count($errors) . "\n");
            return $entry->node;
        }
        $new_errors = [];
        $node = parent::phpParserParse($file_contents, $new_errors);
        if (\count(self::$php_parser_node_cache) >= self::MAX_CACHE_SIZE) {
            // The cache is full, evict the element at the beginning of the associative array.
            \reset(self::$php_parser_node_cache);
            unset(self::$php_parser_node_cache[\key(self::$php_parser_node_cache)]);
        }
        $entry = new PhpParserNodeEntry($node, $new_errors);
        self::$php_parser_node_cache[$file_contents] = $entry;

        $errors = $new_errors;
        // \fwrite(\STDERR, "Creating entry for " . \md5($file_contents) . ' #errors=' . \count($errors) . "\n");
        return $node;
    }
}
