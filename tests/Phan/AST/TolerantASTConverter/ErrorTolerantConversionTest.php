<?php

declare(strict_types=1);

namespace Phan\Tests\AST\TolerantASTConverter;

use ast;
use Phan\AST\TolerantASTConverter\NodeDumper;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\Config;
use Phan\Debug;
use Phan\Tests\BaseTest;

/**
 * Various tests of the error-tolerant conversion mode of TolerantASTConverter
 */
final class ErrorTolerantConversionTest extends BaseTest
{
    public function testIncompleteVar(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $a = $
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {

}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents);
    }

    public function testIncompleteVarWithPlaceholderShort(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
$a = $
EOT;
        $valid_contents = <<<'EOT'
<?php
$a = $__INCOMPLETE_VARIABLE__;
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteVarWithPlaceholder(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $a = $
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $a = $__INCOMPLETE_VARIABLE__;
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteProperty(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $c;

}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents);
    }

    public function testIncompletePropertyWithPlaceholder(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $c;
  $a = $b->__INCOMPLETE_PROPERTY__;
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteMethod(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents);
    }

    public function testIncompleteMethodWithPlaceholder(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b;
  $a = Bar::__INCOMPLETE_CLASS_CONST__;
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testMiscNoise(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $b;
  |
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents);
    }

    public function testMiscNoiseWithPlaceholders(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  $b;
  |
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b;

}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteArithmeticWithPlaceholders(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  ($b * $c) +
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b * $c + \__INCOMPLETE_EXPR__;
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteArithmeticWithoutPlaceholders(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
  ($b * $c) +
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
  $b * $c;
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testMissingMember(): void
    {
        // in 0.0.17, this starts trying to parse a typed property declaration
        // and discards all of it because it's invalid.
        $incomplete_contents = <<<'EOT'
<?php
class Test {
    public notAFunction() {}
    public function aFunction() {}
}
EOT;
        // This doesn't make sense, but it's a valid AST anyway.
        // I doubt that this will ever be a common mistake
        $valid_contents = <<<'EOT'
<?php
class Test {
}
function aFunction() {}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testIncompleteTypedProperty(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
class Test {
    // Starting to input a typed property
    public int
    public function aFunction() {}
}
EOT;
        $valid_contents = <<<'EOT'
<?php
class Test {
    // Starting to input a typed property

    public function aFunction() {}
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testEmptyConstList(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
class Test { const X = ; }
EOT;
        $valid_contents = <<<'EOT'
<?php
class Test { }
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testEmptyThrow(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
throw
EOT;
        $valid_contents = <<<'EOT'
<?php

EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testEmptyMatch(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped("Requires php 8.0+");
        }
        $incomplete_contents = <<<'EOT'
<?php
match {
;
foo();
EOT;
        $valid_contents = <<<'EOT'
<?php


foo();
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    /**
     * Should not crash
     */
    public function testEmptyMatchArm(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped("Requires php 8.0+");
        }
        $incomplete_contents = <<<'EOT'
<?php
match (1+) {
    'a' => 'b',
    =>,
    1=>2,
}
EOT;
        $valid_contents = <<<'EOT'
<?php
match(1) {
    'a' => 'b',
};

1;2;
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

    public function testMissingSemicolon(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
function foo() {
    $y = 3
    $x = intdiv(3, 2);
}
EOT;
        $valid_contents = <<<'EOT'
<?php
function foo() {
    $y = 3;
    $x = intdiv(3, 2);
}
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents);
    }

    public function testIncompleteMethodCallBeforeIfWithPlaceholders(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
$obj->
if (true) {
    $y;
}
$obj->
if (true) {
    echo "example";
}
EOT;
        $valid_contents = <<<'EOT'
<?php
$obj->
if (true){
    $y
};
$obj->
if (true);
echo "example";
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, true);
    }

    public function testIncompleteMethodCallBeforeIfWithoutPlaceholders(): void
    {
        $incomplete_contents = <<<'EOT'
<?php
$obj->
if (true) {
    foo();
}
$obj->
if (true) {
    echo "example";
}
EOT;
        $valid_contents = <<<'EOT'
<?php
$obj->
if (true){
    foo()
};
$obj->
if (true);
echo "example";
EOT;
        $this->runTestFallbackFromParser($incomplete_contents, $valid_contents, false);
    }

// Another test (Won't work with php-parser, might work with tolerant-php-parser
/**
        $incomplete_contents = <<<'EOT'
<?php
class C{
    public function foo() {
        $x = 3;


    public function bar() {
    }
}
EOT;
        $valid_contents = <<<'EOT'
<?php
class C{
    public function foo() {
        $x = 3;
    }

    public function bar() {
    }
}
EOT;
 */

    private static function normalizePolyfillAST(ast\Node $ast): void
    {
        switch ($ast->kind) {
            case ast\AST_DIM:
                if (\PHP_VERSION_ID < 70400) {
                    $ast->flags = 0;
                }
                break;
            case ast\AST_CLASS:
                if (Config::AST_VERSION < 85) {
                    unset($ast->children['type']);
                }
                break;
        }
        foreach ($ast->children as $c) {
            if ($c instanceof ast\Node) {
                self::normalizePolyfillAST($c);
            }
        }
    }

    private function runTestFallbackFromParser(string $incomplete_contents, string $valid_contents, bool $should_add_placeholders = false): void
    {
        $supports80 = ConversionTest::hasNativeASTSupport(Config::AST_VERSION);
        if (!$supports80) {
            $this->fail('No supported AST versions to test');
        }
        $this->runTestFallbackFromParserForASTVersion($incomplete_contents, $valid_contents, Config::AST_VERSION, $should_add_placeholders);
    }

    private function runTestFallbackFromParserForASTVersion(string $incomplete_contents, string $valid_contents, int $ast_version, bool $should_add_placeholders): void
    {
        $ast = \ast\parse_code($valid_contents, $ast_version);
        $this->assertInstanceOf('\ast\Node', $ast, 'Examples(for validContents) must be syntactically valid PHP parsable by php-ast');
        $errors = [];
        $converter = new TolerantASTConverter();
        $converter->setShouldAddPlaceholders($should_add_placeholders);
        $php_parser_node = $converter->phpParserParse($incomplete_contents, $errors);
        $fallback_ast = $converter->phpParserToPhpAst($php_parser_node, $ast_version, $incomplete_contents);
        self::normalizePolyfillAST($fallback_ast);
        $this->assertInstanceOf('\ast\Node', $fallback_ast, 'The fallback must also return a tree of php-ast nodes');
        $fallback_ast_repr = \var_export($fallback_ast, true);
        $original_ast_repr = \var_export($ast, true);

        if ($fallback_ast_repr !== $original_ast_repr) {
            $placeholders_used_str = $should_add_placeholders ? 'Yes' : 'No';
            $dumper = new NodeDumper($incomplete_contents);
            $dumper->setIncludeTokenKind(true);
            $dumper->setIncludeOffset(true);
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall should not happen and unit test would fail
            $dump = $dumper->dumpTreeAsString($php_parser_node);
            $original_ast_dump = Debug::nodeToString($ast);
            $modified_ast_dump = Debug::nodeToString($fallback_ast);
            // $parser_export = var_export($php_parser_node, true);
            $this->assertSame($original_ast_repr, $fallback_ast_repr, <<<EOT
The fallback must return the same tree of php-ast nodes
Placeholders Used: $placeholders_used_str
Code:
$incomplete_contents

Closest Valid Code:
$valid_contents

Original AST:
$original_ast_dump

Fallback AST
$modified_ast_dump

Tolerant-PHP-Parser(simplified):
$dump
EOT

            /*
Tolerant-PHP-Parser(unsimplified):
$parser_export
             */);
        }
    }
}
