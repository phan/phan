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

    public function testBasicNextLineSuppression(): void
    {
        $php_file = $this->createTestFile('test.php', <<<'PHP'
<?php
$x = "string" + 5;
PHP
        );

        $issues = [[
            'check_name' => 'PhanTypeInvalidLeftOperandOfAdd',
            'location' => ['path' => $php_file, 'lines' => ['begin' => 2, 'end' => 2]],
        ]];

        $this->runTool($issues);

        $result = file_get_contents($php_file);
        $this->assertStringContainsString('// @phan-suppress-next-line PhanTypeInvalidLeftOperandOfAdd', $result);
        $this->assertStringContainsString('$x = "string" + 5;', $result);
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
}
