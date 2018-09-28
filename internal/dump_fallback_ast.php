<?php declare(strict_types=1);
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2017 Tyson Andre
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

if (file_exists(__DIR__ . "/../../../../vendor/autoload.php")) {
    // @phan-suppress-next-line PhanMissingRequireFile this depends on where Phan is installed
    require __DIR__ . "/../../../../vendor/autoload.php";
} else {
    // @phan-suppress-next-line PhanMissingRequireFile this depends on where Phan is installed
    require __DIR__ . "/../vendor/autoload.php";
}
use Microsoft\PhpParser\Parser;

dump_main();

// Dumps a snippet provided on stdin.
function dump_main()
{
    error_reporting(E_ALL);
    global $argv;

    $as_php_ast = ($argv[1] ?? null) === '--php-ast';
    if ($as_php_ast) {
        unset($argv[1]);
        $argv = array_values($argv);
    }

    if (count($argv) !== 2) {
        $help = <<<"EOB"
Usage: php [--php-ast] {$argv[0]} 'snippet'
E.g.
  {$argv[0]} '2+2;'
  {$argv[0]} '<?php function test() {}'
  {$argv[0]} "$(cat 'path/to/file.php')"

EOB;
        echo $help;
        exit(1);
    }
    $expr = $argv[1];
    if (!is_string($expr)) {
        throw new AssertionError("missing 2nd argument");
    }

    // Guess if this is a snippet or file contents
    if (($expr[0] ?? '') !== '<') {
        $expr = '<' . '?php ' . $expr;
    }

    if ($as_php_ast) {
        dump_expr_as_ast($expr);
    } else {
        dump_expr($expr);
    }
}

function dump_expr_as_ast(string $expr)
{
    $ast_data = (new \Phan\AST\TolerantASTConverter\TolerantASTConverter())->parseCodeAsPHPAST($expr, 50);
    echo \Phan\Debug::nodeToString($ast_data);
}
function dump_expr(string $expr)
{
    // Instantiate new parser instance
    $parser = new Parser();
    // Return and print an AST from string contents
    $ast_node = $parser->parseSourceFile($expr);
    foreach ($ast_node->getDescendantNodes() as $descendant) {
        // echo "unsetting " . get_class($descendant) . $descendant->getStart() . "\n";
        unset($descendant->parent);
    }

    $ast_node->parent = null;
    unset($ast_node->statementList[0]);
    $dumper = new \Phan\AST\TolerantASTConverter\NodeDumper($expr);
    $dumper->setIncludeTokenKind(true);
    $dumper->dumpTree($ast_node);
    echo "\n";
    // var_export($ast_node->statementList);
}
