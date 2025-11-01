# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phan is a static analyzer for PHP that prefers to minimize false-positives. It attempts to prove incorrectness rather than correctness and has a comprehensive understanding of PHP's type system, including union types, generics, and array shapes.

## Branch Strategy

**IMPORTANT**: All new pull requests should target the **`v6`** branch, not `v5`.

- **v6**: Current development branch - target for all new PRs
- **v5**: Maintenance branch for bug fixes only

When creating PRs:
```bash
# Always create PR against v6
gh pr create --base v6 --title "Your PR title" --body "PR description"
```

## Essential Commands

### Building and Running Phan

```bash
# Run Phan on its own codebase (self-analysis)
./phan

# Run Phan with specific target PHP version
./phan --target-php-version 8.4

# Run on specific files without loading .phan/config.php (faster for quick tests)
./phan -n test1.php test2.php

# Run using the test config that phpunit uses
./phan -n -k tests/.phan_for_test/config.php test1.php test2.php
```

### Testing

```bash
# Run all unit tests
./vendor/bin/phpunit

# Run specific test suite
./tests/run_test <TestSuiteName>

# Common test suites:
./tests/run_test __FakeSelfTest            # Self-analysis test
./tests/run_test __FakeSelfFallbackTest    # Fallback parser test
./tests/run_test __FakeRewritingTest       # AST rewriting test
./tests/run_test PHP84Test                 # PHP 8.4 specific tests

# Run all integration tests
./tests/run_all_tests

# Run individual unit test file
./vendor/bin/phpunit tests/Phan/Language/Internal/PropertyMapTest.php

# Run specific test by pattern
./vendor/bin/phpunit --filter="testFiles.*filename\.php"
```

### Development Tools

```bash
# Analyze a single PHP file with AST dump
tool/dump_ast.php --json <file.php>

# Generate stubs for extensions
php tool/make_stubs
```

### Debugging Type Inference

To see what type Phan has inferred for a variable, use the `@phan-debug-var` annotation as an inline string (not a comment, due to php-ast limitations):

```php
function example() {
    $x = [1, 2, 3];
    '@phan-debug-var $x';  // Will output: PhanDebugAnnotation - $x has union type int[]

    $arr = ['key' => 'value'];
    '@phan-debug-var $arr[\'key\']';  // Can debug array elements and properties
}
```

This is extremely useful when:
- Investigating type inference issues
- Understanding why Phan reports unexpected warnings
- Verifying that type narrowing works correctly in conditional branches
- Debugging complex union types

## High-Level Architecture

### Core Components

1. **AST Processing Pipeline**
   - `src/Phan/AST/`: AST traversal and visitor implementations
   - `src/Phan/Parse/ParseVisitor.php`: Main parser that builds Phan's internal representation
   - Requires php-ast extension (1.1.3+ for PHP 8.4 support)
   - Fallback parser via `microsoft/tolerant-php-parser` (being phased out for PHP 8.4+)

2. **Type System**
   - `src/Phan/Language/Type/`: Core type representations
   - `src/Phan/Language/UnionType.php`: Union type implementation
   - Supports union types, generics (`@template`), array shapes (`array{key:type}`)
   - Type inference through control flow analysis

3. **Analysis Engine**
   - `src/Phan/Analysis/`: Various analysis visitors
   - `src/Phan/BlockAnalysisVisitor.php`: Main analysis visitor
   - `src/Phan/Analysis/ConditionVisitor.php`: Conditional type refinement
   - Multi-pass analysis with type inference improvements

4. **Code Base Representation**
   - `src/Phan/CodeBase.php`: Central registry of all code elements
   - `src/Phan/Language/Element/`: Classes, methods, properties, functions
   - `src/Phan/Language/FQSEN/`: Fully Qualified Structural Element Names

5. **Issue Reporting**
   - `src/Phan/Issue.php`: All issue type definitions
   - `src/Phan/IssueInstance.php`: Specific issue occurrences
   - Multiple output formats: text, json, codeclimate, checkstyle, etc.

6. **Plugin System**
   - `.phan/plugins/`: Built-in plugins
   - `src/Phan/Plugin/`: Plugin infrastructure
   - Plugins can hook into various analysis phases

### Key Design Patterns

1. **Visitor Pattern**: Extensively used for AST traversal
   - All visitors extend `src/Phan/AST/Visitor/KindVisitorImplementation.php`
   - Method naming: `visitXxx()` where Xxx is the AST node type

2. **Context Tracking**: Analysis context flows through visitors
   - `src/Phan/Language/Context.php`: Tracks scope, namespace, variables
   - Context is immutable and cloned when entering new scopes

3. **Union Types**: All types are union types internally
   - Even single types are wrapped in UnionType
   - Enables gradual type refinement through analysis

4. **Issue Suppression**: Multiple suppression mechanisms
   - `@suppress` annotations in PHPDoc
   - File-level suppressions with `@phan-file-suppress`
   - Config-based suppression

## Configuration

Phan configuration is in `.phan/config.php`. Key settings:

- `target_php_version`: PHP version to check compatibility against
- `directory_list`: Directories to parse for analysis
- `exclude_analysis_directory_list`: Parse but don't analyze (e.g., vendor/)
- `plugins`: Active plugins for additional checks
- `suppress_issue_types`: Issues to suppress globally

## Common Development Tasks

### Adding a New Issue Type

1. Define the issue in `src/Phan/Issue.php`
2. Add issue emission in appropriate visitor
3. Add test cases in `tests/files/`
4. Update documentation

### Creating a Plugin

1. Create plugin in `.phan/plugins/`
2. Extend `\Phan\PluginV3`
3. Implement required visitor methods
4. Add to config's plugin list

### Debugging Analysis Issues

1. Use `--debug` flag for verbose output
2. Add `var_dump()` in visitors (use `--allow-polyfill-parser` to avoid AST issues)
3. Use `tool/dump_ast.php` to inspect AST structure
4. Check context state with `$this->context->getDebugRepresentation()`

## Testing Workflow

### Test Structure
```bash
# Test files are organized in tests/files/src/ with corresponding expected output
tests/files/src/0540_invalid_method_name.php      # Test case
tests/files/expected/0540_invalid_method_name.php.expected  # Expected warnings

# Run specific test to see actual vs expected output
./vendor/bin/phpunit --filter="testFiles.*0540_invalid_method_name"
```

### Analyzing Test Failures
When tests fail, examine:
- **Expected output format**: Uses `%s` placeholders for file paths in `.expected` files
- **Line numbers**: Critical for matching expected warnings to actual code lines
- **Issue types**: Must match exactly (e.g., `PhanTypeInvalidCallableMethodName`)
- **Error messages**: Complete message text must match expected format

### Common Test Failure Patterns

- **Missing expected warnings**: Analysis logic not identifying the issue
- **Extra warnings (false positives)**: Analysis conditions too permissive
- **Wrong line numbers**: Using wrong line number source (`$node->lineno` vs `$context->getLineNumberStart()`)
- **Wrong issue types**: Using incorrect `Issue::` constant

### Debugging Strategies

```bash
# Isolate the problem
./vendor/bin/phpunit --filter="testFiles.*problem_test"
./phan -n -k tests/.phan_for_test/config.php tests/files/src/problem_test.php

# Compare expected vs actual
cat tests/files/expected/problem_test.php.expected
./phan -n -k tests/.phan_for_test/config.php tests/files/src/problem_test.php 2>&1 | diff - tests/files/expected/problem_test.php.expected
```

### Test-Driven Development

1. **Write test first**: Create test case with expected warnings
2. **Run test**: Confirm it fails with current implementation
3. **Implement fix**: Add analysis logic to emit expected warnings
4. **Verify fix**: Ensure test passes and no regressions
5. **Run full suite**: Check that other tests still pass

## Test Organization and Expected Files

### Directory Structure

- PHP version-specific tests go in `tests/phpXX_files/` directories
- Expected output files use `.expected` extension with `%s` for file paths
- PHP version-specific expected output: Use `.expectedXX` suffix (e.g., `.expected84` for PHP 8.4)

### Generating Expected Test Output Files

**CRITICAL: Understanding PHPUnit Test Behavior vs Manual Phan Execution**

There is a fundamental difference:

1. **Manual Phan execution** (`./phan --no-progress-bar tests/files/src/file.php`):
   - Uses `.phan/config.php` which loads ALL plugins
   - Output includes plugin warnings
   - **NEVER use this to generate .expected files!**

2. **PHPUnit test execution** (`./vendor/bin/phpunit --filter="testFiles.*file\.php"`):
   - Uses `tests/.phan_for_test/config.php` which has **NO plugins configured**
   - Output does NOT include plugin warnings
   - This is the correct way to generate expected files

**Correct Procedure:**

```bash
# 1. Switch to the appropriate PHP version
sudo newphp 81  # or 82, 83, 84, 85 depending on the test

# 2. Use PHPUnit's built-in mechanism to generate .expected files
PHAN_DUMP_NEW_TEST_EXPECTATION=1 ./vendor/bin/phpunit --filter="testFiles.*filename\.php"

# 3. This creates a .expected.new file
# Example: tests/files/expected/0299_binary_op.php.expected.new

# 4. Review the .new file to ensure it's correct
cat tests/files/expected/filename.php.expected.new

# 5. Move it to replace the old .expected file
mv tests/files/expected/filename.php.expected.new tests/files/expected/filename.php.expected
```

### Version-Specific Expected Files

The test framework uses `getFileForPHPVersion()` to select expected files:

```php
// On PHP 8.5, it checks in this order:
// 1. filename.php.expected85  (if exists, use this)
// 2. filename.php.expected84  (if exists, use this)
// ...
// 6. filename.php.expected    (fallback - always exists)
```

**When to Create Version-Specific Expected Files:**

1. **PHP behavior differences**: When Phan output genuinely differs between PHP versions
2. **PHP version-specific features**: When testing features only available in certain versions

**Common Mistakes to Avoid:**

1. ❌ Running `./phan` manually to generate expected output (includes plugin warnings)
2. ❌ Creating .expected files with full paths instead of `%s` placeholders
3. ❌ Forgetting to test on all PHP versions
4. ❌ Creating version-specific files for the wrong PHP version

## Type System Implementation

### Key Concepts

- All types are represented as `UnionType` internally
- Type narrowing happens in `ConditionVisitor` for conditional branches
- Mixed types have special handling for falsey value narrowing
- Template types require special handling in both detection and substitution

### Common Patterns

**Type Expansion:**
- Use `asExpandedTypesPreservingTemplate()` carefully
- For property assignments, use exact types to enforce strict compatibility
- Expanding types can incorrectly allow sibling types to be considered compatible

**Variable Tracking:**
- Check `isPossiblyUndefined()` when validating variable usage
- Context merging can introduce possibly-undefined states
- `AnnotatedUnionType` tracks undefined state through control flow

**Template Types:**
- Check both method signatures and PHPDoc return types for templates
- Substitution requires updating both the comment and method's union type
- Template changes may require updating test expectations

## PHP Version-Specific Features

### Adding New PHP Version Support

1. **Check AST changes:**
   - Use `tool/dump_ast.php` to inspect AST structure
   - Compare AST between PHP versions
   - Update visitors if new node types exist

2. **Update function signatures:**
   - Add new functions to `FunctionSignatureMap_phpXX_delta.php`
   - Keep alphabetical ordering in signature maps

3. **Add version-specific tests:**
   - Create test in `tests/phpXX_files/src/`
   - Add expected output in `tests/phpXX_files/expected/`

### Common Implementation Patterns

**Attribute Support:**
```php
// Pattern for detecting attributes
public function hasXxxAttribute(): bool
{
    foreach ($this->attribute_list as $attribute) {
        $fqsen = $attribute->getFQSEN();
        if ($fqsen->__toString() === '\\AttributeName') {
            return true;
        }
    }
    return false;
}
```

**Issue Checking in Visitors:**
```php
// Type guards before emitting issues
if (!($element instanceof ExpectedType)) {
    return;
}

// Check conditions that warrant the issue
if ($element->shouldWarn()) {
    $this->emitIssue(Issue::IssueType, ...);
}
```

### TolerantASTConverter (Fallback Parser)

The fallback parser must produce identical AST output to php-ast extension:
- Must handle PHP version-specific AST differences (e.g., exit() representation)
- Line numbers must match exactly
- Null vs empty array differences matter

Test with:
```bash
./vendor/bin/phpunit --filter="testFallbackFromParser"
```

## Important Notes

- **php-ast Extension Required**: Version 1.1.3+ needed for PHP 8.4 analysis
- **Memory Usage**: Large codebases may need `--memory-limit 2G` or more
- **Parallel Analysis**: Use `--processes N` for faster analysis
- **Self-Analysis**: Phan analyzes itself; run `./phan` for self-check
- **Use `phan -n`**: Test quicker when you don't need to test plugins
