<?php

declare(strict_types=1);

namespace Phan\Tests\AST\TolerantASTConverter;

use AssertionError;
use ast;
use Phan\AST\TolerantASTConverter\NodeDumper;
use Phan\AST\TolerantASTConverter\Shim;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\Config;
use Phan\Debug;
use Phan\Tests\TestBase;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function count;
use function get_class;
use function in_array;
use function is_int;
use function is_string;

Shim::load();

/**
 * Tests that the polyfill works with valid ASTs
 */
final class ConversionTest extends TestBase
{
    /**
     * @return list<string>
     * @suppress PhanPluginUnknownObjectMethodCall
     */
    protected static function scanSourceDirForPHP(string $source_dir): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir)) as $file_path => $file_info) {
            $filename = $file_info->getFilename();
            if ($filename &&
                !in_array($filename, ['.', '..'], true) &&
                \substr($filename, 0, 1) !== '.' &&
                \strpos($filename, '.') !== false &&
                \pathinfo($filename)['extension'] === 'php') {
                $files[] = $file_path;
            }
        }
        if (count($files) === 0) {
            throw new \InvalidArgumentException(\sprintf("RecursiveDirectoryIterator iteration returned no files for %s\n", $source_dir));
        }
        return $files;
    }

    /**
     * @return bool does php-ast support $ast_version
     */
    public static function hasNativeASTSupport(int $ast_version): bool
    {
        try {
            ast\parse_code('', $ast_version);
            return true;
        } catch (\LogicException) {
            return false;
        }
    }

    /**
     * This is used to sort by token count, so that the failures with the fewest token
     * (i.e. simplest ASTs) appear first.
     * @param string[] $files
     */
    private static function sortByTokenCount(array &$files): void
    {
        $token_counts = [];
        foreach ($files as $file) {
            $contents = \file_get_contents($file);
            if (!is_string($contents)) {
                throw new AssertionError("Failed to read $file");
            }
            $token_counts[$file] = count(PhpToken::tokenize($contents));
        }
        \usort($files, static function (string $path1, string $path2) use ($token_counts): int {
            return $token_counts[$path1] <=> $token_counts[$path2];
        });
    }

    /**
     * Asserts that valid files get parsed the same way by php-ast and the polyfill.
     *
     * @return array{0:string,1:int}[] array of [string $file_path, int $ast_version]
     */
    public static function astValidFileExampleProvider(): array
    {
        $tests = [];
        // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
        $source_dir = \dirname(\realpath(__DIR__), 3) . '/misc/fallback_ast_src';
        $paths = self::scanSourceDirForPHP($source_dir);

        self::sortByTokenCount($paths);
        $supports80 = self::hasNativeASTSupport(Config::AST_VERSION);
        if (!$supports80) {
            throw new RuntimeException(\sprintf("Version %d is not natively supported", Config::AST_VERSION));
        }
        foreach ($paths as $path) {
            $normalized_path = \str_replace('\\', '/', $path);
            if (\PHP_VERSION_ID < 80200 && \str_contains($normalized_path, '/php82_or_newer/')) {
                continue;
            }
            if (\PHP_VERSION_ID < 80300 && \str_contains($normalized_path, '/php83_or_newer/')) {
                continue;
            }
            if (\PHP_VERSION_ID < 80400 && \str_contains($normalized_path, '/php84_or_newer/')) {
                continue;
            }
            if (\PHP_VERSION_ID < 80500 && \str_contains($normalized_path, '/php85_or_newer/')) {
                continue;
            }
            if (\str_contains($normalized_path, '/php84_or_newer/property_hooks.php') && !self::supportsPropertyHooks()) {
                continue;
            }
            if (\str_contains($normalized_path, '/php85_or_newer/final_property_promotion.php') && !self::supportsFinalPropertyPromotion()) {
                continue;
            }
            if (\str_contains($normalized_path, '/php85_or_newer/pipe_operator.php') && !self::supportsPipeOperator()) {
                continue;
            }
            if (\str_contains($normalized_path, '/php85_or_newer/override_property.php') && !self::supportsPropertyOverrideAttribute()) {
                continue;
            }
            if (\PHP_VERSION_ID >= 80400) {
                foreach ([
                    '/misc/fallback_ast_src/exit.php',
                    '/misc/fallback_ast_src/php-src_tests/bug60634_error_3.php',
                ] as $skip_path) {
                    if (\str_ends_with($normalized_path, $skip_path)) {
                        continue 2;
                    }
                }
            }
            $tests[] = [$path, Config::AST_VERSION];
        }
        return $tests;
    }

    private static function supportsPropertyHooks(): bool
    {
        if (!self::$hasCheckedPropertyHooks) {
            self::$supportsPropertyHooks = self::probePropertyHooks();
            self::$hasCheckedPropertyHooks = true;
        }
        return self::$supportsPropertyHooks;
    }

    private static function supportsFinalPropertyPromotion(): bool
    {
        if (!self::$hasCheckedFinalPropertyPromotion) {
            self::$supportsFinalPropertyPromotion = self::probeFinalPropertyPromotion();
            self::$hasCheckedFinalPropertyPromotion = true;
        }
        return self::$supportsFinalPropertyPromotion;
    }

    private static function supportsPipeOperator(): bool
    {
        if (!self::$hasCheckedPipeOperator) {
            self::$supportsPipeOperator = self::probePipeOperator();
            self::$hasCheckedPipeOperator = true;
        }
        return self::$supportsPipeOperator;
    }

    private static function supportsPropertyOverrideAttribute(): bool
    {
        if (!self::$hasCheckedPropertyOverrideAttribute) {
            self::$supportsPropertyOverrideAttribute = self::probePropertyOverrideAttribute();
            self::$hasCheckedPropertyOverrideAttribute = true;
        }
        return self::$supportsPropertyOverrideAttribute;
    }

    private static function probePropertyHooks(): bool
    {
        $code = <<<'PHP'
<?php
class PropertyHookCheck {
    private int $counter = 0;
    public int $value {
        get => $this->counter;
        set(int $value) {
            $this->counter = $value;
        }
    }
}
PHP;
        try {
            ast\parse_code($code, Config::AST_VERSION);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probeFinalPropertyPromotion(): bool
    {
        $code = <<<'PHP'
<?php
class FinalPromotionCheck {
    public function __construct(public final string $value) {}
}
PHP;
        try {
            ast\parse_code($code, Config::AST_VERSION);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probePipeOperator(): bool
    {
        $code = <<<'PHP'
<?php
$result = "example" |> strlen(...);
PHP;
        try {
            ast\parse_code($code, Config::AST_VERSION);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probePropertyOverrideAttribute(): bool
    {
        $code = <<<'PHP'
<?php
class OverrideBase {
    protected string $value = 'base';
}
class OverrideChild extends OverrideBase {
    #[Override]
    protected string $value = 'child';
}
PHP;
        try {
            ast\parse_code($code, Config::AST_VERSION);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    private static function normalizeOriginalAST(\ast\Node|float|int|null|string|array $node): void
    {
        if ($node instanceof ast\Node) {
            $kind = $node->kind;
            if ($kind === ast\AST_FUNC_DECL || $kind === ast\AST_METHOD) {
                // https://github.com/nikic/php-ast/issues/64
                $node->flags &= ~(0x800000);
            }
            if ($kind === ast\AST_CLASS && !\array_key_exists('type', $node->children)) {
                $declId = $node->children['__declId'];
                $node->children['type'] = null;
                unset($node->children['__declId']);
                $node->children['__declId'] = $declId;
            }
            // Normalize clone nodes: AST version 120+ represents clone as AST_CALL
            // Convert to AST_CLONE for consistency with our normalization
            if ($kind === ast\AST_CALL && self::isCloneCall($node)) {
                self::convertCallToClone($node);
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

    /**
     * Check if an AST_CALL node represents a clone operation.
     */
    private static function isCloneCall(ast\Node $node): bool
    {
        $expr = $node->children['expr'] ?? null;
        if (!($expr instanceof ast\Node) || $expr->kind !== ast\AST_NAME) {
            return false;
        }
        return ($expr->children['name'] ?? null) === 'clone';
    }

    /**
     * Convert an AST_CALL node representing 'clone' to an AST_CLONE node.
     */
    private static function convertCallToClone(ast\Node &$node): void
    {
        $args = $node->children['args'] ?? null;
        $expr = null;

        if ($args instanceof ast\Node && isset($args->children[0])) {
            $expr = $args->children[0];
        }

        // Modify the node in-place to convert from AST_CALL to AST_CLONE
        $node->kind = ast\AST_CLONE;
        $node->children = ['expr' => $expr];
    }

    // TODO: TolerantPHPParser gets more information than PHP-Parser for statement lists,
    // so this step may be unnecessary
    /**
     * Set all of the line numbers to constants,
     * so that minor differences in line numbers won't cause tests to fail.
     */
    /*
    public static function normalizeLineNumbers(ast\Node $node): ast\Node
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
     */

    /**
     * A list of ast\Node kinds that declare functions
     */
    private const FUNCTION_DECLARATION_KINDS = [
        ast\AST_FUNC_DECL,
        ast\AST_METHOD,
        ast\AST_CLOSURE,
        ast\AST_ARROW_FUNC,
    ];

    /** Cached property hook support result @var bool */
    private static bool $supportsPropertyHooks = false;
    /** Cached final promotion support result @var bool */
    private static bool $supportsFinalPropertyPromotion = false;
    /** Cached pipe operator support result @var bool */
    private static bool $supportsPipeOperator = false;
    /** Cached property override attribute support result @var bool */
    private static bool $supportsPropertyOverrideAttribute = false;
    /** Whether property hook support was checked @var bool */
    private static bool $hasCheckedPropertyHooks = false;
    /** Whether final promotion support was checked @var bool */
    private static bool $hasCheckedFinalPropertyPromotion = false;
    /** Whether pipe operator support was checked @var bool */
    private static bool $hasCheckedPipeOperator = false;
    /** Whether property override attribute support was checked @var bool */
    private static bool $hasCheckedPropertyOverrideAttribute = false;

    /**
     * Normalizes the flags on function declaration caused by \ast\flags\FUNC_GENERATOR.
     *
     * Historically, Phan did not use these flags because they were not natively provided in all PHP versions.
     * TODO: They should be available now
     * @suppress PhanUndeclaredProperty
     */
    public static function normalizeNodeFlags(ast\Node $node): void
    {
        $kind = $node->kind;
        if (\in_array($kind, self::FUNCTION_DECLARATION_KINDS, true)) {
            // Alternately, could make Phan do this.
            $node->flags &= ~ast\flags\FUNC_GENERATOR;
        }
        if ($kind === ast\AST_ATTRIBUTE_GROUP) {
            // @phan-suppress-next-line PhanTypeObjectUnsetDeclaredProperty this is deliberately added by the polyfill.
            unset($node->endLineno);
        }
        unset($node->polyfill_has_trailing_comma);
        unset($node->is_deprecated_encaps_var);

        foreach ($node->children as $v) {
            if ($v instanceof ast\Node) {
                self::normalizeNodeFlags($v);
            }
        }
    }

    /** @dataProvider astValidFileExampleProvider */
    public function testFallbackFromParser(string $file_name, int $ast_version): void
    {
        $test_folder_name = \basename(\dirname($file_name));
        if (\PHP_VERSION_ID < 80200 && $test_folder_name === 'php82_or_newer') {
            $this->markTestIncomplete('php-ast cannot parse php8.2 syntax when running in php8.1 or older');
        }
        if (\PHP_VERSION_ID >= 80400) {
            $tests_to_skip = [
                'misc/fallback_ast_src/exit.php',
                'misc/fallback_ast_src/php-src_tests/bug60634_error_3.php',
            ];
            foreach ($tests_to_skip as $test_to_skip) {
                if (str_ends_with($file_name, $test_to_skip)) {
                    $this->markTestIncomplete('exit was changed to function since php8.4, and microsoft/tolerant-php-parser (fallback) cannot parse it correctly yet');
                }
            }
        }
        $contents = \file_get_contents($file_name);
        if ($contents === false) {
            $this->fail("Failed to read $file_name");
        }
        try {
            $ast = @ast\parse_code($contents, $ast_version, $file_name);
        } catch (\ParseError $e) {
            $this->fail("Failed for $file_name:{$e->getLine()}: {$e->getMessage()}");
            return;  // @phan-suppress-current-line PhanPluginUnreachableCode TODO Fix
        }
        self::normalizeOriginalAST($ast);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples must be syntactically valid PHP parsable by php-ast');
        $converter = new TolerantASTConverter();
        $converter->setPHPVersionId(\PHP_VERSION_ID);
        try {
            $fallback_ast = $converter->parseCodeAsPHPAST($contents, $ast_version);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            throw new \RuntimeException("Error parsing $file_name with ast version $ast_version", is_int($code) ? $code : 1, $e);
        }
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');

        /*
        if ($test_folder_name === 'phan_test_files' || $test_folder_name === 'php-src_tests') {
            $fallback_ast = self::normalizeLineNumbers($fallback_ast);
            $ast          = self::normalizeLineNumbers($ast);
        }
         */
        self::normalizeNodeFlags($ast);
        self::normalizeNodeFlags($fallback_ast);
        // TODO: Remove $ast->parent recursively
        $fallback_ast_repr = \var_export($fallback_ast, true);
        $original_ast_repr = \var_export($ast, true);

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
