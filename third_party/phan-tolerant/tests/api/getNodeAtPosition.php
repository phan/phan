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

class GetNodeAtPositionTest extends TestCase {

    const textTestData = array(
        <<<'PHP'
<?php
function a() {
    $a_
    $b = 2;
}
PHP
        => '$a',

        <<<'PHP'
<?php
function a() {
    $a->f_oo();
}
PHP
        => '$a->foo',

        <<<'PHP'
<?php
function a() {
    $a->foo_
    $b = 1;
}
PHP
        => '$a->foo',

        <<<'PHP'
<?php
function a() {
    $a->_$b;
}
PHP
        => '$b',

        <<<'PHP'
<?php
function a() {
    $a->_;$b;
}
PHP
        => '$a->'
    );

    public function textDataProvider() {
        return $this->getDataProvider(GetNodeAtPositionTest::textTestData);
    }

    /**
     * @dataProvider textDataProvider
     */
    public function testNodePositionByText($contents, $expectedText) {
        $this->assertNodeAtPositionText($contents, $expectedText);
    }

    const classTestData = array(
        <<<'PHP'
<?php
function a() {
    // _trivia
    $a = 3;
}
PHP
        => \Microsoft\PhpParser\Node\Statement\CompoundStatementNode::class,

        <<<'PHP'
        <?php
function a() {
    // trivia
    _$a = 3;
}
PHP
        => \Microsoft\PhpParser\Node\Expression\Variable::class,

        <<<'PHP'
<?php
function _a() {
    // trivia
    $a = 3;
}
PHP
        => \Microsoft\PhpParser\Node\Statement\FunctionDeclaration::class
    );

    public function classDataProvider() {
        return $this->getDataProvider(GetNodeAtPositionTest::classTestData);
    }

    /**
     * @dataProvider classDataProvider
     */
    public function testNodePositionByClass($contents, $expectedClass) {
        $this->assertNodeAtPositionInstanceOf($contents, $expectedClass);
    }

    private function getNodeAtPosition($contents): Node {
        $parser = new Parser();
        $pos = strpos($contents, '_');
        $contents = str_replace('_', '', $contents);

        $node = $parser->parseSourceFile($contents);

        $actualNode = $node->getDescendantNodeAtPosition($pos);
        $this->assertNotNull($actualNode);

        return $actualNode;
    }

    private function assertNodeAtPositionInstanceOf($contents, $expectedClass, $message = '') {
        $actualNode = $this->getNodeAtPosition($contents);
        $text = $actualNode->getText();
        $message = "Got node with text: $text" . ($message ? PHP_EOL . $message : '');
        $this->assertInstanceOf($expectedClass, $actualNode, $message);
    }

    private function assertNodeAtPositionText($contents, $expectedText, $message = '') {
        $actualNode = $this->getNodeAtPosition($contents);
        $text = $actualNode->getText();
        $message = "Got node with text: $text" . ($message ? PHP_EOL . $message : '');
        $this->assertEquals($expectedText, $text, $message);
    }

    private function getDataProvider(array $testData) {
        $result = array();
        foreach ($testData as $testCode => $expectedText) {
            $result[$testCode] = [$testCode, $expectedText];
        }

        return $result;
    }
}
