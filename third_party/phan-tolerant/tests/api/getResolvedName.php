<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;
use Microsoft\PhpParser\Node\Statement\IfStatementNode;
use Microsoft\PhpParser\Node\Statement\NamespaceDefinition;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;

class GetResolvedNameTest extends TestCase {

    // Position marked by '_'
    const testData = array(
        // null
        'namespace _A' => null,
        'namespace Foo\B_ar' => null,
        'use _A' => null,
        'use Foo\_Bar' => null,
        'use A, _B' => null,
        'use _A as C' => null,
        'use A\B\{C, D_}' => null,
        'use A\B\{C, D_}' => null,
        'class Foo { use A, B { A::Foo as _foo }' => null,

        // Relative scope resolution qualifiers
        'class Foo { function xyz() { _SELF::Bar; } }' => 'self',
        'class Foo { function xyz() { _parent::Bar; } }' => 'parent',
        'class Foo { function xyz() { _static::Bar; } }' => 'static',

        // Fully qualified name
        '\Fo_o\Bar' => 'Foo\Bar',
        '\Fo_o' => 'Foo',
        'use A\Foo;\_Foo\Bar' => 'Foo\Bar',
        '$f = new \_Foo\Bar()' => 'Foo\Bar',
        'class Foo { use A, B { \Foo\_Bar::A as foo }' => 'Foo\Bar',
        'class Foo { use \A\BTr_ait; }' => 'A\BTrait',

        // Relative name
        'namespace Foo; namespace\Bar_' => 'Foo\Bar',
        'namespace Foo\Bar; namespace\abc_\def' => 'Foo\Bar\abc\def',
        'namespace Foo; use A\Foo; namespace\a_bc' => 'Foo\abc',
        'namespace Foo; $f = new namespace\Ba_r()' => 'Foo\Bar',
        'namespace Foo; class C { use A, B { namespace\B_ar::A as foo }' => 'Foo\Bar',

        // Qualified name
        'use A\B; _B\C()' => 'A\B\C',
        'use A\B, B\C; B\D_()' => 'A\B\D',
        'use A\{B, C}; C\D_()' => 'A\C\D',
        'class Foo { use A, B { \Foo\_Bar::A as foo }' => 'Foo\Bar',
        'use A\B\C; B\_C\D()' => 'B\C\D',
        'namespace Foo; use A\B\C; B\_C\D()' => 'Foo\B\C\D',
        'use A\B; B\C_::foo()' => 'A\B\C',

        // Unqualified name
        'use A\{const B}; B_' => 'A\B',
        'use A\B; _B' => '', // Parsed as constant
        'use function A\B\f; f_()' => 'A\B\f',
        'use function A\B\{f, g}; g_()' => 'A\B\g',
        'use A\B; new _B()' => 'A\B', // Class imported
        'use A\B; _B()' => 'B', // Not imported as function
        'use A\B; function xyz(): _B { };' => 'A\B',
        'class Foo { use _BarTrait; }' => 'BarTrait',
        'class Foo { use BarTrait, Baz_Trait; }' => 'BazTrait',
        'use A\BarTrait; class Foo { use _BarTrait; }' => 'A\BarTrait',
    );

    public function dataProvider() {
        $result = [];
        foreach (GetResolvedNameTest::testData as $contents => $expectedName) {
            $contents = '<?php' . PHP_EOL . $contents;
            $result[] = [$contents, $expectedName];
        }

        return $result;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testGetResolvedName($contents, $expectedName) {
        $node = $this->getNodeAtPosition($contents);
        $this->assertInstanceOf(Node\QualifiedName::class, $node);

        $name = $node->getResolvedName();
        $this->assertEquals($expectedName, (string)$name);
    }

    private function getNodeAtPosition($contents): Node {
        $parser = new Parser();
        $pos = strpos($contents, '_');
        $this->assertNotFalse($pos, 'Data is missing underscore');
        $contents = str_replace('_', '', $contents);

        $node = $parser->parseSourceFile($contents);

        $actualNode = $node->getDescendantNodeAtPosition($pos);
        $this->assertNotNull($actualNode);

        return $actualNode;
    }
}
