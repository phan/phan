<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the add_suppressions.php tool
 */
class AddSuppressionsTest extends TestCase
{
    private string $tool_path;
    private string $test_dir;

    protected function setUp(): void
    {
        $this->tool_path = __DIR__ . '/../../tool/add_suppressions.php';
        $this->test_dir = sys_get_temp_dir() . '/phan_suppress_test_' . uniqid();
        mkdir($this->test_dir);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->rmdirRecursive($this->test_dir);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestFile(string $filename, string $content): string
    {
        $path = $this->test_dir . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    private function runTool(array $json_issues, array $args = []): string
    {
        $json_file = $this->test_dir . '/issues.json';
        file_put_contents($json_file, json_encode($json_issues));

        $cmd = sprintf(
            'php %s --from-json %s %s 2>&1',
            escapeshellarg($this->tool_path),
            escapeshellarg($json_file),
            implode(' ', array_map('escapeshellarg', $args))
        );

        return shell_exec($cmd) ?? '';
    }

    /**
     * Run the tool with a checkstyle XML file, returning combined stdout+stderr output.
     * $exit_code is set to the process exit code.
     */
    private function runToolWithCheckstyle(string $xml, array $args = [], int &$exit_code = 0): string
    {
        $xml_file = $this->test_dir . '/issues.xml';
        file_put_contents($xml_file, $xml);

        $cmd = sprintf(
            'php %s --from-checkstyle %s %s 2>&1; echo "EXIT:$?"',
            escapeshellarg($this->tool_path),
            escapeshellarg($xml_file),
            implode(' ', array_map('escapeshellarg', $args))
        );

        $output = shell_exec($cmd) ?? '';
        if (preg_match('/EXIT:(\d+)$/', rtrim($output), $m)) {
            $exit_code = (int)$m[1];
            $output = substr($output, 0, strrpos($output, "\nEXIT:") + 1);
            $output = rtrim($output, "\n");
        }
        return $output;
    }

    public function testBasicNextLineSuppression(): void
    {
        // Lines over 80 chars get put on the next line
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
$x = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" + 5;
PHP
        );

        $issues = [[
            'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
            'location' => ['path' => $php_file, 'lines' => ['begin' => 2, 'end' => 2]],
        ]];

        $this->runTool($issues);

        $result = file_get_contents($php_file);
        $this->assertStringContainsString('// @phan-suppress-next-line PhanTypeInvalidLeftOperandOfAdd', $result);
        $this->assertStringContainsString('$x = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" + 5;', $result);
    }

    public function testFunctionLevelSuppression(): void
    {
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
function test() {
    $x = "a" + 1;
    $y = "b" + 2;
    $z = "c" + 3;
}
PHP
        );

        $issues = [
            [
                'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
                'location' => ['path' => $php_file, 'lines' => ['begin' => 3, 'end' => 3]],
            ],
            [
                'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
                'location' => ['path' => $php_file, 'lines' => ['begin' => 4, 'end' => 4]],
            ],
            [
                'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
                'location' => ['path' => $php_file, 'lines' => ['begin' => 5, 'end' => 5]],
            ],
        ];

        $this->runTool($issues);

        $result = file_get_contents($php_file);
        $this->assertStringContainsString('* @suppress PhanTypeInvalidLeftOperandOfAdd', $result);
        // Should NOT have line-level suppressions
        $this->assertStringNotContainsString('@phan-suppress-next-line', $result);
        $this->assertStringNotContainsString('@phan-suppress-current-line', $result);
    }

    public function testDryRunMode(): void
    {
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
$x = "string" + 5;
PHP
        );

        $original_content = file_get_contents($php_file);

        $issues = [[
            'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
            'location' => ['path' => $php_file, 'lines' => ['begin' => 2, 'end' => 2]],
        ]];

        $output = $this->runTool($issues, ['--dry-run']);

        // File should not be modified
        $this->assertEquals($original_content, file_get_contents($php_file));

        // Output should indicate dry-run
        $this->assertStringContainsString('Dry-run mode', $output);
    }

    public function testNeverSuppressConfig(): void
    {
        $config_file = $this->test_dir . '/suppress_config.php';
        file_put_contents($config_file, <<<'PHP'
<?php
return [
    'never_suppress' => ['PhanTypeInvalidLeftOperandOfAdd'],
];
PHP
        );

        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
$x = "string" + 5;
PHP
        );

        $original_content = file_get_contents($php_file);

        $issues = [[
            'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
            'location' => ['path' => $php_file, 'lines' => ['begin' => 2, 'end' => 2]],
        ]];

        $this->runTool($issues, ['--config', $config_file]);

        // File should not be modified (issue type is in never_suppress)
        $this->assertEquals($original_content, file_get_contents($php_file));
    }

    public function testFileLevelSuppression(): void
    {
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
function f1() { }
function f2() { }
function f3() { }
function f4() { }
function f5() { }
function f6() { }
function f7() { }
function f8() { }
function f9() { }
function f10() { }
function f11() { }
PHP
        );

        $issues = [];
        for ($i = 2; $i <= 12; $i++) {
            $issues[] = [
                'check_name' => 'PhanUnreferencedFunction',
                'location' => ['path' => $php_file, 'lines' => ['begin' => $i, 'end' => $i]],
            ];
        }

        $this->runTool($issues);

        $result = file_get_contents($php_file);
        $this->assertStringContainsString('// @phan-file-suppress PhanUnreferencedFunction', $result);
    }

    public function testCheckstyleBasicSuppression(): void
    {
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
$x = "string" + 5;
PHP
        );

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="3.0.0">
  <file name="{$php_file}">
    <error line="2" severity="error" message="..." source="PhanTypeInvalidLeftOperandOfAdd"/>
  </file>
</checkstyle>
XML;

        $exit_code = 0;
        $this->runToolWithCheckstyle($xml, [], $exit_code);

        $this->assertSame(0, $exit_code);
        $result = file_get_contents($php_file);
        $this->assertStringContainsString('@phan-suppress', $result);
        $this->assertStringContainsString('PhanTypeInvalidLeftOperandOfAdd', $result);
    }

    public function testCheckstyleInvalidXmlExitsNonZero(): void
    {
        $exit_code = 0;
        $output = $this->runToolWithCheckstyle('this is not xml', [], $exit_code);

        $this->assertNotSame(0, $exit_code);
        $this->assertStringContainsString('Failed to parse checkstyle XML', $output);
    }

    public function testCheckstyleAndJsonTogetherExitsNonZero(): void
    {
        $json_file = $this->test_dir . '/issues.json';
        file_put_contents($json_file, '[]');
        $xml_file = $this->test_dir . '/issues.xml';
        file_put_contents($xml_file, '<checkstyle/>');

        $cmd = sprintf(
            'php %s --from-json %s --from-checkstyle %s 2>&1; echo "EXIT:$?"',
            escapeshellarg($this->tool_path),
            escapeshellarg($json_file),
            escapeshellarg($xml_file)
        );
        $output = shell_exec($cmd) ?? '';
        preg_match('/EXIT:(\d+)$/', rtrim($output), $m);
        $exit_code = (int)($m[1] ?? 0);

        $this->assertNotSame(0, $exit_code);
        $this->assertStringContainsString('Cannot use both', $output);
    }
}
