<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\SkippedToken;
use PHPUnit\Framework\TestCase;
use Microsoft\PhpParser\TokenKind;

class LexerInvariantsTest extends TestCase {
    const FILENAMES = [
        __dir__ . "/cases/testfile.php",
        __dir__ . "/cases/commentsFile.php"
    ];

    public static function tokensArrayProvider() {
        $fileToTokensMap = [];
        foreach (self::FILENAMES as $filename) {
            $lexer = \Microsoft\PhpParser\TokenStreamProviderFactory::GetTokenStreamProvider(file_get_contents($filename));
            $fileToTokensMap[basename($filename)] = [$filename, $lexer->getTokensArray()];
        }
        return $fileToTokensMap;
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenLengthSum($filename, $tokensArray) {
        $tokenLengthSum = 0;
        foreach ($tokensArray as $token) {
            $tokenLengthSum += $token->length;
        }

        $this->assertEquals(
            filesize($filename), $tokenLengthSum,
            "Invariant: Sum of the lengths of all the tokens should be equivalent to the length of the document.");
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenStartGeqFullStart($filename, $tokensArray) {
        foreach ($tokensArray as $token) {
            $this->assertGreaterThanOrEqual(
                $token->fullStart, $token->start,
                "Invariant: A token's Start is always >= FullStart.");
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenContentMatchesFileSpan($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);
        foreach ($tokensArray as $token) {
            $this->assertEquals(
                substr($fileContents, $token->fullStart, $token->length),
                $token->getFullText($fileContents),
                "Invariant: A token's content exactly matches the range of the file its span specifies"
            );
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenFullTextMatchesTriviaPlusText($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);
        foreach ($tokensArray as $token) {
            $this->assertEquals(
                $token->getFullText($fileContents),
                $token->getLeadingCommentsAndWhitespaceText($fileContents) . $token->getText($fileContents),
                "Invariant: FullText of each token matches Trivia plus Text"
            );
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenFullTextConcatenationMatchesDocumentText($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);

        $tokenFullTextConcatenation = "";
        foreach ($tokensArray as $token) {
            $tokenFullTextConcatenation .= $token->getFullText($fileContents);
        }

        $this->assertEquals(
            $fileContents,
            $tokenFullTextConcatenation,
            "Invariant: Concatenating FullText of each token returns the document"
        );
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testGetTokenFullTextLengthMatchesLength($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);

        foreach ($tokensArray as $token) {
            $this->assertEquals(
                $token->length,
                strlen($token->getFullText($fileContents)),
                "Invariant: a token's FullText length is equivalent to Length"
            );
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenTextLengthMatchesLengthMinusStartPlusFullStart($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);

        foreach ($tokensArray as $token) {
            $this->assertEquals(
                $token->length - ($token->start - $token->fullStart),
                strlen($token->getText($fileContents)),
                "Invariant: a token's FullText length is equivalent to Length - (Start - FullStart)"
            );
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenTriviaLengthMatchesStartMinusFullStart($filename, $tokensArray) {
        $fileContents = file_get_contents($filename);

        foreach ($tokensArray as $token) {
            $this->assertEquals(
                $token->start - $token->fullStart,
                strlen($token->getLeadingCommentsAndWhitespaceText($fileContents)),
                "Invariant: a token's Trivia length is equivalent to (Start - FullStart)"
            );
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testEOFTokenTextHasZeroLength($filename, $tokensArray) {
        $tokenText = $tokensArray[count($tokensArray) - 1]->getText($filename);
        $this->assertEquals(
            0, strlen($tokenText),
            "Invariant: End-of-file token text should have zero length"
        );
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokensArrayEndsWithEOFToken($filename, $tokensArray) {
        $this->assertEquals(
            $tokensArray[count($tokensArray) - 1]->kind, TokenKind::EndOfFileToken,
            "Invariant: Tokens array should always end with end of file token"
        );
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokensArrayOnlyContainsExactlyOneEOFToken($filename, $tokensArray) {
        $eofTokenCount = 0;

        foreach ($tokensArray as $index => $token) {
            if ($token->kind == TokenKind::EndOfFileToken) {
                $eofTokenCount++;
            }
        }
        $this->assertEquals(
            1, $eofTokenCount,
            "Invariant: Tokens array should contain exactly one EOF token"
        );
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testTokenFullStartBeginsImmediatelyAfterPreviousToken($filename, $tokensArray) {
        $prevToken;
        foreach ($tokensArray as $index => $token) {
            if ($index === 0) {
                $prevToken = $token;
                continue;
            }

            $this->assertEquals(
                $prevToken->fullStart + $prevToken->length, $token->fullStart,
                "Invariant: Token FullStart should begin immediately after previous token end"
            );
            $prevToken = $token;
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testSkippedTokenLengthGreaterThanZero($filename, $tokensArray) {
        foreach ($tokensArray as $token) {
            if ($token instanceof SkippedToken) {
                $this->assertGreaterThan(
                    0, $token->length,
                    "Invariant: SkippedToken length should be greater than 0"
                );
            }
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testMissingTokenLengthEqualsZero($filename, $tokensArray) {
        foreach ($tokensArray as $token) {
            if ($token instanceof MissingToken) {
                $this->assertEquals(
                    0, $token->length,
                    "Invariant: MissingToken length should be equal to 0"
                );
            }
        }
    }

    /**
     * @dataProvider tokensArrayProvider
     */
    public function testWithDifferentEncodings($filename, $tokensArray) {
        // TODO test with different encodings
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
