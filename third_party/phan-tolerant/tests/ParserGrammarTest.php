<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\DiagnosticsProvider;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\AssertionFailedError;

require_once __DIR__ . '/CallbackTestListener.php';

class ParserGrammarTest extends TestCase {
    private $expectedTokensFile;
    private $expectedDiagnosticsFile;
    private $tokens;
    private $diagnostics;
    public function run(?TestResult $result = null) : TestResult {
        if (!isset($GLOBALS["GIT_CHECKOUT_PARSER"])) {
            $GLOBALS["GIT_CHECKOUT_PARSER"] = true;
            exec("git -C " . dirname(self::FILE_PATTERN) . " checkout *.php.tree *.php.diag");
        }

        $result->addListener(new CallbackTestListener(function (Test $test) {
            if (isset($test->expectedTokensFile) && isset($test->tokens)) {
                file_put_contents($test->expectedTokensFile, str_replace("\r\n", "\n", $test->tokens));
            }
            if (isset($test->expectedDiagnosticsFile) && isset($test->diagnostics)) {
                file_put_contents($test->expectedDiagnosticsFile, str_replace("\r\n", "\n", $test->diagnostics));
            }
        }));

        $result = parent::run($result);
        return $result;
    }

    /**
     * @dataProvider treeProvider
     */
    public function testOutputTreeClassificationAndLength($testCaseFile, $expectedTokensFile, $expectedDiagnosticsFile) {
        $this->expectedTokensFile = $expectedTokensFile;
        $this->expectedDiagnosticsFile = $expectedDiagnosticsFile;

        $fileContents = file_get_contents($testCaseFile);
        if (!file_exists($expectedTokensFile)) {
            file_put_contents($expectedTokensFile, $fileContents);
            exec("git add " . $expectedTokensFile);
        }

        if (!file_exists($expectedDiagnosticsFile)) {
            file_put_contents($expectedDiagnosticsFile, $fileContents);
            exec("git add " . $expectedDiagnosticsFile);
        }

        $parser = new \Microsoft\PhpParser\Parser();
        $sourceFileNode = $parser->parseSourceFile($fileContents);

        $expectedTokens = str_replace("\r\n", "\n", file_get_contents($expectedTokensFile));
        $expectedDiagnostics = str_replace("\r\n", "\n", file_get_contents($expectedDiagnosticsFile));

        $GLOBALS["SHORT_TOKEN_SERIALIZE"] = true;
        $tokens = str_replace("\r\n", "\n", json_encode($sourceFileNode, JSON_PRETTY_PRINT));
        $diagnostics = str_replace("\r\n", "\n", json_encode(\Microsoft\PhpParser\DiagnosticsProvider::getDiagnostics($sourceFileNode), JSON_PRETTY_PRINT));
        $GLOBALS["SHORT_TOKEN_SERIALIZE"] = false;

        $this->tokens = $tokens;
        $this->diagnostics = $diagnostics;

        $tokensOutputStr = "input doc:\r\n$fileContents\r\n\r\ninput: $testCaseFile\r\nexpected: $expectedTokensFile";
        $diagnosticsOutputStr = "input doc:\r\n$fileContents\r\n\r\ninput: $testCaseFile\r\nexpected: $expectedDiagnosticsFile";

        $this->assertEquals($expectedTokens, $tokens, $tokensOutputStr);
        $this->assertEquals($expectedDiagnostics, $diagnostics, $diagnosticsOutputStr);
    }

    const FILE_PATTERN = __DIR__ . "/cases/parser/*";
    const PHP74_FILE_PATTERN = __DIR__ . "/cases/parser74/*";
    const PHP80_FILE_PATTERN = __DIR__ . "/cases/parser80/*";
    const PHP81_FILE_PATTERN = __DIR__ . "/cases/parser81/*";

    const PATTERNS_FOR_MINIMUM_PHP_VERSION = [
        [70400, self::PHP74_FILE_PATTERN],
        [80000, self::PHP80_FILE_PATTERN],
        [80100, self::PHP81_FILE_PATTERN],
    ];

    public function treeProvider() {
        $testCases = glob(self::FILE_PATTERN . ".php");
        $skipped = json_decode(file_get_contents(__DIR__ . "/skipped.json"));

        $testProviderArray = [];
        foreach ($testCases as $testCase) {
            if (in_array(basename($testCase), $skipped)) {
                continue;
            }
            $testProviderArray[basename($testCase)] = [$testCase, $testCase . ".tree", $testCase . ".diag"];
        }

        foreach (self::PATTERNS_FOR_MINIMUM_PHP_VERSION as list($minVersionId, $filePattern)) {
            if (PHP_VERSION_ID >= $minVersionId) {
                $testCases = glob($filePattern . ".php");
                foreach ($testCases as $testCase) {
                    $testProviderArray[basename($testCase)] = [$testCase, $testCase . ".tree", $testCase . ".diag"];
                }
            }
        }

        return $testProviderArray;
    }

    /**
     * @dataProvider outTreeProvider
     */
    public function testSpecOutputTreeClassificationAndLength($testCaseFile, $expectedTreeFile) {
        $parser = new \Microsoft\PhpParser\Parser();
        $sourceFile = $parser->parseSourceFile(file_get_contents($testCaseFile));
        $tokens = str_replace("\r\n", "\n", json_encode($sourceFile, JSON_PRETTY_PRINT));
        file_put_contents($expectedTreeFile, $tokens);

        $this->assertSame([], DiagnosticsProvider::getDiagnostics($sourceFile));
    }

    public function outTreeProvider() {
        $testCases = glob(__DIR__ . "/cases/php-langspec/**/*.php");
        $skipped = json_decode(file_get_contents(__DIR__ . "/skipped.json"));

        $testProviderArray = [];
        foreach ($testCases as $case) {
            if (in_array(basename($case), $skipped)) {
                continue;
            }
            $testProviderArray[basename($case)] = [$case, $case . ".tree"];
        }

        return $testProviderArray;
    }
}
