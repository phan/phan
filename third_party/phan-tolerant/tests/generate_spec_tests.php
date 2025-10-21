<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/


//$testCases = array('C:\src\php-investigations\tolerant-php-parser\tests\..\php-langspec\tests\traits\traits.phpt');
$sep = DIRECTORY_SEPARATOR;
$testCases = glob(__DIR__ . "{$sep}..{$sep}php-langspec{$sep}tests{$sep}**{$sep}*.phpt");
$outTestCaseDir = __DIR__ . "{$sep}cases{$sep}php-langspec{$sep}";
mkdir($outTestCaseDir);
file_put_contents($outTestCaseDir . "README.md", "Auto-generated from php/php-langspec tests");

foreach ($testCases as $idx=> $filename) {
    $myFile = file_get_contents($filename);

    $dirPrefix = $outTestCaseDir . basename(dirname($filename)) . $sep;
    $baseName = basename(basename($filename), ".phpt");
    mkdir($dirPrefix);

    $titleRegex = '/(?<=={15} |-{15} )\X*?(?= ={15}| -{15})(?=[\w\W]*--EXPECT)/';
    $codeRegex = '/(?<=={15}\\\n";|-{15}\\\n";)\X*?(?=echo "=* |echo "-* |--EXPECT)/';

    preg_match_all($titleRegex, $myFile, $titles);
    preg_match_all($codeRegex, $myFile, $codes);

    if (count($titles[0]) >= 1) {
        foreach ($titles[0] as $idx=>$title) {
            $code = prependCode($codes[0][$idx]);

            $fileToWrite = $dirPrefix . $baseName . "-" . pascalCase($title) . ".php";
            writeCodeFileArtifacts($fileToWrite, $code);
        }
    } else {
        $codeRegex = '/(?<=--FILE--)\X*?(?=--EXPECT)/';
        preg_match_all($codeRegex, $myFile, $codes);
        $code = prependCode($codes[0][0]);

        $fileToWrite = $dirPrefix . $baseName . ".php";
        writeCodeFileArtifacts($fileToWrite, $code);
    }
}

function pascalCase($str) {
    $str = preg_replace('/[^a-z0-9]+/i', ' ', $str);
    $str = ucwords($str);
    $str = str_replace(" ", "", $str);
    return $str;
}

function prependCode($code):string {
    if (!stristr($code, "<?php")) {
        $code = PHP_EOL . "<?php" . $code;
    }
    $code = "/* Auto-generated from php/php-langspec tests */" . $code;
    return $code;
}

function writeCodeFileArtifacts($fileToWrite, $code):void {
    file_put_contents($fileToWrite, $code);
    if (!file_exists($fileToWrite . ".tree")) {
        file_put_contents($fileToWrite . ".tree", $code);
    }
    if (!file_exists($fileToWrite . ".tokens")) {
        file_put_contents($fileToWrite . ".tokens", $code);
    }
}
