# Tolerant Parser Update TODO

## Overview

This branch tracks work needed to bring the tolerant PHP parser up to date for PHP 8.3, 8.4, and 8.5 so that downstream consumers (notably Phan) can rely on it when the native `ext-ast` extension is unavailable. The fork currently matches upstream commit `457738cbe` (September 2024) and is missing several recent language features.

## Current Status

- PHPUnit suites `invariants` and `api` pass locally when run with `zend.assertions=1` and `assert.exception=1`.
- Grammar fixtures (`tests/cases/parser*.tree/.diag`) were generated before the latest language changes; they will need regeneration once parser updates land.
- Validation tests that depend on large submodules (`validation/frameworks/*`) remain skipped/not vendored.

## Gaps / Work Items

### PHP 8.3

Double-check tolerant against the language changes that shipped with 8.3:

- **Dynamic class constant fetch** (`Foo::{expr}`): ensure tokenizer/grammar accept brace-wrapped expressions after `::`, add fixtures, and mirror php-ast node structure. **(verified via `tests/samples/dynamic_class_const.php`)**
- **Typed class constants / readonly amendments**: confirm class-constant declarations propagate their type information and `readonly` constraints into tolerant AST output.
- **`#[\Override]` attribute**: attributes already parse, but we should add fixtures to verify tolerant preserves them on methods.
- **Arbitrary static variable initialisers**: while this is largely semantic, tolerant should accept the new grammar in function-level `static` declarations and update diagnostics if necessary.

### PHP 8.4

- **Property access hooks** (`public int $count { get => ...; set { ... } }`): **(implemented)**
  - Tokenizer must recognise `get`/`set` (and hook modifiers) in this context.
  - Introduce AST nodes for hook lists/bodies that align with php-ast’s `AST_PROP_ELEM` `hooks` child.
  - Update diagnostics to catch invalid hook combinations.
  - Conversion now produces `AST_PROPERTY_HOOK`/`AST_PROPERTY_HOOK_SHORT_BODY` nodes; add regression coverage to guard against regressions.
- **Asymmetric visibility v2** (`public(set) private(get) $prop;`): extend the modifier grammar, update `TokenKind`, and cover tolerant AST flag handling.
- **`new Foo()->bar()` without wrapping parentheses**: confirm parser handles the reduced precedence and add regression tests.
- **Property hook improvements** (hook attributes, multiple hooks per property, etc.): ensure attribute placement and hook ordering are represented correctly.
- Audit additional 8.4 deprecations that change parsing (e.g. implicit nullable parameters) to ensure tolerant still emits matching diagnostics.

### PHP 8.5 (in progress)

Monitor RFCs merged into php-src and mirror the token/grammar changes, for example:

- **Pipe operator** (`expr |> func(...)`): add new tokens, precedence rules, AST nodes, and fixtures.
- **`clone with`** expressions: model the new syntax (`clone $obj with { prop: value }`) and ensure node mapping covers the initializer list.
- **Final property promotion** (`final public function __construct(private final string $x) {}`): allow `final` in promoted parameters and carry flags into tolerant AST.
- **Attributes on constants / extended attribute targets**: verify attributes on constants and traits are preserved.
- **Extend `#[\Override]` to properties / `#[\NoDiscard]` / `#[\DelayedTargetValidation]`**: attributes already parse, but add regression coverage to ensure tolerant does not misclassify their targets.
- Track any additional keywords (`with`, operator tokens, etc.) and update `TokenKind.php` / `TokenStringMaps.php` accordingly.

### Diagnostics & Node Mapping

- Review `TokenKind`/`TokenStringMaps` for completeness after adding new tokens.
- Ensure node classes record line/column spans needed by language-server features when hooks and new constructs are present.
- Verify edits still map correctly (`TextEdit`, node mapping) for newly supported syntax.

### Tests & Tooling

- Add focused fixtures for property hooks and any new constructs under `tests/cases/parser` (and regenerate `.tree/.diag`).
- Re-enable / update tolerant PHPUnit suites once fixtures are refreshed.
- Consider syncing targeted tests from Phan’s fallback suite to catch divergences early.

### Integration with Phan

- After implementing features, run Phan’s fallback parser tests (`./tests/run_test __FakeSelfFallbackTest`) to ensure parity.
- Publish updated tags / commit references so Phan can subtree merge the changes into `third_party/phan-tolerant`.

### Verification Strategy

- **AST comparison**: use Phan’s `tools/dump_ast.php` (php-ast) to capture the expected AST for new syntax. For the tolerant side, run `php tools/PrintTolerantAst.php` (raw tolerant tree) in combination with Phan’s `internal/dump_fallback_ast.php` (uses the converter) to ensure both the parse tree and converted php-ast structures match expectations.
- **PHP runtime selection**: on this dev machine we can run `sudo newphp 83`, `sudo newphp 84`, etc. to switch CLI versions; other environments may require Docker images, phpenv, asdf, etc. Record the PHP version used when regenerating fixtures.
- **Leverage Phan fixtures**: pull feature-specific testcases (e.g. property hooks, asymmetric visibility) from `phan/tests/files/src` into tolerant’s parser tests to validate new constructs.
- **Run tolerant PHPUnit suites**: keep `vendor/bin/phpunit --testsuite invariants,api` (with `zend.assertions=1`) as a fast regression check while iterating.

Recommended sample inputs for AST diffs (update as new fixtures are added):

| Feature | Sample file(s) | Min PHP | Native AST dump | Tolerant dump |
| --- | --- | --- | --- | --- |
| Dynamic class const fetch | `tests/samples/dynamic_class_const.php` | 8.3 | `php ~/phan/tools/dump_ast.php --json …` | `php tools/PrintTolerantAst.php …` + `php ~/phan/internal/dump_fallback_ast.php --php-ast …` |
| Property hooks | `tests/samples/property_hooks.php` | 8.4 | (run after `sudo newphp 84`) | same as above |
| Asymmetric visibility props | (add fixture) | 8.4 | … | … |
| Pipe operator | `tests/samples/pipe_operator.php` | 8.5 | `php ~/phan/tools/dump_ast.php --json …` | `php tools/PrintTolerantAst.php …`, `php ~/phan/internal/dump_fallback_ast.php --php-ast …` |
| `clone with` expressions | (add fixture) | 8.5 | … | … |

## Next Steps

1. Audit existing fixtures vs php-src 8.3/8.4 syntax to catalogue precise failures.
2. Prototype grammar/tokenizer changes for property hooks and regenerate associated AST nodes.
3. Update diagnostics and tolerant AST converter expectations in tandem with Phan.
4. Refresh fixtures and CI to cover the new syntax.
5. Coordinate subtree sync back into Phan once tests pass.
