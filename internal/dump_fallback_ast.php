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

// @phan-file-suppress PhanMissingRequireFile this depends on where Phan is installed
if (file_exists(__DIR__ . "/../../../../vendor/autoload.php")) {
    require __DIR__ . "/../../../../vendor/autoload.php";
} else {
    require __DIR__ . "/../vendor/autoload.php";
}
use Microsoft\PhpParser\Parser;

dump_main();

/**
 * Dumps a snippet provided as a command line argument
 * @return void
 */
function dump_main()
{
    error_reporting(E_ALL);
    global $argv;

    $as_php_ast = false;
    $as_php_ast_with_placeholders = false;
    foreach ($argv as $i => $arg) {
        if ($arg === '--php-ast') {
            $as_php_ast = true;
            unset($argv[$i]);
        } elseif ($arg === '--php-ast-with-placeholders') {
            $as_php_ast = true;
            $as_php_ast_with_placeholders = true;
            unset($argv[$i]);
        }
    }
    $argv = array_values($argv);

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
        dump_expr_as_ast($expr, $as_php_ast_with_placeholders);
    } else {
        dump_expr($expr);
    }
}

/**
 * Parses $expr and echoes the compact AST representation to stdout.
 * @return void
 */
function dump_expr_as_ast(string $expr, bool $with_placeholders)
{
    $converter = new \Phan\AST\TolerantASTConverter\TolerantASTConverter();
    $converter->setShouldAddPlaceholders($with_placeholders);
    $ast_data = $converter->parseCodeAsPHPAST($expr, 50);
    echo \Phan\Debug::nodeToString($ast_data);
}

/**
 * Parses $expr and echoes the tolerant-php-parser AST to stdout.
 * @return void
 */
function dump_expr(string $expr)
{
    // Instantiate new parser instance
    $parser = new Parser();
    // Return and print an AST from string contents
    $ast_node = $parser->parseSourceFile($expr);
    foreach ($ast_node->getDescendantNodes() as $descendant) {
        // echo "unsetting " . get_class($descendant) . $descendant->getStart() . "\n";
        $descendant->parent = null;
    }

    $ast_node->parent = null;
    unset($ast_node->statementList[0]);
    $dumper = new \Phan\AST\TolerantASTConverter\NodeDumper($expr);
    $dumper->setIncludeTokenKind(true);
    $dumper->dumpTree($ast_node);
    echo "\n";
    // var_export($ast_node->statementList);
}
