<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;
use PHPUnit\Framework\TestCase;
use Microsoft\PhpParser\TokenKind;

class ParserInvariantsTest extends LexerInvariantsTest {
    const FILENAME_PATTERN = __dir__ . "/cases/{parser,parser74,}/*.php";

    public static function sourceFileNodeProvider() {
        $testFiles = [];
        $testCases = glob(self::FILENAME_PATTERN, GLOB_BRACE);

        foreach ($testCases as $filename) {
            $parser = new \Microsoft\PhpParser\Parser();
            $testFiles[basename($filename)] = [$filename, $parser->parseSourceFile(file_get_contents($filename))];
        }
        return $testFiles;
    }

    public static function tokensArrayProvider() {
        $testFiles = [];
        $testCases = glob(self::FILENAME_PATTERN, GLOB_BRACE);

        foreach ($testCases as $filename) {
            $parser = new \Microsoft\PhpParser\Parser();
            $sourceFileNode = $parser->parseSourceFile(file_get_contents($filename));
            $tokensArray = [];
            foreach ($sourceFileNode->getDescendantNodesAndTokens() as $child) {
                if ($child instanceof \Microsoft\PhpParser\Token) {
                    $tokensArray[] = $child;
                }
            }
            $testFiles[basename($filename)] = [$filename, $tokensArray];
        }
        return $testFiles;
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testSourceFileNodeLengthEqualsDocumentLength($filename, Node $sourceFileNode) {
        $this->assertEquals(
            filesize($filename), $sourceFileNode->getFullWidth(),
            "Invariant: The tree length exactly matches the file length.");
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testNodesAllHaveAtLeastOneChild($filename, Node $sourceFileNode) {
        foreach ($sourceFileNode->getDescendantNodesAndTokens() as $child) {
            if ($child instanceof Node) {
                $encode = json_encode($child);
                $this->assertGreaterThanOrEqual(
                    1, iterator_count($child->getChildNodesAndTokens()),
                    "Invariant: All Nodes have at least one child. $encode"
                );
            }
        }
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testEveryNodeSpanIsSumOfChildSpans($filename, Node $sourceFileNode) {
        $treeElements = iterator_to_array($sourceFileNode->getDescendantNodesAndTokens(), false);
        $treeElements[] = $sourceFileNode;

        foreach ($treeElements as $element) {
            if ($element instanceof Node) {
                $expectedLength = 0;
                foreach ($element->getChildNodesAndTokens() as $child) {
                    if ($child instanceof Node) {
                        $expectedLength += $child->getFullWidth();
                    } elseif ($child instanceof \Microsoft\PhpParser\Token) {
                        $expectedLength += $child->length;
                    }
                }
                $this->assertEquals(
                    $expectedLength, $element->getFullWidth(),
                    "Invariant: Span of any Node is span of child nodes and tokens."
                );
            }
        }
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testParentOfNodeHasSameChildNode($filename, Node $sourceFileNode) {
        foreach ($sourceFileNode->getDescendantNodesAndTokens() as $child) {
            if ($child instanceof Node) {
                if (!$child->parent) {
                    $this->fail("Missing parent for " . var_export($child, true));
                }

                $this->assertContains(
                    $child, $child->parent->getChildNodesAndTokens(),
                    "Invariant: Parent of Node contains same child node."
                );
            }
        }
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testEachChildHasExactlyOneParent($filename, Node $sourceFileNode) {
        $allTreeElements = iterator_to_array($sourceFileNode->getDescendantNodesAndTokens(), false);
        $allTreeElements[] = $sourceFileNode;

        foreach ($sourceFileNode->getDescendantNodesAndTokens() as $childWithParent) {
            $count = 0;
            foreach ($allTreeElements as $element) {
                if ($element instanceof Node) {
                    $values = iterator_to_array($element->getChildNodesAndTokens(), false);
                    if (in_array($childWithParent, $values, true)) {
                        $count++;
                    }
                }
            }
            $this->assertEquals(
                1, $count,
                "Invariant: each child has exactly one parent.");
        }
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testEveryChildIsNodeOrTokenType($filename, Node $sourceFileNode) {
        $treeElements = iterator_to_array($sourceFileNode->getDescendantNodesAndTokens(), false);
        $treeElements[] = $sourceFileNode;

        foreach ($sourceFileNode->getDescendantNodes() as $descendant) {
            foreach ($descendant->getChildNodesAndTokens() as $child) {
                if ($child instanceof Node || $child instanceof Token) {
                    continue;
                }
                $this->fail("Invariant: Every child is Node or Token type");
            }
        }
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testRootNodeHasNoParent($filename, Node $sourceFileNode) {
        $this->assertEquals(
            null, $sourceFileNode->parent,
            "Invariant: Root node of tree has no parent.");
    }

    /**
     * @dataProvider sourceFileNodeProvider
     */
    public function testRootNodeIsNeverAChild($filename, Node $sourceFileNode) {
        $treeElements = iterator_to_array($sourceFileNode->getDescendantNodesAndTokens(), false);
        $treeElements[] = $sourceFileNode;

        foreach ($treeElements as $element) {
            if ($element instanceof Node) {
                $this->assertNotContains(
                    $sourceFileNode, $element->getChildNodesAndTokens(),
                    "Invariant: root node of tree is never a child.");
            }
        }
    }
}
