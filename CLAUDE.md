# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phan is a static analyzer for PHP that aims to minimize false-positives by attempting to prove incorrectness rather than correctness. It performs type safety checks, detects common errors, and can track values through flow control.

## Key Architecture Components

### Core Analysis Engine
- **`src/Phan/AST/`** - Abstract Syntax Tree handling and visitors that traverse PHP code
- **`src/Phan/Analysis/`** - Core analysis algorithms for type checking, condition analysis, and code flow
- **`src/Phan/Language/`** - Type system implementation including UnionTypes, FQSEN (Fully Qualified Structural Element Names)
- **`src/Phan/CodeBase.php`** - Central repository of all analyzed code elements (classes, functions, etc.)

### Plugin System
- **`src/Phan/PluginV3/`** - Plugin interfaces and capabilities
- **`.phan/plugins/`** - Bundled plugins that extend Phan's analysis capabilities
- Plugins can hook into various analysis phases through capability interfaces

### Language Server
- **`src/Phan/LanguageServer/`** - LSP implementation for IDE integration
- **`src/Phan/Daemon/`** - Daemon mode for persistent analysis processes

## Essential Commands

### Running Phan
```bash
# Basic analysis of current directory
./phan

# With specific configuration
./phan -k .phan/config.php

# With memory limit and specific directory
./phan --memory-limit 1G -l src/

# Using polyfill parser (when php-ast extension unavailable)
./phan --allow-polyfill-parser

# Running in daemon mode
./phan --daemonize-tcp-port 4846
```

### Running Tests
```bash
# Run PHPUnit tests
./test

# Run all tests including integration tests
./tests/run_all_tests

# Run specific test suite
./tests/run_test PhanTest

# Run tests in parallel (requires GNU parallel)
./tests/run_all_tests --parallel

# Run a specific PHPUnit test file
./vendor/bin/phpunit tests/Phan/Language/UnionTypeTest.php
```

### Code Quality Checks
```bash
# Run Phan on itself (self-analysis)
./phan --memory-limit 1G

# Run with all plugins and dead code detection
./phan --dead-code-detection --unused-variable-detection --redundant-condition-detection

# Check with PHP's native syntax checker
./phan --plugin InvokePHPNativeSyntaxCheckPlugin
```

## Development Workflow

### Adding New Issue Types
1. Define the issue in `src/Phan/Issue.php`
2. Emit the issue in appropriate visitor/analyzer classes
3. Add test cases in `tests/files/` with corresponding `.expected` files

### Creating Plugins
1. Extend `PluginV3` class
2. Implement desired capability interfaces (e.g., `AnalyzeNodeCapability`)
3. Place in `.phan/plugins/` or reference by path in config
4. Add to `'plugins'` array in `.phan/config.php`

### Modifying Type Analysis
1. Core type classes are in `src/Phan/Language/Type/`
2. Union type handling is in `src/Phan/Language/UnionType.php`
3. Type visitors are in `src/Phan/AST/UnionTypeVisitor.php`

## Configuration

Main configuration file is `.phan/config.php`. Key settings:

- `target_php_version` - PHP version to check compatibility against
- `directory_list` - Directories to parse for analysis
- `exclude_analysis_directory_list` - Parse but don't analyze (e.g., vendor/)
- `plugins` - Active plugins
- `suppress_issue_types` - Issues to suppress globally

## Testing Guidelines

- Unit tests use PHPUnit and are in `tests/Phan/`
- Integration tests are in `tests/files/` with `.php` input and `.expected` output
- Run `./tests/run_test __FakeAllPHPUnitTests` for all PHPUnit tests
- Test files should include both positive and negative test cases

## Important Notes

- Phan requires php-ast extension for optimal performance (can use polyfill as fallback)
- Minimum PHP version is 7.2+ for running Phan
- Can analyze PHP 7.0-8.2 syntax
- Uses AST-based analysis, not execution-based
- Designed for incremental adoption with configurable strictness levels