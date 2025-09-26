# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phan is a static analyzer for PHP that prefers to minimize false-positives. It attempts to prove incorrectness rather than correctness and has a comprehensive understanding of PHP's type system, including union types, generics, and array shapes.

**Current Focus**: PHP 8.4 Property Hooks Support - The codebase is being prepared to support PHP 8.4's new property hooks feature. See PHP84_PROPERTY_HOOKS_IMPLEMENTATION.md for detailed implementation plans.

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
./tests/run_all_tests.sh

# Run individual unit test file
./vendor/bin/phpunit tests/Phan/Language/PropertyHookTest.php
```

### Development Tools

```bash
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

## Performance Optimization: phan_helper Extension

Phan supports an optional native C extension (`phan_helper`) that provides significant performance improvements for critical operations:

### Key Optimizations
- **Object Deduplication**: 30x+ faster than PHP's `array_unique()` for Type objects
- **FQSEN Parsing**: 1.6x faster parsing of Fully Qualified Structural Element Names

### Implementation
- `src/Phan/Library/PhanHelper.php`: Wrapper class with automatic fallback to PHP implementations
- `src/Phan/Language/UnionType.php`: Uses extension for `getUniqueTypes()` - a critical hot path
- Extension only includes functions with proven performance benefits (many were removed after benchmarking showed PHP built-ins were faster)

### Usage
- Extension is optional - Phan works correctly without it
- To enable: Uncomment `extension=phan_helper.so` in `/etc/php8/conf.d/phan_helper.ini`
- Benchmark with: `php benchmarks/phan_helper_benchmark.php`

## PHP 8.4 Property Hooks Implementation

The codebase is being extended to support PHP 8.4 property hooks. Key areas being modified:

1. **AST Constants**: Adding `AST_PROPERTY_HOOK` and `AST_PROPERTY_HOOK_SHORT_BODY`
2. **Property Class**: Extended to store hook methods
3. **ParseVisitor**: Modified to detect and parse hooks
4. **Analysis**: Updated for get/set hook type checking
5. **New Issue Types**: Property hook-specific warnings

See `PHP84_PROPERTY_HOOKS_IMPLEMENTATION.md` for detailed implementation guide.

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
- Tests without plugin output should have empty expected files if no warnings are expected
- Use `./tests/run_test TestName` for integration tests
- Use `./vendor/bin/phpunit` for unit tests