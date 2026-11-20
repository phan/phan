<?php

declare(strict_types=1);

namespace Phan\Tests\AST;

use Phan\AST\PartialApplicationReparser;
use Phan\Debug;
use Phan\Tests\TestBase;

/**
 * Tests of recovering the arguments of php 8.6 partial function application when php-ast does not provide AST version 130.
 *
 * Rewriting and reparsing do not need php 8.6: the rewritten code is valid in older php versions.
 */
final class PartialApplicationReparserTest extends TestBase
{
    private const P = '\\__PHAN_PLACEHOLDER__';

    /**
     * Code with partial function application and other uses of `?` and `...`, all valid php 8.6 code.
     * The test that compares with AST version 130 parses it.
     */
    private const NATIVE_CORPUS = <<<'EOT'
<?php
f(1, ?, $x);
f(1, ...);
f(?, c: ?, ...);
f(...);
$o->m(?, b: 2);
$o?->m(?);
C::s(?, ...);
$x |> f(1, ?);
f(
    1,
    ?,
    ...
);
f(1,
  ?
);
f(
  a: ?,
  b: 2,
);
f(g(?), h(1, ...), ?);
new C(1, ?);
#[A(?)]
function attr() {}
clone(?);
exit(?);
f(/* c */ ?, // c
  ... /* d */);
$a = f(?)(1);
f(?)
  (2);
$x = [f(?, 1), $y ? $z : f(...)];
$fn = fn(?int $x): ?int => $x ? f(?) : null;
f(
  ...
);
f(
  ?
);
$s = "VALUES (?, ?)" . f(?, ...);
f(b: 1, ...);
f(?, );
class P extends Q {
    function m() {
        $a = parent::m(?);
        $b = static::m(1, ...);
        $c = self::m(
            ?,
            ...
        );
        return [$a, $b, $c];
    }
}
$d = $f(?, 2);
$e = (new C)->m(?);
$g = new class(?) {};
$h = "x {$o->m(?)} y";
$i = <<<EOS
  {$o->m(1, ...)}
  EOS;
$k = f(..., b: 2);
$l = f(
  ...
  ,
  b: 2
);
$n = f(?)(?)(...);
$q = f(
  1
  , ?
  , b
  : ?
  , ...
  );
$r = f($x ? $y : $z, ?, $u ?: $v, $w ?? null, $o?->p, ?);
function vf(?int ...$xs): ?int { return null; }
$t = vf(...[1, 2], ...$xs);
EOT;

    /**
     * @dataProvider rewriteProvider
     * @param ?string $expected the rewritten code, or null if it has no placeholders
     * @param bool $expected_may_contain_placeholders the result of the check that selects the files to rewrite
     */
    public function testRewrite(?string $expected, string $code, bool $expected_may_contain_placeholders): void
    {
        $code = "<?php\n$code";
        $result = PartialApplicationReparser::rewrite($code);
        $this->assertSame($expected !== null ? "<?php\n$expected" : null, $result[0] ?? null);
        $this->assertSame($expected_may_contain_placeholders, PartialApplicationReparser::mayContainPlaceholders($code));
    }

    /**
     * @return list<array{0:?string,1:string,2:bool}>
     */
    public static function rewriteProvider(): array
    {
        $p = self::P;
        // The line of the token after the `...` is the line of the variadic placeholder. The code starts on line 2.
        $v = static fn(int $line): string => '\\__PHAN_VARIADIC_PLACEHOLDER_' . $line;
        return [
            ["f(1, $p, \$x);", 'f(1, ?, $x);', true],
            ["f(1, {$v(2)});", 'f(1, ...);', true],
            ["f($p, c: $p, {$v(2)});", 'f(?, c: ?, ...);', true],
            ["\$o?->m($p, b: 2);", '$o?->m(?, b: 2);', true],
            ["f($p, );", 'f(?, );', true],
            ["f({$v(2)}, b: 2);", 'f(..., b: 2);', true],
            ["f(\n  1,\n  $p,\n  {$v(6)}\n);", "f(\n  1,\n  ?,\n  ...\n);", true],
            ["f(/* a */ $p, // b\n  {$v(3)} /* c */);", "f(/* a */ ?, // b\n  ... /* c */);", true],
            ["f(a: # b\n  $p);", "f(a: # b\n  ?);", true],
            ["new C($p);", 'new C(?);', true],
            ["#[A($p)] function g() {}", '#[A(?)] function g() {}', true],
            // When a file has placeholders, `f(...)` is rewritten too, but it does not make a file selected
            ["f({$v(2)}); f($p);", 'f(...); f(?);', true],
            [null, 'f(...);', false],
            [null, '$a = $b ? $c : $d;', false],
            [null, '$a = f($b ?: $c, $d ?? $e, $f?->g);', false],
            [null, 'function g(?int $a, ?Foo $b = null): ?int { return $a; }', false],
            [null, '$a = [...$b, ...f(...$c)]; function g(int ...$a) {}', false],
            [null, 'if ($a): ?>x<?php endif;', false],
            // Text that looks like placeholders in strings and comments makes a file selected, but is not rewritten
            [null, '$sql = "INSERT INTO t VALUES (?, ?)"; // f(1, ...)', true],
        ];
    }

    /**
     * @dataProvider reparseProvider
     */
    public function testReparse(string $expected, string $code): void
    {
        $node = PartialApplicationReparser::reparse("<?php\n$code", 'test.php', 120);
        $this->assertNotNull($node);
        $this->assertSame($expected, Debug::astDump($node->children[0], Debug::AST_DUMP_LINENOS));
    }

    /**
     * The expected nodes are the nodes that php-ast creates in AST version 130.
     * @return list<array{0:string,1:string}>
     */
    public static function reparseProvider(): array
    {
        return [
            [
                <<<'EOT'
AST_CALL @ 2
    expr: AST_NAME @ 2
        flags: NAME_NOT_FQ (1)
        name: "f"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: 1
            1: AST_PLACEHOLDER_ARG @ 2
            2: AST_VAR @ 2
                name: "x"
EOT,
                'f(1, ?, $x);',
            ],
            [
                <<<'EOT'
AST_CALL @ 2
    expr: AST_NAME @ 2
        flags: NAME_NOT_FQ (1)
        name: "f"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: AST_PLACEHOLDER_ARG @ 2
            1: AST_NAMED_ARG @ 2
                name: "c"
                expr: AST_PLACEHOLDER_ARG @ 2
            2: AST_PLACEHOLDER_ARG @ 2
                flags: PLACEHOLDER_VARIADIC (1)
EOT,
                'f(?, c: ?, ...);',
            ],
            [
                <<<'EOT'
AST_METHOD_CALL @ 2
    expr: AST_VAR @ 2
        name: "o"
    method: "m"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: AST_PLACEHOLDER_ARG @ 2
            1: AST_NAMED_ARG @ 2
                name: "b"
                expr: 2
EOT,
                '$o->m(?, b: 2);',
            ],
            [
                <<<'EOT'
AST_NULLSAFE_METHOD_CALL @ 2
    expr: AST_VAR @ 2
        name: "o"
    method: "m"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: AST_PLACEHOLDER_ARG @ 2
EOT,
                '$o?->m(?);',
            ],
            [
                <<<'EOT'
AST_STATIC_CALL @ 2
    class: AST_NAME @ 2
        flags: NAME_NOT_FQ (1)
        name: "C"
    method: "s"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: AST_PLACEHOLDER_ARG @ 2
            1: AST_PLACEHOLDER_ARG @ 2
                flags: PLACEHOLDER_VARIADIC (1)
EOT,
                'C::s(?, ...);',
            ],
            [
                // php reads the token after a `...` before it creates the placeholder, and uses its line.
                <<<'EOT'
AST_CALL @ 2
    expr: AST_NAME @ 2
        flags: NAME_NOT_FQ (1)
        name: "f"
    args: AST_CALLABLE_CONVERT @ 5
        args: AST_ARG_LIST @ 3
            0: 1
            1: AST_PLACEHOLDER_ARG @ 5
                flags: PLACEHOLDER_VARIADIC (1)
EOT,
                "f(\n    1,\n    ...\n);",
            ],
            [
                <<<'EOT'
AST_CALL @ 2
    expr: AST_NAME @ 2
        flags: NAME_NOT_FQ (1)
        name: "f"
    args: AST_CALLABLE_CONVERT @ 2
        args: AST_ARG_LIST @ 2
            0: AST_CALL @ 2
                expr: AST_NAME @ 2
                    flags: NAME_NOT_FQ (1)
                    name: "g"
                args: AST_CALLABLE_CONVERT @ 2
                    args: AST_ARG_LIST @ 2
                        0: AST_PLACEHOLDER_ARG @ 2
            1: AST_PLACEHOLDER_ARG @ 2
                flags: PLACEHOLDER_VARIADIC (1)
EOT,
                'f(g(?), ...);',
            ],
        ];
    }

    public function testReparseWithoutPlaceholders(): void
    {
        $this->assertNull(PartialApplicationReparser::reparse("<?php\nf(...);\n\$a = \$b ? \$c : \$d;", 'test.php', 120));
    }

    /**
     * Compares the result with the nodes that php-ast creates in AST version 130.
     * This needs php 8.6 and a php-ast that supports AST version 130.
     */
    public function testMatchesNativeVersion130(): void
    {
        if (\PHP_VERSION_ID < 80600 || !\in_array(130, \ast\get_supported_versions(), true)) {
            $this->markTestSkipped('This needs php 8.6 and php-ast 1.1.5+');
        }
        $node = PartialApplicationReparser::reparse(self::NATIVE_CORPUS, 'test.php', 120);
        $this->assertNotNull($node);
        $this->assertSame(
            Debug::astDump(\ast\parse_code(self::NATIVE_CORPUS, 130), Debug::AST_DUMP_LINENOS),
            Debug::astDump($node, Debug::AST_DUMP_LINENOS)
        );
    }
}
