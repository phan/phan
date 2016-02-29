<?php declare(strict_types=1);

// Phan does a ton of GC and this offers a major speed
// improvment if your system can handle it (which it
// should be able to)
gc_disable();

// Check the environment to make sure Phan can run successfully
require_once(__DIR__ . '/requirements.php');
require_once(__DIR__ . '/Phan/Bootstrap.php');

use Phan\CLI;
use Phan\Config;
use Phan\Prep;

// Create our CLI interface and load arguments
$cli = new CLI();

$file_list = $cli->getFileList();

// This is an example visitor. Do whatever you like here
// to scan all nodes.
$visit_node = function(\ast\Node $node, string $file_path) {

    // Take a look at Phan\AST\Visitor\Element to see all
    // of the kinds of nodes
    if ($node->kind == \ast\AST_CONST) {

        if (is_string($node->children['name']->children['name'])) {
            $name = $node->children['name']->children['name'];
            if (preg_match('/.*AST.*/', $name)) {
                print "$file_path:{$node->lineno} $name\n";
            }
        }

    }

};

// Apply the closure to every single node in the
// code base
Prep::scanFileList($file_list, $visit_node);
