# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phan is a static analyzer for PHP that prefers to minimize false-positives. It attempts to prove incorrectness rather than correctness and has a comprehensive understanding of PHP's type system, including union types, generics, and array shapes.

## Essential Commands

### Building and Running Phan

```bash
# Run Phan on its own codebase (self-analysis)
./phan --memory-limit 1G

# Run Phan with specific target PHP version
./phan --target-php-version 8.4

# Run with dead code detection
./phan --dead-code-detection

# Run with unused variable detection
./phan --unused-variable-detection

# Run with redundant condition detection
./phan --redundant-condition-detection

# Automatic fixing of certain issues
./phan --automatic-fix

# Check Phan version
./phan --version
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
```

### Development Tools

```bash
# Run Phan on one or more files without reading .phan/config.php
./phan -n test1.php test2.php

# Analyze a single PHP file with AST dump
./dump_ast.php <file.php>

# Interactive REPL for testing Phan internals
php tool/phan_repl_helpers.php

# Generate stubs for extensions
php tool/make_stubs

# Run syntax checks with multiple PHP versions
php phan --plugin InvokePHPNativeSyntaxCheckPlugin
```

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
   - File-level suppressions
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
3. Use `./dump_ast.php` to inspect AST structure
4. Check context state with `$this->context->getDebugRepresentation()`

### Debugging Failing Tests

When Phan tests fail, use this systematic approach to diagnose and fix issues:

#### 1. Understanding Test Structure
```bash
# Test files are organized in tests/files/src/ with corresponding expected output
tests/files/src/0540_invalid_method_name.php      # Test case
tests/files/expected/0540_invalid_method_name.php.expected  # Expected warnings

# Run specific test to see actual vs expected output
./vendor/bin/phpunit --filter="testFiles.*0540_invalid_method_name"
```

#### 2. Analyzing Test Failures
When tests fail, examine:
- **Expected output format**: Uses `%s` placeholders for file paths in `.expected` files
- **Line numbers**: Critical for matching expected warnings to actual code lines
- **Issue types**: Must match exactly (e.g., `PhanTypeInvalidCallableMethodName`)
- **Error messages**: Complete message text must match expected format

**Example failure analysis:**
```bash
# Failed assertion shows exact mismatch
Failed asserting that 'actual_output' matches PCRE pattern "/expected_regex/"

# Key patterns to identify:
- Missing expected warnings (test expects warnings that aren't generated)
- Extra warnings (test generates warnings not in expected output)
- Wrong line numbers (warnings on different lines than expected)
- Incorrect issue types (wrong PhanXxx error type)
```

#### 3. Common Test Failure Patterns

**Pattern 1: Missing Expected Warnings**
- **Cause**: Code changes broke the analysis that should emit warnings
- **Debug**: Check if the analysis visitor is being called for the relevant node types
- **Fix**: Ensure analysis logic correctly identifies the problematic patterns

**Pattern 2: Extra Warnings (False Positives)**
- **Cause**: Analysis is too broad and flagging valid code
- **Debug**: Check if conditions are too permissive
- **Fix**: Add more specific checks to avoid false positives

**Pattern 3: Wrong Line Numbers**
- **Cause**: Analysis is emitting warnings at wrong location
- **Debug**: Check `$node->lineno` vs `$context->getLineNumberStart()`
- **Fix**: Use appropriate line number source for the warning

**Pattern 4: Wrong Issue Types**
- **Cause**: Emitting wrong issue type for the condition
- **Debug**: Check `Issue::` constants being used
- **Fix**: Use correct issue type that matches expected output

#### 4. Debugging Strategies

**Strategy 1: Isolate the Problem**
```bash
# Run just the failing test to focus on the issue
./vendor/bin/phpunit --filter="testFiles.*problem_test"

# Run Phan directly on the test file to see raw output
./phan --target-php-version 8.4 tests/files/src/problem_test.php
```

**Strategy 2: Compare Expected vs Actual**
```bash
# View expected output
cat tests/files/expected/problem_test.php.expected

# Get actual output and compare
./phan tests/files/src/problem_test.php 2>&1 | diff - tests/files/expected/problem_test.php.expected
```

**Strategy 3: Trace Analysis Flow**
- Add debug output in relevant visitor methods
- Use `error_log()` to trace execution flow
- Check if the right visitor methods are being called for the AST nodes

**Strategy 4: Verify Context**
- Check current context scope and variables
- Verify node structure with `./dump_ast.php`
- Ensure proper parent-child relationships in AST

#### 5. Type System Issues

When dealing with type-related test failures:

**Understanding Type Flow:**
- Method return types can be `UnionType|bool|null`
- Calling code may expect only `UnionType|null`
- Add explicit type checks before method calls on union types

**Common Type Fixes:**
```php
// Before: Assumes $result is always UnionType
if ($result->hasRealTypeSet()) { ... }

// After: Defensive type checking
if ($result instanceof UnionType && $result->hasRealTypeSet()) { ... }
```

#### 6. Test-Driven Development Workflow

1. **Write test first**: Create test case with expected warnings
2. **Run test**: Confirm it fails with current implementation
3. **Implement fix**: Add analysis logic to emit expected warnings
4. **Verify fix**: Ensure test passes and no regressions
5. **Run full suite**: Check that other tests still pass

#### 7. Common Pitfalls

**Redundant Conditions:**
- Phan flags redundant type checks after earlier validation
- Solution: Remove redundant `is_string()` checks when type is already confirmed

**Control Flow Analysis:**
- Phan tracks type changes through conditional branches
- Consider all possible execution paths when adding type checks

**AST Node Handling:**
- Different node types (`AST_ARRAY_ELEM` vs `AST_VAR`) need different handling
- Extract actual values from array elements before processing

**Scope and Context:**
- Analysis context affects what variables and types are available
- Ensure proper context is passed to analysis methods

#### 8. Self-Analysis Warnings

When Phan reports warnings about its own code:
```bash
# Check specific warnings
./phan --target-php-version 8.4 2>&1 | grep "PhanTypeMismatch"

# Focus on specific files
./phan src/Phan/AST/UnionTypeVisitor.php 2>&1 | grep "PhanRedundantCondition"
```

Fix these by:
- Adding defensive type checking
- Removing redundant conditions after earlier validation
- Ensuring method signatures match actual usage

## Important Notes

- **php-ast Extension Required**: Version 1.1.3+ needed for PHP 8.4 analysis
- **Memory Usage**: Large codebases may need `--memory-limit 2G` or more
- **Parallel Analysis**: Use `--processes N` for faster analysis
- **Incremental Analysis**: Phan caches results; use `--force-polyfill-parser` to bypass cache
- **Self-Analysis**: Phan analyzes itself; run `./phan` for self-check

## Type System Implementation Details

### Mixed Type Narrowing

When implementing type narrowing for `mixed` types (e.g., after `empty()` checks), the type system must handle all possible falsey values correctly. The `UnionType::toNonTruthyTypeSet()` method has special handling for `MixedType` to return all falsey types (null, false, 0, "", "0", 0.0, []) rather than just null.

Key implementation points:
- `MixedType::asNonTruthyType()` returns `NullType` by design
- `UnionType::toNonTruthyTypeSet()` has special case handling for `MixedType`
- Must check for `NonEmptyMixedType` and `NonNullMixedType` variants
- Use `Type\` namespace prefix when these types aren't imported

### PHPUnit Test Compatibility

When writing tests that create Type instances, be aware of PHPUnit's global state serialization:

**PHP 8.3/8.4 Compatibility Issues:**
- `@runInSeparateProcess` causes stream handling errors in PHP 8.3+
- Type instances cannot be serialized (they have `__wakeup()` that throws)
- PHPUnit's global state backup will fail if Type instances are in static properties

**Solution:**
- Add `@backupStaticAttributes disabled` to test classes that may pollute static state with Type instances
- Avoid `@runInSeparateProcess` with PHP 8.3+ (causes "Stream does not support seeking" errors)
- `@backupGlobals disabled` is usually not necessary if static attributes are the issue

### Test Organization

- PHP version-specific tests go in `tests/phpXX_files/` directories
- Expected output files use `.expected` extension with `%s` for file paths
- PHP version-specific expected output: Use `.expectedXX` suffix (e.g., `.expected84` for PHP 8.4)
- Tests without plugin output should have empty expected files if no warnings are expected
- Use `./tests/run_test TestName` for integration tests
- Use `./vendor/bin/phpunit` for unit tests

## PHP Version-Specific Behavior and Testing

### AST Version 110/120 Support (PHP 8.4+)

**Key Changes:**
- AST version 120 represents `exit`/`die` as `AST_CALL` nodes instead of `AST_EXIT` nodes
- PHP 8.4 made `exit()` a real function with `never` return type
- TolerantASTConverter (fallback parser) must match php-ast extension behavior exactly

**Critical Implementation Details:**

1. **exit() Representation Varies by PHP Version:**
   ```php
   // On PHP 8.1-8.3 with AST 120: exit; produces AST_ARG_LIST with [null]
   // On PHP 8.4+ with AST 120: exit; produces AST_ARG_LIST with empty array []

   // TolerantASTConverter must check PHP_VERSION_ID:
   $arg_list_children = $expr_node !== null ? [$expr_node] :
       (\PHP_VERSION_ID >= 80400 ? [] : [null]);
   ```

2. **Function Signature Updates:**
   - Add to `FunctionSignatureMap_php84_delta.php` in 'changed' section:
     ```php
     'exit' => [
         'old' => ['', 'status='=>'string|int'],
         'new' => ['never', 'status='=>'string|int'],
     ],
     ```

3. **Config Setting to Avoid False Positives:**
   ```php
   // In .phan/config.php - allows exit() to be recognized on PHP < 8.4
   'ignore_undeclared_functions_with_known_signatures' => true,
   ```

4. **Version-Specific Test Expectations:**
   - Use `.expected` for PHP 8.1-8.3 behavior
   - Use `.expected84` for PHP 8.4-specific behavior
   - The `test.sh` script automatically selects the right file based on PHP version

### TolerantASTConverter Testing

The TolerantASTConverter fallback parser must produce identical AST output to the php-ast extension:

**Test Pattern:**
```bash
# Run TolerantASTConverter tests to ensure fallback parser matches php-ast
./vendor/bin/phpunit --filter="testFallbackFromParser"
```

**Common Issues:**
- **Line number mismatches**: Fallback parser may calculate line numbers differently for multi-line attributes
- **AST structure differences**: Must match php-ast extension exactly, including null vs empty array differences
- **Windows-specific issues**: Some tests only fail on Windows (AppVeyor) due to path or parser differences

### AppVeyor Configuration

AppVeyor runs Windows CI builds. Key notes:

**Configuration file:** `.appveyor.yml`

**Branch filtering:**
```yaml
branches:
  only:
    - v5
    - v6
```

**Disabling problematic tests:**
```yaml
# Disable PHP 8.4 temporarily if TolerantASTConverter has issues
# - PHP_EXT_VERSION: '8.4'
#   PHP_VERSION: '8.4.0'
#   ...
```

**Common AppVeyor Issues:**
- Symfony Console deprecation warnings on PHP 8.4 interfere with test output parsing
- TolerantASTConverter line number issues are more visible on Windows
- Can disable specific PHP versions while issues are being investigated

### CI and PR Management Workflow

**Typical workflow for feature branches:**

1. **Create feature branch from base:**
   ```bash
   git checkout -b feature-name base-branch
   ```

2. **When base branch gets merged to main branch:**
   ```bash
   # Update PR target branch
   gh pr edit PR_NUMBER --base v6

   # Rebase feature branch onto new target
   git fetch origin
   git rebase origin/v6

   # Force push rebased branch
   git push --force-with-lease
   ```

3. **Trigger CI if it doesn't auto-start:**
   ```bash
   git commit --allow-empty -m "Trigger CI"
   git push
   ```

4. **Monitor CI status:**
   ```bash
   gh pr checks PR_NUMBER
   gh pr view PR_NUMBER
   ```

**Merge strategies:**
- **Merge commit**: Best for feature branches - preserves history and makes features easy to revert
- **Rebase and merge**: Creates linear history but loses feature branch context
- **Squash and merge**: Loses individual commit history, use sparingly

### PHP 8.4 Feature Implementation Patterns

When adding new PHP 8.4 features:

1. **Check if AST changes are needed:**
   - Use `./dump_ast.php` to inspect AST structure
   - Compare AST between PHP versions
   - Update visitors in `KindVisitorImplementation.php` if new node types exist

2. **Update function signatures:**
   - Add new functions to `FunctionSignatureMap_php84_delta.php`
   - Add return types to `FunctionSignatureMapReal.php`
   - Keep alphabetical ordering in signature maps

3. **Add version-specific tests:**
   - Create test in `tests/php84_files/src/`
   - Add expected output in `tests/php84_files/expected/`
   - Use `.expected84` if behavior differs from earlier PHP versions

4. **Test across all PHP versions:**
   ```bash
   # Test on PHP 8.1, 8.2, 8.3, 8.4
   for ver in 81 82 83 84; do
       sudo newphp $ver
       ./vendor/bin/phpunit
   done
   ```

### Common PHP 8.4 Changes Implemented

**Property Hooks:**
- New AST node types: `AST_PROPERTY_HOOK`, `AST_PROPERTY_HOOK_SHORT_BODY`
- New property field: `hooks` in `AST_PROP_ELEM`
- Implementation: `PropertyHook` element class, validation in `ParseVisitor`

**#[Deprecated] Attribute:**
- Check both PHPDoc `@deprecated` and `#[Deprecated]` attribute
- Implementation in `HasAttributesTrait::hasDeprecatedAttribute()`
- Works on functions, methods, and class constants

**exit() as Function:**
- Changed from language construct to function with `never` return type
- AST representation changed in version 110/120
- Version-specific behavior in TolerantASTConverter

**New Without Parentheses:**
- Syntax: `new MyClass()->method()`
- Works automatically via php-ast, no special handling needed
- Type inference works correctly across method chains
