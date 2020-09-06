<?php

declare(strict_types=1);

// Phan does a ton of GC and this offers a major speed
// improvement if your system can handle it (which it
// should be able to)
gc_disable();

// Check the environment to make sure Phan can run successfully
// @phan-file-suppress PhanPluginRemoveDebugEcho
require_once __DIR__ . '/requirements.php';
require_once __DIR__ . '/Phan/Bootstrap.php';

use Phan\CLI;
use Phan\Prep;

// Create our CLI interface and load arguments
$cli = CLI::fromArgv();

$file_list = $cli->getFileList();

// This is an example visitor. Do whatever you like here
// to scan all nodes.
$visit_node = static function (\ast\Node $node, string $file_path): void {

    // Take a look at Phan\AST\Visitor\Element to see all
    // of the kinds of nodes
    if ($node->kind === \ast\AST_CLASS_CONST) {
        // Debug::printNode($node);

        $name = $node->children['const'];
        if (\is_string($name)) {
            if (preg_match('/.*SEARCH.*/', $name)) {
                print "$file_path:{$node->lineno} $name\n";
            }
        }
    }
};

// Apply the closure to every single node in the
// code base
Prep::scanFileList($file_list, $visit_node);
