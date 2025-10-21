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

class NodeApiTest extends TestCase {
    const FILENAME_PATTERN = __dir__ . "/cases/{parser,}/*.php";

    const FILE_CONTENTS = <<<'PHP'
<?php
function a () {
    // trivia
    $a = 3;
}
PHP;

    public static $sourceFileNode;

    public static function setUpBeforeClass(): void {
        $parser = new Parser();
        self::$sourceFileNode = $parser->parseSourceFile(self::FILE_CONTENTS);
        parent::setUpBeforeClass();
    }

    public function testRootNodeIsScript() {
        $treeElements = iterator_to_array(self::$sourceFileNode->getDescendantNodes(), false);
        $treeElements[] = self::$sourceFileNode;

        foreach ($treeElements as $element) {
            $this->assertInstanceOf(SourceFileNode::class, $element->getRoot());
        }
    }

    public function testFileContentsRetrievableFromAnyNode() {
        $treeElements = iterator_to_array(self::$sourceFileNode->getDescendantNodes(), false);
        $treeElements[] = self::$sourceFileNode;

        foreach ($treeElements as $element) {
            $this->assertEquals(self::FILE_CONTENTS, $element->getFileContents());
        }
    }

    public function testFullTextOfRootNodeEqualsFullDocument() {
        $this->assertEquals(self::FILE_CONTENTS, self::$sourceFileNode->getFullText());
    }

    public function testGetTriviaForNode() {
        $contents = '<?php /* contents */ $a = 1';
        $parser = new Parser();
        $iterator = $parser->parseSourceFile($contents)->getChildNodes();
        $iterator->next();
        $actualTrivia = $iterator->current()->getLeadingCommentAndWhitespaceText();
        $this->assertEquals('/* contents */ ', $actualTrivia);

        $sourceFile = $parser->parseSourceFile('');
        $this->assertEquals('', $sourceFile->getLeadingCommentAndWhitespaceText());
    }

    public function testGetTextForNode() {
        $contents = '<?php /* contents */ $a = 1';
        $parser = new Parser();
        $iterator = $parser->parseSourceFile($contents)->getChildNodes();
        $iterator->next();
        $actualText = $iterator->current()->getText();
        $this->assertEquals('$a = 1', $actualText);

        $sourceFile = $parser->parseSourceFile('');
        $this->assertEquals('', $sourceFile->getText());
    }

    public function testGetFullTextForNode() {
        $contents = '<?php /* contents */ $a = 1';
        $parser = new Parser();
        $iterator = $parser->parseSourceFile($contents)->getChildNodes();
        $iterator->next();
        $actualText = $iterator->current()->getFullText();
        $this->assertEquals('/* contents */ $a = 1', $actualText);

        $sourceFile = $parser->parseSourceFile('');
        $this->assertEquals('', $sourceFile->getFullText());
    }

    public function testGetFirstAncestor() {
        $contents = <<< PHP
<?php
namespace Hello {
    class A {
    }
}
PHP;
        $parser = new Parser();
        // TODO consider renaming to parseContents
        $ast = $parser->parseSourceFile($contents);
        // TODO statements vs statementList naming inconsistency
        $classNode = $ast->statementList[1]->compoundStatementOrSemicolon->statements[0]->classMembers;

        self::assertInstanceOf(
            NamespaceDefinition::class,
            $classNode->getFirstAncestor(NamespaceDefinition::class),
            "getFirstAncestor with a single specified class name should return first occurrence."
        );
        self::assertInstanceOf(
            NamespaceDefinition::class,
            $classNode->getFirstAncestor(SourceFileNode::class, NamespaceDefinition::class),
            "getFirstAncestor with multiple specified class names should return first occurrence."
        );

        self::assertNull(
            $classNode->getFirstAncestor(IfStatementNode::class),
            "getFirstAncestor with a non-ancestor class name should return null."
        );

        self::assertNull(
            $classNode->getFirstAncestor(),
            "getFirstAncestor with no specified class names should return null."
        );
    }

    public function testGetDocCommentText() {
        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /** */ function b () { }",
            "/** */"
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /***/ function b () { }",
            null
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /*/** */ function b () { }",
            null
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /**d */ function b () { }",
            null
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /** hello */\n/** */ function b () { }",
            "/** */"
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php /** hello */\n/**\n*/ function b () { }",
            "/**\n*/"
        );

        $this->AssertDocCommentTextOfNode(
            FunctionDeclaration::class,
            "<?php function b () { }",
            null
        );

        $this->AssertDocCommentTextOfNode(
            \Microsoft\PhpParser\Node\Statement\InlineHtml::class,
            "/** hello */ <?php function b () { }",
            null
        );
    }

    private function AssertDocCommentTextOfNode($nodeKind, $contents, $expectedDocCommentText) : array {
        $parser = new Parser();
        $ast = $parser->parseSourceFile($contents);
        $functionDeclaration = $ast->getFirstDescendantNode($nodeKind);
        $this->assertEquals(
            $expectedDocCommentText,
            $functionDeclaration->getDocCommentText()
        );
        return array($contents, $parser, $ast, $functionDeclaration);
    }
}
