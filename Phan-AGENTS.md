# Phan for Coding Agents

This document gives coding agents (Claude Code, Cursor, Copilot, etc.) direct access to Phan's code intelligence capabilities using standard shell tools. No MCP server or extra processes required — just `sqlite3`, `phan_client`, and basic TCP tools.

## Overview

Phan provides two data sources for code intelligence:

1. **Phound SQLite Database** — A pre-built database of all symbols, callsites, class hierarchies, and signatures in your codebase. Query it with `sqlite3` for instant results.
2. **Phan Daemon** — A long-running analysis server that can analyze individual files on demand, including unsaved buffers and type inference at specific code points.

Use the **database** for structural queries (find references, list symbols, check hierarchy). Use the **daemon** for live analysis (type errors, type inference, diagnostics on modified code).

## Phound SQLite Database

### Setup

Add the PhoundPlugin to your `.phan/config.php`:

```php
'plugin_config' => [
    'phound_sqlite_path' => '.phan/phound.sqlite',
],
'plugins' => [
    'PhoundPlugin',
    // ... other plugins
],
```

### Building the Database

Run Phan normally — PhoundPlugin builds the database during analysis:

```bash
./phan
# Database is written to the path specified in phound_sqlite_path
```

The database is rebuilt from scratch on each run (no incremental updates).

### Schema Reference

The database contains 11 tables. All file paths are project-relative. All FQSENs (Fully Qualified Structural Element Names) start with `\`.

#### FQSEN Format

| Element | Format | Example |
|---------|--------|---------|
| Class | `\Namespace\ClassName` | `\Phan\CodeBase` |
| Interface | `\Namespace\InterfaceName` | `\Phan\PluginV3` |
| Trait | `\Namespace\TraitName` | `\Phan\Memoize` |
| Method | `\Class::method` | `\Phan\CodeBase::getMethodByFQSEN` |
| Property | `\Class::property` | `\Phan\CodeBase::class_fqsen_class_map_map` |
| Class constant | `\Class::CONST` | `\Phan\Issue::TypeMismatchReturn` |
| Function | `\namespace\function` | `\array_map` |

#### Core Tables

**callsites** — Every usage of a class member in the codebase.

| Column | Type | Description |
|--------|------|-------------|
| element | TEXT | FQSEN of the element being used |
| type | TEXT | `method`, `prop`, or `const` |
| callsite | TEXT | Location of usage (`file:lineno`) |

Primary key: `(element, type, callsite)`. Index: `(element, callsite)`.

**signatures** — Complete signatures for all functions and class members.

| Column | Type | Description |
|--------|------|-------------|
| fqsen | TEXT | FQSEN (primary key) |
| kind | TEXT | `function`, `method`, `property`, or `constant` |
| class_fqsen | TEXT | Parent class FQSEN (NULL for functions) |
| name | TEXT | Unqualified name |
| type | TEXT | Return/value type as union type string |
| is_static | INTEGER | 1 if static, 0 otherwise |
| visibility | TEXT | `public`, `protected`, `private` (NULL for functions) |
| filepath | TEXT | File where defined (project-relative) |
| lineno | INTEGER | Line number of definition |
| docblock | TEXT | PHPDoc comment (NULL if none) |

Indexes: `name`, `class_fqsen`, `filepath`.

**parameters** — Function/method parameters.

| Column | Type | Description |
|--------|------|-------------|
| fqsen | TEXT | Parent function/method FQSEN |
| idx | INTEGER | Parameter position (0-based) |
| name | TEXT | Parameter name (without `$`) |
| type | TEXT | Parameter type |
| is_variadic | INTEGER | 1 if `...$param` |
| is_reference | INTEGER | 1 if `&$param` |
| is_optional | INTEGER | 1 if has default value |
| default_repr | TEXT | String representation of default value |

Primary key: `(fqsen, idx)`.

#### Class/Interface/Trait Definition Tables

**classes**, **interfaces**, **traits** — Each has the same structure:

| Column | Type | Description |
|--------|------|-------------|
| name | TEXT | FQSEN (primary key) |
| filepath | TEXT | File where defined |

#### Relationship Tables

All relationship tables store **transitive** closures (if A extends B extends C, then `class_relationships` contains both `(B, A)` and `(C, A)` pairs).

**class_relationships** — Class inheritance. Columns: `parent`, `child`.

**interface_relationships** — Interface inheritance. Columns: `parent`, `child`.

**class_interfaces** — Classes implementing interfaces. Columns: `class`, `interface`.

**class_traits** — Classes using traits. Columns: `class`, `trait`.

**trait_traits** — Traits using other traits. Columns: `trait`, `uses_trait`.

All relationship tables have indexes on the child/second column for reverse lookups.

### Query Recipes

All examples use `sqlite3 .phan/phound.sqlite`. Adjust the path to match your `phound_sqlite_path` config.

#### Find References (callsites of a symbol)

```bash
# Find all usages of a method
sqlite3 .phan/phound.sqlite "SELECT callsite FROM callsites WHERE element = '\Phan\CodeBase::getMethodByFQSEN' AND type = 'method' ORDER BY callsite"

# Find all usages of a property
sqlite3 .phan/phound.sqlite "SELECT callsite FROM callsites WHERE element = '\Phan\CodeBase::class_fqsen_class_map_map' AND type = 'prop' ORDER BY callsite"

# Find all usages of a class constant
sqlite3 .phan/phound.sqlite "SELECT callsite FROM callsites WHERE element = '\Phan\Issue::TypeMismatchReturn' AND type = 'const' ORDER BY callsite"
```

#### Get a Symbol's Signature

```bash
# Get signature of a method
sqlite3 -header .phan/phound.sqlite "
  SELECT fqsen, kind, type, visibility, is_static, filepath, lineno, docblock
  FROM signatures WHERE fqsen = '\Phan\CodeBase::getMethodByFQSEN'"

# Get its parameters
sqlite3 -header .phan/phound.sqlite "
  SELECT name, type, is_variadic, is_reference, is_optional, default_repr
  FROM parameters WHERE fqsen = '\Phan\CodeBase::getMethodByFQSEN' ORDER BY idx"
```

#### Class Hierarchy

```bash
CLASS='\Phan\Language\Type'

# Parent classes
sqlite3 .phan/phound.sqlite "SELECT parent FROM class_relationships WHERE child = '$CLASS' ORDER BY parent"

# Child classes (subclasses)
sqlite3 .phan/phound.sqlite "SELECT child FROM class_relationships WHERE parent = '$CLASS' ORDER BY child"

# Interfaces implemented
sqlite3 .phan/phound.sqlite "SELECT interface FROM class_interfaces WHERE class = '$CLASS' ORDER BY interface"

# Traits used
sqlite3 .phan/phound.sqlite "SELECT trait FROM class_traits WHERE class = '$CLASS' ORDER BY trait"

# For interfaces — sub-interfaces
sqlite3 .phan/phound.sqlite "SELECT child FROM interface_relationships WHERE parent = '$CLASS' ORDER BY child"

# For interfaces — parent interfaces
sqlite3 .phan/phound.sqlite "SELECT parent FROM interface_relationships WHERE child = '$CLASS' ORDER BY parent"
```

#### Find Implementations

```bash
# Classes implementing an interface (with file locations)
sqlite3 -header .phan/phound.sqlite "
  SELECT ci.class, c.filepath
  FROM class_interfaces ci LEFT JOIN classes c ON ci.class = c.name
  WHERE ci.interface = '\Phan\PluginV3'
  ORDER BY ci.class"

# Subclasses of a class (with file locations)
sqlite3 -header .phan/phound.sqlite "
  SELECT cr.child, c.filepath
  FROM class_relationships cr LEFT JOIN classes c ON cr.child = c.name
  WHERE cr.parent = '\Phan\Language\Type'
  ORDER BY cr.child"
```

#### Search Symbols by Name

```bash
# Search by name pattern (SQL LIKE syntax)
sqlite3 -header .phan/phound.sqlite "
  SELECT fqsen, kind, filepath, lineno
  FROM signatures WHERE name LIKE '%CodeBase%'
  ORDER BY fqsen LIMIT 50"

# Filter by kind
sqlite3 -header .phan/phound.sqlite "
  SELECT fqsen, kind, filepath, lineno
  FROM signatures WHERE name LIKE 'get%' AND kind = 'method'
  ORDER BY fqsen LIMIT 50"
```

#### List Symbols in a File

```bash
# All symbols defined in a file
sqlite3 -header .phan/phound.sqlite "
  SELECT fqsen, kind, type, visibility, is_static, lineno
  FROM signatures WHERE filepath = 'src/Phan/CodeBase.php'
  ORDER BY lineno"

# Only methods
sqlite3 -header .phan/phound.sqlite "
  SELECT fqsen, kind, type, visibility, is_static, lineno
  FROM signatures WHERE filepath = 'src/Phan/CodeBase.php' AND kind = 'method'
  ORDER BY lineno"
```

#### Find Unused (Dead) Code

```bash
# Find unreferenced symbols (methods, properties, constants — excluding functions)
sqlite3 -header .phan/phound.sqlite "
  SELECT s.fqsen, s.kind, s.visibility, s.filepath, s.lineno, s.name
  FROM signatures s
  LEFT JOIN callsites c ON s.fqsen = c.element AND c.type = (
    CASE s.kind
      WHEN 'method' THEN 'method'
      WHEN 'property' THEN 'prop'
      WHEN 'constant' THEN 'const'
      ELSE ''
    END
  )
  WHERE c.element IS NULL
    AND s.kind != 'function'
  ORDER BY s.filepath, s.lineno
  LIMIT 100"
```

**Important filtering**: The raw query returns false positives. Filter these out:
- **Magic methods**: `__construct`, `__destruct`, `__call`, `__callStatic`, `__get`, `__set`, `__isset`, `__unset`, `__sleep`, `__wakeup`, `__serialize`, `__unserialize`, `__toString`, `__invoke`, `__set_state`, `__clone`, `__debugInfo`
- **PHPUnit test methods**: Public methods starting with `test`
- **The `class` constant**: Every class has a `::class` pseudo-constant

Add these filters to the WHERE clause:

```sql
  AND s.name NOT IN ('__construct','__destruct','__call','__callStatic',
      '__get','__set','__isset','__unset','__sleep','__wakeup',
      '__serialize','__unserialize','__toString','__invoke',
      '__set_state','__clone','__debugInfo')
  AND NOT (s.kind = 'method' AND s.visibility = 'public' AND s.name LIKE 'test%')
  AND NOT (s.kind = 'constant' AND s.name = 'class')
```

#### Ad-hoc Queries

The real power of direct database access is writing queries the pre-built tools can't anticipate:

```bash
# Which files have the most symbols?
sqlite3 .phan/phound.sqlite "SELECT filepath, COUNT(*) as cnt FROM signatures GROUP BY filepath ORDER BY cnt DESC LIMIT 20"

# Most-referenced methods
sqlite3 .phan/phound.sqlite "SELECT element, COUNT(*) as cnt FROM callsites WHERE type = 'method' GROUP BY element ORDER BY cnt DESC LIMIT 20"

# All protected methods in a class
sqlite3 .phan/phound.sqlite "SELECT name, type FROM signatures WHERE class_fqsen = '\Phan\CodeBase' AND visibility = 'protected' ORDER BY name"

# Classes with no subclasses (leaf classes)
sqlite3 .phan/phound.sqlite "SELECT c.name, c.filepath FROM classes c LEFT JOIN class_relationships cr ON c.name = cr.parent WHERE cr.child IS NULL ORDER BY c.name"
```

## Phan Daemon

The daemon keeps your entire codebase loaded in memory and can analyze individual files on demand in milliseconds.

**Requires the `pcntl` extension** (used to fork worker processes for each request). This means the daemon is not available on Windows.

### Starting the Daemon

```bash
# Start on default port (4846)
./phan --daemonize-tcp-port default

# Start on specific port
./phan --daemonize-tcp-port 4846

# With quick mode for faster analysis (less thorough)
./phan --daemonize-tcp-port 4846 --quick
```

The daemon loads the full codebase on startup, then listens for analysis requests.

### Using phan_client

The simplest way to analyze a file:

```bash
# Analyze a single file
php phan_client -l src/MyClass.php

# Analyze with JSON output
php phan_client -l src/MyClass.php -m json

# Analyze with a non-default port
php phan_client --daemonize-tcp-port 4850 -l src/MyClass.php

# Analyze an unsaved buffer (flycheck-style)
php phan_client -l src/MyClass.php -f /tmp/MyClass_modified.php
```

`phan_client` runs `php -l` syntax check first, then sends the file to the daemon. Use `--use-fallback-parser` to skip the syntax check.

### Direct TCP Communication

For programmatic use, send JSON directly to the daemon over TCP:

```bash
# Analyze a file via netcat
echo '{"method":"analyze_files","files":["src/MyClass.php"],"format":"json"}' | nc 127.0.0.1 4846
```

#### Request Format

```json
{
  "method": "analyze_files",
  "files": ["src/MyClass.php"],
  "format": "json"
}
```

| Field | Type | Description |
|-------|------|-------------|
| method | string | `analyze_files`, `analyze_file`, or `analyze_all` |
| files | string[] | File paths (project-relative) to analyze |
| format | string | `json`, `text`, `csv`, `codeclimate`, `checkstyle`, `pylint`, `html`, `github` |
| temporary_file_mapping_contents | object | Map of `{filepath: contents}` to override file contents |
| color | bool | Whether to colorize output |

#### Response Format (JSON)

```json
{
  "status": "ok",
  "issue_count": 2,
  "issues": [
    {
      "type": "issue",
      "check_name": "PhanUndeclaredVariable",
      "description": "Variable $x is undeclared",
      "severity": 10,
      "location": {
        "path": "src/MyClass.php",
        "lines": { "begin": 42 }
      }
    }
  ]
}
```

Status codes: `ok`, `no_files`, `invalid_format`, `invalid_files`, `invalid_method`, `invalid_request`, `error_unknown`.

### Analyzing Unsaved Buffers

Send modified file contents without writing to disk:

```bash
echo '{
  "method": "analyze_files",
  "files": ["src/MyClass.php"],
  "format": "json",
  "temporary_file_mapping_contents": {
    "src/MyClass.php": "<?php\nclass MyClass {\n    public function test(): void {\n        $x = undefinedFunction();\n    }\n}\n"
  }
}' | nc 127.0.0.1 4846
```

The daemon analyzes the provided content as if the file contained it, without modifying the file on disk.

## Type Inference at a Point

Phan can tell you the exact inferred type of any variable at any point in your code using the `@phan-debug-var` annotation.

### Basic Usage

Add a string literal annotation in your PHP code:

```php
function example() {
    $x = [1, 2, 3];
    '@phan-debug-var $x';
    // Phan reports: PhanDebugAnnotation $x has union type int[]
}
```

Then analyze the file:

```bash
./phan -n myfile.php
# or via the daemon:
php phan_client -l myfile.php
```

### Automated Type Query via Daemon

To query a variable's type without modifying the file on disk, inject the annotation into the file contents and send to the daemon:

```bash
#!/bin/bash
# Usage: type_at.sh <file> <line> <variable>
FILE="$1"
LINE="$2"
VAR="$3"

# Read the file and inject @phan-debug-var before the target line
CONTENTS=$(awk -v line="$LINE" -v var="$VAR" '
  NR == line { print "'"'"'@phan-debug-var $" var "'"'"';" }
  { print }
' "$FILE")

# Send to daemon with the modified contents
REQUEST=$(jq -n \
  --arg file "$FILE" \
  --arg contents "$CONTENTS" \
  '{method: "analyze_files", files: [$file], format: "json",
    temporary_file_mapping_contents: {($file): $contents}}')

echo "$REQUEST" | nc 127.0.0.1 4846 | jq '.issues[] | select(.check_name == "PhanDebugAnnotation") | .description'
```

**Important**: The variable name must be a valid PHP identifier (`[a-zA-Z_][a-zA-Z0-9_]*`) — do not pass unsanitized input.

## Quick Reference

### Running Phan

```bash
# Analyze specific files without loading config (fast)
./phan -n file1.php file2.php

# Analyze with the test config
./phan -k tests/.phan_for_test/config.php file1.php file2.php

# Self-analysis
./phan

# AST dump for debugging
php tool/dump_ast.php --json file.php
```

### Issue Suppression

```php
// Suppress on next line
/** @suppress PhanTypeMismatchReturn */

// Suppress for entire file
/** @phan-file-suppress PhanUnreferencedMethod */

// Inline suppression
/* @phan-suppress-current-line PhanUndeclaredVariable */
/* @phan-suppress-next-line PhanUndeclaredVariable */
```

### Debugging Type Inference

```php
'@phan-debug-var $x';           // Single variable
'@phan-debug-var $x, $y';       // Multiple variables
'@phan-debug-var $arr[\'key\']'; // Array elements
```
