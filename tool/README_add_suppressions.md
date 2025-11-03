# Phan Suppression Auto-Fixer

Automatically adds `@phan-suppress` annotations to code based on Phan issue output.

## Features

- **Intelligent Suppression Selection**: Automatically chooses between line-level, function-level, and file-level suppressions based on issue patterns
- **Line Length Constraint Handling**: Respects maximum line length limits
- **Issue Grouping**: Groups multiple occurrences of the same issue type within functions
- **Dry-Run Mode**: Preview changes before applying
- **Interactive Mode**: Confirm each file before making changes
- **Configurable Behavior**: Customize thresholds and suppression strategies

## Usage

### Basic Usage

```bash
# Generate suppressions for all Phan issues
./phan --output-mode json | php tool/add_suppressions.php

# Dry-run to preview changes
./phan --output-mode json | php tool/add_suppressions.php --dry-run

# Interactive mode with confirmation
./phan --output-mode json | php tool/add_suppressions.php --interactive

# Read from JSON file
./phan --output-mode json --no-progress-bar > issues.json
php tool/add_suppressions.php --from-json issues.json
```

### Command-Line Options

- `--dry-run` - Show what would be changed without modifying files
- `--interactive` - Confirm each file before making changes
- `--from-json FILE` - Read issues from JSON file instead of stdin
- `--config FILE` - Load configuration from file (default: `.phan/suppress_config.php`)
- `--verbose` - Show detailed output
- `--help` - Show help message

## Configuration

Create a configuration file at `.phan/suppress_config.php`:

```php
<?php

return [
    // Minimum occurrences before using function-level suppression
    'function_threshold' => 3,

    // Minimum occurrences before using file-level suppression
    'file_threshold' => 10,

    // Maximum line length
    'max_line_length' => 120,

    // Issue types to never suppress
    'never_suppress' => [
        'PhanUndeclaredVariable',
        'PhanTypeMismatchReturn',
    ],

    // Issue types to always file-suppress
    'always_file_suppress' => [
        'PhanUnreferencedFunction',
    ],

    // Enable verbose output
    'verbose' => false,
];
```

## Suppression Types

### Line-Level Suppressions

**Next-Line** (default):
```php
// @phan-suppress-next-line PhanTypeInvalidLeftOperandOfAdd
$result = "string" + 5;
```

**Current-Line** (for short lines):
```php
$x = "str" + 1; // @phan-suppress-current-line PhanTypeInvalidLeftOperandOfAdd
```

### Function-Level Suppressions

Used when 3+ occurrences of the same issue type appear in a function:

```php
/**
 * @suppress PhanTypeInvalidLeftOperandOfAdd
 */
function example() {
    $x = "a" + 1;
    $y = "b" + 2;
    $z = "c" + 3;
}
```

### File-Level Suppressions

Used when 10+ occurrences of the same issue type appear in a file:

```php
<?php
// @phan-file-suppress PhanUnreferencedFunction

function helper1() { }
function helper2() { }
// ... many more unreferenced functions
```

## How It Works

1. **Read Issues**: Parses Phan JSON output
2. **Group Issues**: Groups by file, function, and issue type
3. **Analyze Patterns**:
   - If 10+ occurrences file-wide → file-level suppression
   - If 3+ occurrences in a function → function-level suppression
   - Otherwise → line-level suppression
4. **Apply Suppressions**: Uses byte-offset-based file edits for safe modifications

## Best Practices

### When to Use This Tool

- **Large Legacy Codebases**: Quickly suppress existing issues to establish a baseline
- **Type Migration**: Suppress known issues while gradually improving type safety
- **Test Files**: Suppress unreferenced function warnings in test helpers

### When NOT to Use This Tool

- **New Code**: Fix issues properly instead of suppressing
- **Critical Issues**: Never auto-suppress serious issues like `PhanUndeclaredVariable`
- **Short Files**: Manually add suppressions for better understanding

### Recommended Workflow

1. **Baseline**: Run tool with `--dry-run` to preview changes
2. **Configure**: Add issue types you want to fix manually to `never_suppress`
3. **Apply**: Run tool to suppress remaining issues
4. **Fix Gradually**: Remove suppressions as you fix underlying issues
5. **Prevent Regressions**: Use Phan in CI to prevent new issues

## Examples

### Suppress All Issues in a Single File

```bash
./phan -n --output-mode json path/to/file.php | php tool/add_suppressions.php
```

### Suppress Only Specific Issue Types

```bash
./phan --output-mode json | jq '. | map(select(.check_name | startswith("PhanUnreferenced")))' | php tool/add_suppressions.php
```

### Preview Changes for a Specific Directory

```bash
./phan --output-mode json --directory src/ | php tool/add_suppressions.php --dry-run --verbose
```

## Troubleshooting

### "File not found" Errors

The tool needs absolute paths. Use:
```bash
./phan --output-mode json --absolute-paths | php tool/add_suppressions.php
```

### Suppressions Not Working

Verify the suppression was added correctly:
```bash
./phan -n path/to/file.php
```

If issues still appear, the suppression format may be incorrect. Check that:
- Issue type name matches exactly (case-sensitive)
- Suppression is on the correct line
- No typos in the annotation

### Line Too Long Errors

The tool automatically uses next-line suppressions when adding current-line would exceed `max_line_length`. Adjust this in your config file if needed.

## Contributing

Enhancements welcome! Consider adding:
- Support for consolidating multiple suppressions on the same line
- Detection of existing suppressions to avoid duplicates
- Smart PHPDoc block merging when function already has documentation
- Support for class-level suppressions

## License

Same as Phan (MIT)
