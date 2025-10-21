<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\Token;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\AssertionFailedError;

require_once __DIR__ . '/CallbackTestListener.php';

class LexicalGrammarTest extends TestCase {
    private $expectedTokensFile;
    private $tokens;
    const FILE_PATTERN = __DIR__ . "/cases/lexical/*";
    public function run(?TestResult $result = null) : TestResult {
        if (!isset($GLOBALS["GIT_CHECKOUT_LEXER"])) {
            $GLOBALS["GIT_CHECKOUT_LEXER"] = true;
            exec("git -C " . dirname(self::FILE_PATTERN) . " checkout *.php.tokens");
        }

        $result->addListener(new CallbackTestListener(function (Test $test) {
            if (isset($test->expectedTokensFile) && isset($test->tokens)) {
                file_put_contents($test->expectedTokensFile, str_replace("\r\n", "\n", $test->tokens));
            }
        }));

        $result = parent::run($result);
        return $result;
    }


    /**
     * @dataProvider lexicalProvider
     */
    public function testOutputTokenClassificationAndLength($testCaseFile, $expectedTokensFile) {
        $fileContents = file_get_contents($testCaseFile);
        if (!file_exists($expectedTokensFile)) {
            file_put_contents($expectedTokensFile, $fileContents);
            exec("git add " . $expectedTokensFile);
        }

        $expectedTokens = str_replace("\r\n", "\n", file_get_contents($expectedTokensFile));
        $lexer = \Microsoft\PhpParser\TokenStreamProviderFactory::GetTokenStreamProvider($fileContents);
        $GLOBALS["SHORT_TOKEN_SERIALIZE"] = true;
        $tokens = str_replace("\r\n", "\n", json_encode($lexer->getTokensArray(), JSON_PRETTY_PRINT));
        $GLOBALS["SHORT_TOKEN_SERIALIZE"] = false;
        $this->expectedTokensFile = $expectedTokensFile;
        $this->tokens = $tokens;
        $this->assertEquals($expectedTokens, $tokens, "input: $testCaseFile\r\nexpected: $expectedTokensFile");
    }

    public function lexicalProvider() {
        $testCases = glob(__dir__ . "/cases/lexical/*.php");

        $skipped = json_decode(file_get_contents(__DIR__ . "/skipped.json"));

        $testProviderArray = [];
        foreach ($testCases as $testCase) {
            if (in_array(basename($testCase), $skipped)) {
                continue;
            }
            $testProviderArray[basename($testCase)] = [$testCase, $testCase . ".tokens"];
        }

        return $testProviderArray;
    }

    /**
     * @dataProvider lexicalSpecProvider
     */
    public function testSpecTokenClassificationAndLength($testCaseFile, $expectedTokensFile) {
        $lexer = \Microsoft\PhpParser\TokenStreamProviderFactory::GetTokenStreamProvider(file_get_contents($testCaseFile));
        $tokensArray = $lexer->getTokensArray();
        $tokens = str_replace("\r\n", "\n", json_encode($tokensArray, JSON_PRETTY_PRINT));
        file_put_contents($expectedTokensFile, $tokens);
        foreach ($tokensArray as $child) {
            if ($child instanceof Token) {
                $this->assertNotEquals(\Microsoft\PhpParser\TokenKind::Unknown, $child->kind, "input: $testCaseFile\r\nexpected: $expectedTokensFile");
                $this->assertNotEquals(\Microsoft\PhpParser\TokenKind::SkippedToken, $child->kind, "input: $testCaseFile\r\nexpected: $expectedTokensFile");
                $this->assertNotEquals(\Microsoft\PhpParser\TokenKind::MissingToken, $child->kind, "input: $testCaseFile\r\nexpected: $expectedTokensFile");
            }
        }
//        $tokens = str_replace("\r\n", "\n", json_encode($tokens, JSON_PRETTY_PRINT));
//        $this->assertEquals($expectedTokens, $tokens, "input: $testCaseFile\r\nexpected: $expectedTokensFile");
    }

    public function lexicalSpecProvider() {
        $testCases = glob(__dir__ . "/cases/php-langspec/**/*.php");

        $testProviderArray = [];
        foreach ($testCases as $testCase) {
            $testProviderArray[basename($testCase)] = [$testCase, $testCase . ".tree"];
        }

        return $testProviderArray;
    }
}
