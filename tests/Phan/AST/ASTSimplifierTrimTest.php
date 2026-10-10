<?php

declare(strict_types=1);

namespace Phan\Tests\AST;

use Phan\AST\ASTSimplifier;
use Phan\Config;
use Phan\Debug;
use Phan\Tests\TestBase;

use function str_replace;

/**
 * Tests of how ASTSimplifier trims large array literals (ast_trim_max_elements_per_level, ast_trim_max_total_elements).
 */
final class ASTSimplifierTrimTest extends TestBase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Config::setValue('ast_trim_max_elements_per_level', Config::DEFAULT_CONFIGURATION['ast_trim_max_elements_per_level']);
        Config::setValue('ast_trim_max_total_elements', Config::DEFAULT_CONFIGURATION['ast_trim_max_total_elements']);
    }

    /**
     * @dataProvider trimProvider
     */
    public function testTrim(string $code, string $expected): void
    {
        Config::setValue('ast_trim_max_elements_per_level', 3);
        Config::setValue('ast_trim_max_total_elements', 6);
        $node = \ast\parse_code('<?php ' . $code, Config::AST_VERSION);
        $actual = str_replace("\t", '    ', Debug::nodeToString(ASTSimplifier::applyStatic($node)));
        $this->assertSame($expected . "\n", $actual, $code);
    }

    /**
     * Elements with possible side effects and elements referring to classes are kept, (at)var and (at)return annotations are added.
     * @return list<array{0:string,1:string}>
     */
    public static function trimProvider(): array
    {
        return [
            [
                '$x = [1, f(), 3, 4, 5, $y++];',
                <<<'EOT'
                AST_STMT_LIST [] #1
                    0 => AST_ASSIGN [] #1
                        var => AST_VAR [] #1
                            name => x
                        expr => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                            0 => AST_ARRAY_ELEM [] #1
                                value => 1
                                key => null
                            1 => AST_ARRAY_ELEM [] #1
                                value => AST_CALL [] #1
                                    expr => AST_NAME [NAME_NOT_FQ] #1
                                        name => f
                                    args => AST_ARG_LIST [] #1
                                key => null
                            2 => AST_ARRAY_ELEM [] #1
                                value => 3
                                key => null
                            3 => AST_ARRAY_ELEM [] #1
                                value => AST_POST_INC [] #1
                                    var => AST_VAR [] #1
                                        name => y
                                key => null
                        docComment => /** @var array<array-key,mixed> */
                EOT,
            ],
            [
                '$x = [A::class, 2, 3, 4, B::C, 6, 7];',
                <<<'EOT'
                AST_STMT_LIST [] #1
                    0 => AST_ASSIGN [] #1
                        var => AST_VAR [] #1
                            name => x
                        expr => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                            0 => AST_ARRAY_ELEM [] #1
                                value => AST_CLASS_NAME [] #1
                                    class => AST_NAME [NAME_NOT_FQ] #1
                                        name => A
                                key => null
                            1 => AST_ARRAY_ELEM [] #1
                                value => 4
                                key => null
                            2 => AST_ARRAY_ELEM [] #1
                                value => AST_CLASS_CONST [] #1
                                    class => AST_NAME [NAME_NOT_FQ] #1
                                        name => B
                                    const => C
                                key => null
                            3 => AST_ARRAY_ELEM [] #1
                                value => 7
                                key => null
                        docComment => /** @var array<array-key,mixed> */
                EOT,
            ],
            [
                '$x = foo([1, 2], [[1, 2, 3, 4], [5, 6], 7, 8]);',
                <<<'EOT'
                AST_STMT_LIST [] #1
                    0 => AST_ASSIGN [] #1
                        var => AST_VAR [] #1
                            name => x
                        expr => AST_CALL [] #1
                            expr => AST_NAME [NAME_NOT_FQ] #1
                                name => foo
                            args => AST_ARG_LIST [] #1
                                0 => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                    0 => AST_ARRAY_ELEM [] #1
                                        value => 1
                                        key => null
                                    1 => AST_ARRAY_ELEM [] #1
                                        value => 2
                                        key => null
                                1 => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                    0 => AST_ARRAY_ELEM [] #1
                                        value => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                            0 => AST_ARRAY_ELEM [] #1
                                                value => 1
                                                key => null
                                        key => null
                                    1 => AST_ARRAY_ELEM [] #1
                                        value => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                            0 => AST_ARRAY_ELEM [] #1
                                                value => 5
                                                key => null
                                        key => null
                                    2 => AST_ARRAY_ELEM [] #1
                                        value => 8
                                        key => null
                        docComment => /** @var array<array-key,mixed> */
                EOT,
            ],
            [
                'class C { public $p = [1, 2, 3]; const K = [1, 2, 3, 4, 5]; }',
                <<<'EOT'
                AST_STMT_LIST [] #1
                    0 => AST_CLASS [] #1:1
                        name => C
                        docComment => null
                        extends => null
                        implements => null
                        stmts => AST_STMT_LIST [] #1
                            0 => AST_PROP_GROUP [MODIFIER_PUBLIC] #1
                                type => null
                                props => AST_PROP_DECL [] #1
                                    0 => AST_PROP_ELEM [] #1
                                        name => p
                                        default => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                            0 => AST_ARRAY_ELEM [] #1
                                                value => 1
                                                key => null
                                            1 => AST_ARRAY_ELEM [] #1
                                                value => 2
                                                key => null
                                            2 => AST_ARRAY_ELEM [] #1
                                                value => 3
                                                key => null
                                        docComment => null
                                        hooks => null
                                attributes => null
                            1 => AST_CLASS_CONST_GROUP [MODIFIER_PUBLIC] #1
                                const => AST_CLASS_CONST_DECL [] #1
                                    0 => AST_CONST_ELEM [] #1
                                        name => K
                                        value => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                            0 => AST_ARRAY_ELEM [] #1
                                                value => 1
                                                key => null
                                            1 => AST_ARRAY_ELEM [] #1
                                                value => 3
                                                key => null
                                            2 => AST_ARRAY_ELEM [] #1
                                                value => 5
                                                key => null
                                        docComment => null
                                attributes => null
                                type => null
                        attributes => null
                        type => null
                        __declId => 0
                EOT,
            ],
            [
                'function f() { static $s = [1, 2, 3, 4, 5]; return g(["a" => 1, "b" => h(), "c" => 3, "d" => 4]); }',
                <<<'EOT'
                AST_STMT_LIST [] #1
                    0 => AST_FUNC_DECL [] #1:1
                        name => f
                        docComment => null
                        params => AST_PARAM_LIST [] #1
                        stmts => AST_STMT_LIST [] #1
                            0 => AST_STATIC [] #1
                                var => AST_VAR [] #1
                                    name => s
                                default => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                    0 => AST_ARRAY_ELEM [] #1
                                        value => 1
                                        key => null
                                    1 => AST_ARRAY_ELEM [] #1
                                        value => 3
                                        key => null
                                    2 => AST_ARRAY_ELEM [] #1
                                        value => 5
                                        key => null
                                docComment => /** @var array<array-key,mixed> */
                            1 => AST_RETURN [] #1
                                expr => AST_CALL [] #1
                                    expr => AST_NAME [NAME_NOT_FQ] #1
                                        name => g
                                    args => AST_ARG_LIST [] #1
                                        0 => AST_ARRAY [ARRAY_SYNTAX_SHORT] #1
                                            0 => AST_ARRAY_ELEM [] #1
                                                value => 1
                                                key => a
                                            1 => AST_ARRAY_ELEM [] #1
                                                value => AST_CALL [] #1
                                                    expr => AST_NAME [NAME_NOT_FQ] #1
                                                        name => h
                                                    args => AST_ARG_LIST [] #1
                                                key => b
                                            2 => AST_ARRAY_ELEM [] #1
                                                value => 4
                                                key => d
                                docComment => /** @return array<array-key,mixed> */
                        returnType => null
                        attributes => null
                        __declId => 0
                EOT,
            ],
        ];
    }
}
