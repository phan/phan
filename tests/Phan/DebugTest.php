<?php declare(strict_types = 1);

namespace Phan\Tests;

use Phan\Debug;

/**
 * Unit tests of static helper methods used for debugging in Debug
 */
final class DebugTest extends BaseTest
{
    public function testNodeToString()
    {
        $this->assertSame("2\n", Debug::nodeToString(2));
        $this->assertSame("example\n", Debug::nodeToString('example'));
        $actual_code = <<<'EOT'
<?php
class MyClass {
    public function test(int $x = 2) {
    }
}
EOT;
        $expected_ast_repr = <<<EOT
AST_STMT_LIST [] #1
\t0 => AST_CLASS [] #2:5
\t\tname => MyClass
\t\tdocComment => null
\t\textends => null
\t\timplements => null
\t\tstmts => AST_STMT_LIST [] #2
\t\t\t0 => AST_METHOD [MODIFIER_PUBLIC] #3:4
\t\t\t\tname => test
\t\t\t\tdocComment => null
\t\t\t\tparams => AST_PARAM_LIST [] #3
\t\t\t\t\t0 => AST_PARAM [] #3
\t\t\t\t\t\ttype => AST_TYPE [TYPE_LONG] #3
\t\t\t\t\t\tname => x
\t\t\t\t\t\tdefault => 2
\t\t\t\tuses => null
\t\t\t\tstmts => AST_STMT_LIST [] #3
\t\t\t\treturnType => null
\t\t\t\t__declId => null
\t\t__declId => 1

EOT;
        $ast = \ast\parse_code($actual_code, 50);
        $actual_ast_repr = Debug::nodeToString($ast);
        $this->assertSame($expected_ast_repr, $actual_ast_repr);
    }
}
