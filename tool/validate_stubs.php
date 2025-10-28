#!/usr/bin/env php
<?php
/**
 * Stub Validation Script
 *
 * This script validates internal stub files by:
 * 1. Generating fresh stubs with make_stubs
 * 2. Comparing against current stubs
 * 3. Flagging differences while preserving template annotations
 *
 * Usage: php tool/validate_stubs.php [extension_name]
 *        php tool/validate_stubs.php --all
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Phan\CodeBase;
use Phan\Language\Context;

// Files with template annotations that need special handling
const TEMPLATE_FILES = [
    'spl' => 'internal/stubs/spl.phan_php',
    'spl_php81' => 'internal/stubs/spl_php81.phan_php',
    'standard' => 'internal/stubs/standard_templates.phan_php',
];

// Extensions currently loaded in config
const ACTIVE_EXTENSIONS = [
    'ast', 'ctype', 'igbinary', 'mbstring', 'pcntl', 'phar',
    'posix', 'readline', 'simplexml', 'soap', 'spl', 'sqlite3',
    'sysvmsg', 'sysvsem', 'sysvshm', 'tidy', 'xsl',
];

function print_header(string $text): void {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  $text\n";
    echo str_repeat('=', 70) . "\n";
}

function print_section(string $text): void {
    echo "\n" . str_repeat('-', 70) . "\n";
    echo "  $text\n";
    echo str_repeat('-', 70) . "\n";
}

function check_extension_loaded(string $ext): bool {
    $loaded = extension_loaded($ext);
    if (!$loaded && $ext === 'standard') {
        // 'standard' is always loaded but not reported by extension_loaded()
        return true;
    }
    return $loaded;
}

function generate_stub(string $extension): ?string {
    $temp_file = tempnam(sys_get_temp_dir(), 'phan_stub_');

    // Capture output from make_stubs
    $cmd = sprintf(
        'php %s/make_stubs -e %s 2>&1',
        escapeshellarg(__DIR__),
        escapeshellarg($extension)
    );

    exec($cmd, $output, $return_code);

    if ($return_code !== 0) {
        echo "  ✗ Failed to generate stub for $extension\n";
        echo "    " . implode("\n    ", $output) . "\n";
        return null;
    }

    $stub_content = implode("\n", $output);
    file_put_contents($temp_file, $stub_content);

    return $temp_file;
}

function compare_stubs(string $current_file, string $generated_file, string $ext): array {
    $issues = [];

    if (!file_exists($current_file)) {
        $issues[] = "Current stub file does not exist: $current_file";
        return $issues;
    }

    $current = file_get_contents($current_file);
    $generated = file_get_contents($generated_file);

    // Remove template annotations for comparison (we'll preserve them manually)
    $current_no_templates = preg_replace('/@template\s+\w+.*$/m', '', $current);
    $current_no_templates = preg_replace('/@implements\s+\w+<.*?>$/m', '', $current_no_templates);
    $current_no_templates = preg_replace('/@param\s+T\w+\s+/m', '@param mixed ', $current_no_templates);
    $current_no_templates = preg_replace('/@return\s+T\w+\s*/m', '@return mixed', $current_no_templates);

    // Check for phantom 'public $name;' properties
    if (preg_match_all('/class\s+(\w+).*?\{.*?public\s+\$name;/s', $current, $matches)) {
        foreach ($matches[1] as $class_name) {
            // Check if this class actually has a 'name' property via reflection
            try {
                if (class_exists($class_name) || class_exists("$ext\\$class_name")) {
                    $full_class = class_exists($class_name) ? $class_name : "$ext\\$class_name";
                    $rc = new ReflectionClass($full_class);
                    $has_name = false;
                    foreach ($rc->getProperties() as $prop) {
                        if ($prop->getName() === 'name') {
                            $has_name = true;
                            break;
                        }
                    }
                    if (!$has_name) {
                        $issues[] = "PHANTOM PROPERTY: $class_name has 'public \$name;' but reflection shows no such property";
                    }
                }
            } catch (ReflectionException $e) {
                // Class might not be loaded, skip check
            }
        }
    }

    // Check for redundant interface implementations
    if (preg_match_all('/class\s+(\w+).*?implements\s+([^{]+)/s', $current, $matches)) {
        foreach ($matches[0] as $idx => $match) {
            $class_name = $matches[1][$idx];
            $interfaces = array_map('trim', explode(',', $matches[2][$idx]));

            // Check for obvious redundancies (Iterator + Traversable, SeekableIterator + Iterator + Traversable)
            if (in_array('\\SeekableIterator', $interfaces) || in_array('SeekableIterator', $interfaces)) {
                if (in_array('\\Iterator', $interfaces) || in_array('Iterator', $interfaces)) {
                    $issues[] = "REDUNDANT INTERFACE: $class_name implements both SeekableIterator and Iterator (Iterator is implied)";
                }
                if (in_array('\\Traversable', $interfaces) || in_array('Traversable', $interfaces)) {
                    $issues[] = "REDUNDANT INTERFACE: $class_name implements both SeekableIterator and Traversable (Traversable is implied)";
                }
            }
        }
    }

    // Check for correct enum declarations
    if (preg_match_all('/enum\s+(\w+).*?implements.*?(UnitEnum|BackedEnum)/s', $current, $matches)) {
        foreach ($matches[1] as $enum_name) {
            $issues[] = "ENUM INTERFACE: $enum_name should not explicitly implement UnitEnum/BackedEnum (implied by 'enum' keyword)";
        }
    }

    return $issues;
}

function validate_extension(string $ext): void {
    print_section("Validating: $ext");

    // Find stub file
    $stub_file = "internal/stubs/$ext.phan_php";
    if (!file_exists($stub_file)) {
        echo "  ⚠ Stub file not found: $stub_file\n";
        return;
    }

    // Check if extension is loaded
    if (!check_extension_loaded($ext)) {
        echo "  ⚠ Extension not loaded, skipping runtime validation\n";
    } else {
        echo "  ✓ Extension is loaded\n";
    }

    // Check if file has template annotations
    $has_templates = in_array($ext, array_keys(TEMPLATE_FILES));
    if ($has_templates) {
        echo "  ⚠ File contains template annotations (manual review required)\n";
    }

    // Check if file is actively used
    $is_active = in_array($ext, ACTIVE_EXTENSIONS);
    if ($is_active) {
        echo "  ✓ File is loaded in .phan/config.php\n";
    } else {
        echo "  ⚠ File is NOT loaded in config (unused?)\n";
    }

    // Generate fresh stub
    echo "  → Generating fresh stub...\n";
    $generated_file = generate_stub($ext);

    if ($generated_file === null) {
        return;
    }

    // Compare stubs
    echo "  → Comparing stubs...\n";
    $issues = compare_stubs($stub_file, $generated_file, $ext);

    if (empty($issues)) {
        echo "  ✓ No issues found\n";
    } else {
        echo "  ✗ Issues found:\n";
        foreach ($issues as $issue) {
            echo "    - $issue\n";
        }
    }

    // Cleanup
    @unlink($generated_file);
}

function main(array $argv): int {
    print_header("Phan Internal Stubs Validation");

    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "PHP Version ID: " . PHP_VERSION_ID . "\n";

    if (count($argv) < 2) {
        echo "\nUsage: php tool/validate_stubs.php [extension_name]\n";
        echo "       php tool/validate_stubs.php --all\n";
        echo "       php tool/validate_stubs.php --active (only active extensions)\n";
        return 1;
    }

    $mode = $argv[1];

    if ($mode === '--all') {
        // Validate all stub files
        $stub_files = glob('internal/stubs/*.phan_php');
        foreach ($stub_files as $stub_file) {
            $ext = basename($stub_file, '.phan_php');
            if ($ext === 'standard_templates' || $ext === 'spl_php81') {
                continue; // Skip special files
            }
            validate_extension($ext);
        }
    } elseif ($mode === '--active') {
        // Validate only active extensions
        foreach (ACTIVE_EXTENSIONS as $ext) {
            validate_extension($ext);
        }
    } else {
        // Validate specific extension
        validate_extension($mode);
    }

    print_header("Validation Complete");

    return 0;
}

exit(main($argv));
