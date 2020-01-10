<?php

declare(strict_types=1);

namespace Phan;

use ast\Node;

/**
 * A utility that can be used to scan a list of files and apply a closure to every node.
 *
 * This is not invoked by ./phan
 */
class Prep
{

    /**
     * Scan a list of files, applying the given closure to every
     * AST node
     *
     * @param list<string> $file_list
     * A list of files to scan
     *
     * @param \Closure $visit_node
     * A closure that is to be applied to every AST node
     */
    public static function scanFileList(
        array $file_list,
        \Closure $visit_node
    ): void {
        foreach ($file_list as $file_path) {
            // Convert the file to an Abstract Syntax Tree
            // before passing it on to the recursive version
            // of this method.
            $node = \ast\parse_file(
                $file_path,
                Config::AST_VERSION
            );

            self::scanNodeInFile($node, $file_path, $visit_node);
        }
    }

    /**
     * Recursively scan a node and its children applying the
     * given closure to each.
     *
     * @param string $file_path
     * The file in which the node exists
     *
     * @param \Closure $visit_node
     * A closure that is to be applied to every AST node
     */
    public static function scanNodeInFile(
        Node $node,
        string $file_path,
        \Closure $visit_node
    ): void {
        // Visit the node doing whatever the caller
        // likes
        $visit_node($node, $file_path);

        // Scan all children recursively
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                continue;
            }

            self::scanNodeInFile($child_node, $file_path, $visit_node);
        }
    }
}
