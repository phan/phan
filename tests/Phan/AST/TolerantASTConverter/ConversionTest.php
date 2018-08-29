<?php declare(strict_types = 1);

namespace Phan\Tests\AST\TolerantASTConverter;

use Phan\Tests\BaseTest;

use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\AST\TolerantASTConverter\NodeDumper;
use Phan\Debug;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use ast;

/**
 * Tests that the polyfill works with valid ASTs
 *
 * @phan-file-suppress PhanThrowTypeAbsent it's a test
 */
final class ConversionTest extends BaseTest
{
    /**
     * @return array<int,string>
     */
    protected function scanSourceDirForPHP(string $source_dir) : array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir)) as $file_path => $file_info) {
            $filename = $file_info->getFilename();
            if ($filename &&
                !in_array($filename, ['.', '..'], true) &&
                substr($filename, 0, 1) !== '.' &&
                strpos($filename, '.') !== false &&
                pathinfo($filename)['extension'] === 'php') {
                $files[] = $file_path;
            }
        }
        if (count($files) === 0) {
            throw new \InvalidArgumentException(sprintf("RecursiveDirectoryIterator iteration returned no files for %s\n", $source_dir));
        }
        return $files;
    }

    /**
     * @return bool
     */
    public static function hasNativeASTSupport(int $ast_version)
    {
        try {
            ast\parse_code('', $ast_version);
            return true;
        } catch (\LogicException $_) {
            return false;
        }
    }

    /**
     * This is used to sort by token count, so that the failures with the fewest token
     * (i.e. simplest ASTs) appear first.
     * @param string[] $files
     * @return void
     */
    private static function sortByTokenCount(array &$files)
    {
        $token_counts = [];
        foreach ($files as $file) {
            $token_counts[$file] = count(token_get_all(file_get_contents($file)));
        }
        usort($files, function (string $path1, string $path2) use ($token_counts) : int {
            return $token_counts[$path1] <=> $token_counts[$path2];
        });
    }

    /**
     * Asserts that valid files get parsed the same way by php-ast and the polyfill.
     *
     * @return array{0:string,1:int}[] array of [string $file_path, int $ast_version]
     */
    public function astValidFileExampleProvider()
    {
        $tests = [];
        $source_dir = dirname(dirname(dirname(realpath(__DIR__)))) . '/misc/fallback_ast_src';
        $paths = $this->scanSourceDirForPHP($source_dir);

        self::sortByTokenCount($paths);
        $supports50 = self::hasNativeASTSupport(50);
        if (!$supports50) {
            throw new RuntimeException("Version 50 is not natively supported");
        }
        foreach ($paths as $path) {
            $tests[] = [$path, 50];
        }
        return $tests;
    }

    /** @return void */
    private static function normalizeOriginalAST($node)
    {
        if ($node instanceof ast\Node) {
            $kind = $node->kind;
            if ($kind === ast\AST_FUNC_DECL || $kind === ast\AST_METHOD) {
                // https://github.com/nikic/php-ast/issues/64
                $node->flags &= ~(0x800000);
            }
            foreach ($node->children as $c) {
                self::normalizeOriginalAST($c);
            }
            return;
        } elseif (\is_array($node)) {
            foreach ($node as $c) {
                self::normalizeOriginalAST($c);
            }
        }
    }

    // TODO: TolerantPHPParser gets more information than PHP-Parser for statement lists,
    // so this step may be unnecessary
    public static function normalizeLineNumbers(ast\Node $node) : ast\Node
    {
        $node = clone($node);
        if (is_array($node->children)) {
            foreach ($node->children as $k => $v) {
                if ($v instanceof ast\Node) {
                    $node->children[$k] = self::normalizeLineNumbers($v);
                }
            }
        }
        $node->lineno = 1;
        return $node;
    }

    /** @dataProvider astValidFileExampleProvider */
    public function testFallbackFromParser(string $file_name, int $ast_version)
    {
        $test_folder_name = basename(dirname($file_name));
        if (PHP_VERSION_ID < 70100 && $test_folder_name === 'php71_or_newer') {
            $this->markTestIncomplete('php-ast cannot parse php7.1 syntax when running in php7.0');
        }
        $contents = file_get_contents($file_name);
        if ($contents === false) {
            $this->fail("Failed to read $file_name");
        }
        $ast = ast\parse_code($contents, $ast_version, $file_name);
        self::normalizeOriginalAST($ast);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parseable by php-ast');
        $converter = new TolerantASTConverter();
        $converter->setPHPVersionId(PHP_VERSION_ID);
        try {
            $fallback_ast = $converter->parseCodeAsPHPAST($contents, $ast_version);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error parsing $file_name with ast version $ast_version", $e->getCode(), $e);
        }
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');

        if ($test_folder_name === 'phan_test_files' || $test_folder_name === 'php-src_tests') {
            $fallback_ast = self::normalizeLineNumbers($fallback_ast);
            $ast          = self::normalizeLineNumbers($ast);
        }
        // TODO: Remove $ast->parent recursively
        $fallback_ast_repr = var_export($fallback_ast, true);
        $original_ast_repr = var_export($ast, true);

        if ($fallback_ast_repr !== $original_ast_repr) {
            $node_dumper = new NodeDumper($contents);
            $node_dumper->setIncludeTokenKind(true);
            $node_dumper->setIncludeOffset(true);
            $php_parser_node = $converter->phpparserParse($contents);
            try {
                $dump = $node_dumper->dumpTreeAsString($php_parser_node);
            } catch (\Throwable $e) {
                $dump = 'could not dump PhpParser Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            $original_ast_dump = Debug::nodeToString($ast);
            try {
                $fallback_ast_dump = Debug::nodeToString($fallback_ast);
            } catch (\Throwable $e) {
                $fallback_ast_dump = 'could not dump php-ast Node: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
            // $parser_export = var_dump($php_parser_node, true);
            $this->assertSame($original_ast_repr, $fallback_ast_repr, <<<EOT
The fallback must return the same tree of php-ast nodes
File: $file_name
Code:
$contents

Original AST:
$original_ast_dump

Fallback AST:
$fallback_ast_dump
PHP-Parser(simplified):
$dump
EOT

            /*
PHP-Parser(unsimplified):
$parser_export
             */);
        }
    }
}
