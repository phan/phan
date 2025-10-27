#!/usr/bin/env php
<?php declare(strict_types=1);
use ast\flags;

/**
 * AST Dumper - Dumps PHP Abstract Syntax Tree
 *
 * Supports two output modes:
 * - Visual mode (default): Colorful, human-readable tree structure
 * - JSON mode: Machine-readable JSON output for scripting
 */

// Check for CLI usage
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line\n");
    exit(1);
}

// Check if ast extension is loaded
if (!extension_loaded('ast')) {
    fwrite(STDERR, "Error: The 'ast' extension is required. Install it with: pecl install ast\n");
    exit(1);
}

function get_flag_info(): array {
    static $info;
    if ($info !== null) {
        return $info;
    }
    foreach (ast\get_metadata() as $data) {
        if (empty($data->flags)) continue;
        $flagMap = [];
        foreach ($data->flags as $fullName) {
            $shortName = substr($fullName, strrpos($fullName, '\\') + 1);
            $flagMap[constant($fullName)] = $shortName;
        }
        $info[(int) $data->flagsCombinable][$data->kind] = $flagMap;
    }
    return $info;
}

function is_combinable_flag(int $kind): bool {
    return isset(get_flag_info()[1][$kind]);
}

function format_flags(int $kind, int $flags): string {
    list($exclusive, $combinable) = get_flag_info();
    if (isset($exclusive[$kind])) {
        $flagInfo = $exclusive[$kind];
        if (isset($flagInfo[$flags])) {
            return "{$flagInfo[$flags]} ($flags)";
        }
    } else if (isset($combinable[$kind])) {
        $flagInfo = $combinable[$kind];
        $names = [];
        foreach ($flagInfo as $flag => $name) {
            if ($flags & $flag) {
                $names[] = $name;
            }
        }
        if (!empty($names)) {
            return implode(" | ", $names) . " ($flags)";
        }
    }
    return (string) $flags;
}

// Parse command line options
$options = [
    'help' => false,
    'json' => false,
    'color' => posix_isatty(STDOUT),
    'version' => 120, // Latest AST version (120 for PHP 8.4+)
    'linenos' => true,
    'exclude-doc' => false,
];

$files = [];

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    if ($arg === '-h' || $arg === '--help') {
        $options['help'] = true;
    } elseif ($arg === '-j' || $arg === '--json') {
        $options['json'] = true;
        $options['color'] = false; // JSON output shouldn't be colored
    } elseif ($arg === '--color') {
        $options['color'] = true;
    } elseif ($arg === '--no-color') {
        $options['color'] = false;
    } elseif ($arg === '--no-linenos') {
        $options['linenos'] = false;
    } elseif ($arg === '--exclude-doc') {
        $options['exclude-doc'] = true;
    } elseif (preg_match('/^--version=(\d+)$/', $arg, $matches)) {
        $options['version'] = (int)$matches[1];
    } elseif ($arg[0] === '-') {
        fwrite(STDERR, "Unknown option: $arg\n");
        exit(1);
    } else {
        $files[] = $arg;
    }
}

// Show help if requested or no files provided
if ($options['help'] || empty($files)) {
    echo <<<HELP
Usage: {$argv[0]} [OPTIONS] <php-file>...

Options:
  -h, --help           Show this help message
  -j, --json           Output JSON instead of visual tree
  --color              Force colored output (default for TTY)
  --no-color           Disable colored output
  --no-linenos         Don't include line numbers in output
  --exclude-doc        Exclude docComment nodes from output
  --version=N          Parse with AST version N (default: 120)
                       Supported: 50, 60, 70, 80, 85, 90, 100, 110, 120

Examples:
  {$argv[0]} file.php                    # Visual dump
  {$argv[0]} --json file.php             # JSON output
  {$argv[0]} --version=110 file.php      # Use AST version 110
  {$argv[0]} --no-linenos file.php       # Hide line numbers

HELP;
    exit($options['help'] ? 0 : 1);
}

// ANSI color codes
class Colors {
    public static $enabled = true;

    const RESET = "\e[0m";
    const BOLD = "\e[1m";
    const DIM = "\e[2m";

    const RED = "\e[31m";
    const GREEN = "\e[32m";
    const YELLOW = "\e[33m";
    const BLUE = "\e[34m";
    const MAGENTA = "\e[35m";
    const CYAN = "\e[36m";
    const WHITE = "\e[37m";

    const BRIGHT_RED = "\e[91m";
    const BRIGHT_GREEN = "\e[92m";
    const BRIGHT_YELLOW = "\e[93m";
    const BRIGHT_BLUE = "\e[94m";
    const BRIGHT_MAGENTA = "\e[95m";
    const BRIGHT_CYAN = "\e[96m";

    public static function color(string $text, string $color): string {
        return self::$enabled ? $color . $text . self::RESET : $text;
    }

    public static function kind(string $text): string {
        return self::color($text, self::BOLD . self::CYAN);
    }

    public static function flag(string $text): string {
        return self::color($text, self::YELLOW);
    }

    public static function line(string $text): string {
        return self::color($text, self::DIM . self::WHITE);
    }

    public static function key(string $text): string {
        return self::color($text, self::GREEN);
    }

    public static function value(string $text): string {
        return self::color($text, self::BRIGHT_MAGENTA);
    }

    public static function null(string $text): string {
        return self::color($text, self::DIM . self::WHITE);
    }
}

Colors::$enabled = $options['color'];

/**
 * Convert AST to JSON-serializable array
 */
function ast_to_array($ast, bool $include_linenos = true, bool $exclude_doc = false): mixed {
    if ($ast instanceof ast\Node) {
        $result = [
            'kind' => ast\get_kind_name($ast->kind),
        ];

        if ($include_linenos) {
            $result['lineno'] = $ast->lineno;
            if (isset($ast->endLineno)) {
                $result['endLineno'] = $ast->endLineno;
            }
        }

        if (ast\kind_uses_flags($ast->kind) || $ast->flags != 0) {
            $result['flags'] = $ast->flags;
            if (function_exists('format_flags')) {
                $result['flags_formatted'] = format_flags($ast->kind, $ast->flags);
            }
        }

        $children = [];
        foreach ($ast->children as $key => $child) {
            if ($exclude_doc && $key === 'docComment') {
                continue;
            }
            $children[$key] = ast_to_array($child, $include_linenos, $exclude_doc);
        }

        if (!empty($children)) {
            $result['children'] = $children;
        }

        return $result;
    } else if ($ast === null) {
        return null;
    } else if (is_string($ast)) {
        return $ast;
    } else {
        return $ast;
    }
}

/**
 * Dump AST in colorful visual format
 */
function dump_ast_visual($ast, int $indent = 0, bool $include_linenos = true, bool $exclude_doc = false): void {
    $prefix = str_repeat('  ', $indent);

    if ($ast instanceof ast\Node) {
        $kind_name = ast\get_kind_name($ast->kind);
        $output = $prefix . Colors::kind($kind_name);

        // Add line numbers
        if ($include_linenos) {
            $line_info = " @ {$ast->lineno}";
            if (isset($ast->endLineno) && $ast->endLineno != $ast->lineno) {
                $line_info .= "-{$ast->endLineno}";
            }
            $output .= Colors::line($line_info);
        }

        // Add flags
        if (ast\kind_uses_flags($ast->kind) || $ast->flags != 0) {
            $flag_str = function_exists('format_flags')
                ? format_flags($ast->kind, $ast->flags)
                : (string)$ast->flags;
            $output .= ' ' . Colors::flag("[{$flag_str}]");
        }

        echo $output . "\n";

        // Dump children
        foreach ($ast->children as $key => $child) {
            if ($exclude_doc && $key === 'docComment') {
                continue;
            }

            echo $prefix . '  ' . Colors::key((string)$key) . ': ';

            if ($child === null) {
                echo Colors::null('null') . "\n";
            } elseif (is_scalar($child)) {
                echo Colors::value(var_export($child, true)) . "\n";
            } else {
                echo "\n";
                dump_ast_visual($child, $indent + 2, $include_linenos, $exclude_doc);
            }
        }
    } elseif ($ast === null) {
        echo $prefix . Colors::null('null') . "\n";
    } elseif (is_string($ast)) {
        echo $prefix . Colors::value('"' . addslashes($ast) . '"') . "\n";
    } else {
        echo $prefix . Colors::value(var_export($ast, true)) . "\n";
    }
}

// Process each file
$exit_code = 0;

foreach ($files as $filename) {
    // Check if file exists
    if (!file_exists($filename)) {
        fwrite(STDERR, "Error: File '$filename' does not exist\n");
        $exit_code = 1;
        continue;
    }

    // Read the file
    $code = file_get_contents($filename);
    if ($code === false) {
        fwrite(STDERR, "Error: Could not read file '$filename'\n");
        $exit_code = 1;
        continue;
    }

    // Parse the code into AST
    try {
        $ast = ast\parse_code($code, $options['version']);
    } catch (ParseError $e) {
        fwrite(STDERR, "Parse Error in '$filename': " . $e->getMessage() . "\n");
        $exit_code = 1;
        continue;
    }

    // Output based on mode
    if ($options['json']) {
        // JSON output mode
        $output = [
            'file' => $filename,
            'ast_version' => $options['version'],
            'ast' => ast_to_array($ast, $options['linenos'], $options['exclude-doc']),
        ];
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        // Visual output mode
        if (count($files) > 1) {
            echo "\n" . Colors::color(str_repeat('=', 70), Colors::BOLD . Colors::BLUE) . "\n";
            echo Colors::color("File: $filename", Colors::BOLD . Colors::WHITE) . "\n";
            echo Colors::color(str_repeat('=', 70), Colors::BOLD . Colors::BLUE) . "\n\n";
        } else {
            echo Colors::color("AST for: $filename", Colors::BOLD . Colors::WHITE) . "\n";
            echo Colors::color("AST Version: {$options['version']}", Colors::DIM . Colors::WHITE) . "\n";
            echo Colors::color(str_repeat('-', 70), Colors::DIM . Colors::WHITE) . "\n\n";
        }

        dump_ast_visual($ast, 0, $options['linenos'], $options['exclude-doc']);
    }
}

exit($exit_code);
