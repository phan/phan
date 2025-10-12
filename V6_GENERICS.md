# Phan Generics Improvement Roadmap (v6)

## Overview
This document tracks the implementation of enhanced generics support in Phan v6 while maintaining Phan's core goals:
- **Minimize false positives**
- **Maintain analysis speed**

## Current Status Summary

### ✅ Already Supported
- `@template T` - Basic template declarations
- `@template T of SomeClass` - Constraints with enforcement across class hierarchies and function calls
- `@extends ParentClass<Type>` - Template inheritance for classes
- `class-string<T>` - Type-safe class strings
- Generic built-in interfaces (ArrayAccess, Iterator, etc.) - hardcoded
- Array shapes: `array{key:type}`
- Multiple template parameters
- Template type inference

### Remaining Gaps
- `@template-covariant` / `@template-contravariant` enforcement for array shapes and other compound types (difficult)
- Conditional return types (difficult to implement efficiently)

## Implementation Phases

### Phase 1: Template Inheritance Gaps ✅ COMPLETE

**Goal**: Enable generic interfaces and traits

**Priority**: P1 (High Value, Moderate Effort)

**Performance Impact**: Minimal - similar to existing `@extends` implementation

**Status**: Task 1.1 (interfaces) ✅ Complete | Task 1.2 (traits) ✅ Complete

#### Task 1.1: Implement `@template-implements` ✅
**Status**: COMPLETE
**Actual Effort**: 5 hours total (Parsing: 2 hours | Resolution: 3 hours)
**Files Modified**:
- ✅ `src/Phan/Language/Element/Comment/Builder.php` - Parse annotation
- ✅ `src/Phan/Language/Element/Comment.php` - Store implements list
- ✅ `src/Phan/Language/Element/Clazz.php` - Process template parameters and resolution
- ✅ `src/Phan/Parse/ParseVisitor.php` - Populate interface type mapping
- ✅ `src/Phan/Analysis/ParameterTypesAnalyzer.php` - Override compatibility with templates
- ✅ `tests/files/src/1100_template_implements.php` - Test cases
- ✅ `tests/files/expected/1100_template_implements.php.expected` - Expected output

**Phase 1 - Parsing (Complete)**:
- ✅ Added `$implemented_types` field to Comment.php
- ✅ Updated Comment constructor to accept implemented types
- ✅ Added `getImplementedTypes()` getter method in Comment.php
- ✅ Added `applyOverride()` support for 'implements' key in Comment.php
- ✅ Added `$implemented_types` field to Builder.php
- ✅ Created `implementsFromCommentLine()` parsing method in Builder.php
- ✅ Added `maybeParseImplements()` handler in Builder.php
- ✅ Added `maybeParsePhanImplements()` handler in Builder.php
- ✅ Integrated support into the `parseCommentLine()` switch
- ✅ Updated regex to recognize 'implements'
- ✅ Added 'phan-implements' case in `maybeParsePhanCustomAnnotation()`
- ✅ Added '@phan-implements' to supported annotations

**Phase 2 - Template Resolution (Complete)**:
- ✅ Added `$interface_type_map` field to Clazz.php
- ✅ Added `setInterfaceType()` method to store interface types
- ✅ Added `getInterfaceType()` method to retrieve interface types
- ✅ Updated ParseVisitor.php to populate the interface type map
- ✅ Updated `importAncestorClasses()` to use mapped interface types
- ✅ Added validation for template parameter count matching (missing or extra)
- ✅ Updated ParameterTypesAnalyzer to resolve interface templates for override checking
- ✅ Updated test expectations with correct results
- ✅ `./vendor/bin/phpunit tests/Phan/PhanTestNew.php` — 104 tests, 0 failures

**What Works Now**:
- ✅ Parsing of `@implements Interface<Type>` and `@phan-implements Interface<Type>`
- ✅ Template type resolution for inherited interface methods
- ✅ Override compatibility checking with resolved template types
- ✅ Multiple interface implementation with different template parameters
- ✅ Nested generic types (e.g., `@implements Repository<array<string, User>>`)
- ✅ Validation warning when interface template parameter counts are missing or extra
- ✅ Zero false positives on signature mismatch errors

**Test Cases Implemented** (tests/files/src/1100_template_implements.php):
```php
// Basic implementation with concrete type
/**
 * @implements Repository<User>
 */
class UserRepository implements Repository {
    public function find(int $id): User { return new User(); }
    public function save($entity): void { }
}

// Multiple interfaces with template parameters
/**
 * @template T
 * @implements Iterator<int, T>
 * @implements Countable
 */
class UserCollection implements Iterator, Countable { ... }

// Nested generic types
/**
 * @implements Repository<array<string, User>>
 */
class UserMapRepository implements Repository {
    public function find(int $id): array { return []; }
    public function save($entity): void { }
}

// Using @phan-implements variant
/**
 * @template T
 * @phan-implements Repository<T>
 */
class GenericRepository implements Repository { ... }
```

**All test cases pass** with correct template resolution and zero false positives.

Additional negative coverage: `tests/files/src/1102_template_parameter_mismatch.php` exercises missing and extra template parameter diagnostics across `@implements`, `@use`, and `@extends`, and now includes scaffolding for future variance tests (`@template-covariant`).

**Performance Considerations**:
- ✅ Cached parsed implements types (similar to extends)
- ✅ Lazy evaluation of template parameter validation
- ✅ No additional passes required

**Technical Implementation Details**:

The implementation follows the same pattern as `@extends` for parent classes:

1. **Storage Pattern** (Clazz.php):
   - `$interface_type_map` stores FQSEN → Type mappings
   - Uses Option<Type> pattern for optional interface types
   - Parallel to `$parent_type` but supports multiple interfaces

2. **Population Flow** (ParseVisitor.php):
   - After parsing `implements` clause from AST
   - Read `@implements` from comment via `getImplementedTypes()`
   - Extract FQSEN and store in map via `setInterfaceType()`

3. **Resolution Flow** (Clazz.php → importAncestorClasses()):
   - For each interface FQSEN, call `getInterfaceType()`
   - If type exists, extract template parameter type map
   - Pass to `importAncestorClass()` which calls `addMethod()`
   - `addMethod()` applies template substitution via `cloneWithTemplateParameterTypeMap()`

4. **Override Checking** (ParameterTypesAnalyzer.php):
   - Extended `analyzeOverrideSignatureForOverriddenMethod()`
   - Check if overridden method is from interface
   - Retrieve interface type from map
   - Apply template substitution before comparison
   - Prevents false positive signature mismatch errors

**Key Design Decisions**:
- ✅ Reuse existing template resolution machinery (`cloneWithTemplateParameterTypeMap`)
- ✅ Store per-interface instead of global (supports multiple interfaces)
- ✅ Validate parameter count but defer constraint checking to Phase 2
- ✅ Emit warnings (not errors) for template parameter count mismatches with precise interface locations (lenient)

#### Task 1.2: Implement `@template-use` ✅
**Status**: COMPLETE
**Actual Effort**: 3 hours total (Parsing: 1 hour | Resolution: 2 hours)
**Files Modified**:
- ✅ `src/Phan/Language/Element/Comment/Builder.php` - Parse annotation
- ✅ `src/Phan/Language/Element/Comment.php` - Store trait types
- ✅ `src/Phan/Language/Element/Clazz.php` - Process and resolve trait templates
- ✅ `src/Phan/Parse/ParseVisitor.php` - Populate trait type mapping
- ✅ `tests/files/src/1101_template_use.php` - Test cases
- ✅ `tests/files/expected/1101_template_use.php.expected` - Expected output

**Phase 1 - Parsing (Complete)**:
- ✅ Added `$used_trait_types` field to Comment.php
- ✅ Updated Comment constructor to accept trait types parameter
- ✅ Added `getUsedTraitTypes()` getter method in Comment.php
- ✅ Added `applyOverride()` support for 'use' key in Comment.php
- ✅ Added `$used_trait_types` field to Builder.php
- ✅ Created `useFromCommentLine()` parsing method in Builder.php
- ✅ Added `maybeParseUse()` handler in Builder.php
- ✅ Added `maybeParsePhanUse()` handler in Builder.php
- ✅ Integrated support into the `parseCommentLine()` switch
- ✅ Updated regex to recognize 'use'
- ✅ Added 'phan-use' case in `maybeParsePhanCustomAnnotation()`
- ✅ Added '@phan-use' to supported annotations

**Phase 2 - Template Resolution (Complete)**:
- ✅ Added `$trait_type_map` field to Clazz.php
- ✅ Added `setTraitType()` method to store trait types
- ✅ Added `getTraitType()` method to retrieve trait types
- ✅ Updated ParseVisitor.php to populate the trait type map
- ✅ Updated `importAncestorClasses()` to use mapped trait types
- ✅ Added validation for template parameter count matching (missing or extra)
- ✅ **Critical fix**: Apply template resolution BEFORE `adaptInheritedMethodFromTrait()`
  - Trait methods get new FQSENs via `createUseAlias`, preventing later resolution
  - Solution: Resolve templates early, then adapt method
- ✅ Pass `None::instance()` to `addMethod()` for traits to avoid double resolution
- ✅ Verified via `./vendor/bin/phpunit tests/Phan/PhanTestNew.php`

**What Works Now**:
- ✅ Parsing of `@use Trait<Type>` and `@phan-use Trait<Type>`
- ✅ Template type resolution for trait methods and properties
- ✅ Multiple trait usage with different template parameters
- ✅ Nested generic types (e.g., `@use Repository<array<string, User>>`)
- ✅ Validation warning when trait template parameter counts are missing or extra
- ✅ Combining class templates with trait templates (`@template T @phan-use Trait<T>`)

**Test Cases Implemented** (tests/files/src/1101_template_use.php):
```php
// Basic trait usage with concrete type
/**
 * @use Repository<User>
 */
class UserService {
    use Repository;
    // getEntity() correctly returns User instead of T
}

// Multiple traits with different template parameters
/**
 * @use Repository<Article>
 * @use Timestamped<\DateTimeImmutable>
 */
class ArticleService {
    use Repository;
    use Timestamped;
    // getEntity() returns Article, getTimestamp() returns DateTimeImmutable
}

// Nested generic types
/**
 * @use Repository<array<string, User>>
 */
class UserMapService {
    use Repository;
    // getEntity() returns array<string, User>
}

// Class template with trait template
/**
 * @template T
 * @phan-use Repository<T>
 */
class GenericService {
    use Repository;
    // get() correctly returns T (class's template parameter)
}
```

**All test cases pass** with correct template resolution:
- ✅ UserService::getEntity() → User
- ✅ ArticleService::getEntity() → Article
- ✅ ArticleService::getTimestamp() → DateTimeImmutable
- ✅ UserMapService::getEntity() → array<string, User>

**Performance Considerations**:
- ✅ Cached parsed trait types (similar to implements)
- ✅ Lazy evaluation of template parameter validation
- ✅ No additional passes required

**Technical Implementation Details**:

The implementation follows the exact same pattern as `@template-implements`:

1. **Storage Pattern** (Clazz.php):
   - `$trait_type_map` stores FQSEN → Type mappings
   - Uses Option<Type> pattern for optional trait types
   - Parallel to `$interface_type_map`

2. **Population Flow** (ParseVisitor.php):
   - After parsing `use` clause from AST
   - Read `@use` from comment via `getUsedTraitTypes()`
   - Extract FQSEN and store in map via `setTraitType()`

3. **Resolution Flow** (Clazz.php → importAncestorClasses()):
   - For each trait FQSEN, call `getTraitType()`
   - If type exists, extract template parameter type map
   - **Apply template resolution BEFORE calling `adaptInheritedMethodFromTrait()`**
     - This is critical because `adaptInheritedMethodFromTrait()` changes method FQSEN
     - Changed FQSEN would cause template resolution to be skipped in `addMethod()`
   - Pass `None::instance()` to `addMethod()` to prevent double resolution

4. **Key Difference from Interfaces**:
   - Interface methods keep original FQSEN → template resolution in `addMethod()`
   - Trait methods get new FQSEN via `createUseAlias` → must resolve earlier

**Key Design Decisions**:
- ✅ Reuse existing template resolution machinery (`cloneWithTemplateParameterTypeMap`)
- ✅ Store per-trait instead of global (supports multiple traits)
- ✅ Validate parameter count but defer constraint checking to Phase 2
- ✅ Emit warnings (not errors) for template parameter count mismatches with precise trait locations (lenient)
- ✅ Apply resolution before FQSEN changes (critical for traits)

### Phase 2: Template Constraint Enforcement ✅ COMPLETE

**Goal**: Make declared bounds (`@template T of Foo`) enforceable for inheritance hierarchies and template-aware call sites.

**Priority**: P1 (High Value, High Effort)

**Status**: COMPLETE

**Highlights**:
- ✅ Parsing: `Comment/Builder` now captures optional `of ...` constraints and instantiates `TemplateType` instances with bound union types, including support for `@template-contravariant` tokens (still ignored for enforcement).
- ✅ Storage: `TemplateType` caches keyed by identifier + bound, exposes `getBoundUnionType()/hasBound()`, and memoizes `unionTypeSatisfiesBound()` for reuse.
- ✅ Class Enforcement: `Clazz::enforceTemplateConstraintForAncestor()` validates template arguments supplied via `@extends`, `@implements`, and `@use`, emitting the new `PhanTemplateTypeConstraintViolation` (ID 14013) with precise source locations.
- ✅ Function Enforcement: `ArgumentType::analyzeParameter()` inspects template-bearing parameters to verify argument unions respect declared bounds; `FunctionTrait` helpers share argument-to-union extraction logic.
- ✅ Issue Catalogue: Added `PhanTemplateTypeConstraintViolation` to `Issue.php` for downstream suppression/configuration.
- ✅ Tests: `tests/files/src/1103_template_constraint_enforcement.php` covers positive and negative scenarios across classes, traits, and function calls with expected output captured in `tests/files/expected/1103_template_constraint_enforcement.php.expected`.
- ✅ Variance regression: `tests/files/src/1104_template_variance_parsing.php` asserts covariant/contravariant enforcement rules via `tests/files/expected/1104_template_variance_parsing.php.expected`.
- ✅ Existing mismatch fixture (`1102_template_parameter_mismatch.php`) now doubles as a property variance regression, verifying that mutable properties reject covariant templates while read-only ones remain valid.
- ✅ Property variance smoke test (`1105_template_property_variance.php`) ensures covariant templates remain permitted on read-only properties while write-only annotations still trigger violations.
- ✅ Regression Coverage: Extended `1102_template_parameter_mismatch.php` to include variance scaffolding and ensure constraint enforcement coexists with count validation.
- ✅ CI Hooks: `./vendor/bin/phpunit tests/Phan/PhanTestNew.php` now exercises 105 tests (210 assertions) including the new constraint suite.

**What Works Now**:
- ✅ Enforces bounds when instantiating generic parents, interfaces, and traits via PHPDoc (`@extends`, `@implements`, `@use`).
- ✅ Flags mismatched bounds when chaining local templates to ancestor templates (e.g., passing `T` with its own bound).
- ✅ Validates template bounds at function/method call sites, including nested generics inferred via parameter extraction.
- ✅ Reports violations with actionable diagnostics that include template name, ancestor FQSEN, expected bound, actual argument, and usage site.
- ✅ Enforces covariant/contravariant usage in method/function signatures, emitting `PhanTemplateTypeVarianceViolation` when templates appear in incompatible positions.
- ✅ Applies the same variance rules to class properties, allowing covariant templates only on read-only properties (PHP 8.1 `readonly` or doc `@phan-read-only`) and disallowing contravariant templates entirely on properties.

**New Issue Type**:
- `PhanTemplateTypeConstraintViolation` (ID 14013) — emitted when a template argument does not satisfy its declared `of` constraint.
- `PhanTemplateTypeVarianceViolation` (ID 14014) — emitted when variance annotations conflict with usage positions (e.g., covariant templates in parameters).

**Performance Considerations**:
- ✅ Checks reuse existing parameter extraction closures to avoid extra passes.
- ✅ Constraint comparisons rely on cached union IDs to minimize recomputation.

### Phase 2: Template Constraint Enforcement ✅ COMPLETE

**Goal**: Make `@template T of Foo` actually validate types

**Priority**: P2 (High Value, Moderate-High Effort)

**Performance Impact**: Moderate - requires validation at instantiation points

#### Task 2.1: Store Constraint Information ✅ COMPLETE
**Status**: Complete
**Notes**:
- `TemplateType` now caches bounds and variance metadata, keyed by identifier + constraint.
- `Comment/Builder` captures the optional `of ...` clause (and variance keywords) when parsing `@template` tags.
- `TemplateScope`/scope maps preserve the enriched `TemplateType` instances for use during analysis.

**Implementation Notes**:
```php
// Default invariant template without bounds
TemplateType::instanceForId($template_type_identifier, false, null, TemplateType::VARIANCE_INVARIANT);

// Template with explicit bound and variance metadata
TemplateType::instanceForId($template_type_identifier, false, $constraint_union_type, TemplateType::VARIANCE_CONTRAVARIANT);
```

**Constraint Parsing**:
```php
// Builder.php now captures both bounds and variance modifiers
'/@(?:phan-)?template(?:-(?:co|contra)variant)?\s+(?P<identifier>' . self::WORD_REGEX . ')(?:\s+of\s+(?P<constraint>' . UnionType::union_type_regex . '))?/'
```

**Performance Considerations**:
- TemplateType instances are cached - ensure constraint is part of cache key
- Lazy constraint validation (only when template is instantiated)

#### Task 2.2: Validate Constraints at Instantiation ✅ COMPLETE
**Status**: Complete
**Notes**:
- `Clazz::enforceTemplateConstraintForAncestor()` checks docblock instantiations for `@extends`, `@implements`, and `@use`, emitting `PhanTemplateTypeConstraintViolation` on mismatches.
- `ArgumentType::analyzeParameter()` reuses the same logic for template-bearing parameters at call sites, ensuring inferred arguments respect declared bounds.
- Regression suite (`tests/files/src/1103_template_constraint_enforcement.php`) exercises class, trait, and function scenarios.

**Validation Points**:
1. Class inheritance: `@extends Generic<ConcreteType>`
2. Interface implementation: `@implements Repository<User>`
3. Trait usage: `@use Timestamped<DateTime>`
4. Function calls with template inference

**Test Cases**:
```php
/**
 * @template T of \DateTimeInterface
 */
class DateProcessor {
    /** @var T */
    private $date;
}

// OK
/** @extends DateProcessor<\DateTime> */
class Processor1 extends DateProcessor {}

// ERROR: string is not DateTimeInterface
/** @extends DateProcessor<string> */
class Processor2 extends DateProcessor {}
```

**Performance Considerations**:
- Only validate when concrete types are provided
- Skip validation for nested template types (defer to their instantiation)
- Cache validation results

### Phase 3: Variance Enforcement ✅ COMPLeTE

**Goal**: Enforce `@template-covariant` and `@template-contravariant` semantics

**Priority**: P2 (Very High Value, High Effort)

**Performance Impact**: Moderate - requires tracking read/write positions

#### Task 3.1: Implement `@template-contravariant` ✅ COMPLETE
**Status**: Complete
**Notes**:
- `Comment/Builder` and `TemplateType::instanceForId()` now understand the `-(?:co|contra)variant` suffix and cache variance alongside bounds.
- Variance metadata survives cloning/hydration so downstream checks can react appropriately.

**Implementation**:
```php
enum TemplateVariance {
    INVARIANT,    // default @template
    COVARIANT,    // @template-covariant
    CONTRAVARIANT // @template-contravariant
}
```

#### Task 3.2: Track Template Usage Positions ✅ COMPLETE
**Status**: Complete
**Notes**:
- Method/function signatures now emit `PhanTemplateTypeVarianceViolation` when covariant templates appear in parameter types or contravariant templates appear in return types.
- Property variance enforcement inspects real and PHPDoc union types. Covariant templates remain allowed only for truly read-only properties; contravariant templates are rejected on all properties to keep semantics sound; arrays and other nested constructs remain invariant.
- Remaining TODO: extend checks to array shapes and other compound structures once design is finalized.

**Position Tracking**:
- **Read positions** (covariant OK):
  - Return types
  - Readonly properties
  - Method return types

- **Write positions** (contravariant OK):
  - Method parameters
  - Constructor parameters (property usage excluded)

- **Invariant positions** (both):
  - Mutable properties
  - Array type parameters

**Test Cases**:
```php
/**
 * @template-covariant T
 */
class Box {
    /** @var T */
    private $value;

    /** @return T */
    public function get() { return $this->value; }  // OK - read position

    /** @param T $value */
    public function set($value): void { }  // ERROR - write position with covariant
}

/**
 * @template-contravariant T
 */
class Sink {
    /** @param T $value */
    public function consume($value): void { }  // OK - write position

    /** @return T */
    public function produce() { }  // ERROR - read position with contravariant
}
```

**Performance Considerations**:
- Run variance checking only during class analysis (not on every method call)
- Cache variance validation results per class
- Skip variance checking if no covariant/contravariant templates present

#### Task 3.3: Update Comment in Builder.php ✅ COMPLETE
**Status**: Complete
**Estimated Effort**: 5 minutes

Remove the "XXX" comment acknowledging lack of support:
```php
// BEFORE:
case 'template-covariant': // XXX Phan does not actually support @template-covariant semantics

// AFTER:
case 'template-covariant': // Enforces covariant template variance
```

### Phase 4: Advanced Utility Types ✅ COMPLETE

**Goal**: Add Psalm/PHPStan utility types

**Priority**: P4 (Medium Value, Medium Effort)

**Performance Impact**: Low - these are parse-time transformations

#### Task 4.1: `key-of<T>` and `value-of<T>`
**Status**: Complete
**Highlights**:
- Added dedicated `KeyOfType`/`ValueOfType` implementations that expand to literal/int/string unions, falling back to `array-key`/`mixed` when source containers are imprecise.
- Parser recognizes the new utility syntax directly in `Type::fromStringInContext`, allowing template substitution downstream.
- Regression: `tests/files/src/1106_key_value_utility_types.php` asserts both positive and negative scenarios (expected output in `tests/files/expected/1106_key_value_utility_types.php.expected`).

**Example**:
```php
/** @var key-of<array{foo: int, bar: string}> */  // Resolves to 'foo'|'bar'
/** @var value-of<array{foo: int, bar: string}> */  // Resolves to int|string
```

#### Task 4.2: `int-range<min, max>`
**Status**: Complete
**Highlights**:
- Introduced `IntRangeType`, tracking inclusive bounds on specialized integers and interoperating with literal ints during casting.
- Parser normalizes `int-range` template parameters and degrades gracefully when malformed hints are encountered.
- Regression: `tests/files/src/1109_int_range.php` exercises argument/return enforcement against literal values (expected output in `tests/files/expected/1109_int_range.php.expected`).

**Example**:
```php
/** @param int-range<1, 100> $percentage */
function setOpacity(int $percentage): void { }
```

#### Task 4.3: `positive-int`, `negative-int`
**Status**: Complete
**Files to Modify**:
- New types: `PositiveIntType`, `NegativeIntType`

## Testing Strategy

### Test File Naming Convention
- `tests/files/src/XXXX_template_<feature>.php` - Test implementation
- `tests/files/expected/XXXX_template_<feature>.php.expected` - Expected errors
- Use numbers 1100-1199 for template-related tests

### Coverage Goals
- ✅ Basic `@template` - Already covered (0597, 0993, etc.)
- ✅ `@template-implements` - Test 1100 (Complete with 4 scenarios)
- ✅ `@template-use` - Test 1101 (Complete with 4 scenarios)
- ✅ Template parameter mismatch diagnostics - Test 1102 (Missing & extra scenarios)
- ✅ Constraint enforcement - Test 1103 (Class/trait/function bounds)
- ✅ Variance parsing smoke test - Test 1104 (Covariant & contravariant templates)
- ✅ Covariant property variance - Tests 1102 & 1105 (write misuse + readonly allowance)
- ✅ Contravariant property variance - Tests 1102 & 1105 (all property usages rejected)
- ✅ Utility types `key-of`/`value-of` - Test 1106 (shape + generic coverage)
- ✅ Union bounds & callable variance - Test 1107 (union constraints, callable returns, nullable templates)
- ✅ Callable parameter variance - Test 1108 (callable parameter returns enforce variance)
- ✅ Utility type `int-range` - Test 1109 (literal-bound, negative ranges, reversed bounds)
- ✅ Utility types `positive-int`/`negative-int` - Test 1110 (strict literal enforcement)
- ✅ Nested template bound enforcement - Test 1111 (array/callable argument constraints)
- ✅ Intersection template bounds - Test 1112 (multiple-interface requirements)
- ✅ Generic-bound containers - Test 1113 (templates bounded by parameterized types)
- ✅ Utility fallback for `key-of` without generics - Test 1114 (lenient parsing)

### Performance Testing
- Benchmark against Phan's own codebase (before/after)
- Test on large codebases (Symfony, Laravel)
- Target: <5% performance regression

## Implementation Guidelines

### Performance Best Practices
1. **Lazy Evaluation**: Only validate when necessary
2. **Caching**: Cache parsed templates, validated constraints
3. **Early Exit**: Skip checks when templates not present
4. **Incremental**: Don't add new analysis passes

### Code Quality Standards
1. **No False Positives**: When uncertain, don't error
2. **Clear Error Messages**: Include fix suggestions
3. **Backward Compatible**: Existing code should still analyze
4. **Well Tested**: Each feature needs positive and negative tests

### Documentation Requirements
- Update Wiki for each completed feature
- Add examples to `README.md`
- Document performance characteristics
- Note any limitations or edge cases

## Open Questions / Decisions Needed

1. **Should we support `@psalm-` and `@phpstan-` prefixed versions?**
   - Pro: Better compatibility with mixed-toolcodebases
   - Con: Slightly more tolerant parser surface and extra aliases to maintain (negligible cost, but additional complexity)
   - **Decision**: TBD — lean toward enabling if we see demand.

2. **How strict should constraint validation be?**
   - Option A: Always emit `PhanTemplateTypeConstraintViolation`
   - Option B: Gate emission behind configuration for legacy projects
   - **Decision**: Emit by default; consider a future config flag if feedback requires it.

3. **Should variance checking be opt-in or always on?**
   - Option A: Always emit variance issues (current behaviour)
   - Option B: Provide a config toggle for projects easing into variance
   - **Decision**: TBD

## Known Limitations & Design Decisions

### Template Bound Inheritance
**Status:** Not implemented (by design)

Child class templates do **not** automatically inherit bounds declared on parent templates:

```php
/** @template T of Foo */
class Base {}

/** @template T */  // T is unconstrained
class Child extends Base {}
```

Workaround: redeclare the bound explicitly

```php
/** @template T of Foo */
class Child extends Base {}
```

*Rationale:* keeping constraints explicit avoids hidden coupling to parent changes and keeps template contracts self-documenting.

## Performance Benchmarks

### Baseline (before changes)
```bash
# Phan analyzing itself on branch v6 (68a899b8d) — measured 2024-05-16
time ./phan --no-progress-bar
# Output: real 12.93
```

### After Each Phase
- [x] Phase 1 complete: 12.79 seconds (target: <baseline + 5%)
- [ ] Phase 2 complete: _____ seconds (target: <baseline + 8%)
- [ ] Phase 3 complete: _____ seconds (target: <baseline + 10%)

## References

- Psalm Docs: https://psalm.dev/docs/annotating_code/templated_annotations/
- PHPStan Blog: https://phpstan.org/blog/generics-by-examples
- Phan Wiki: https://github.com/phan/phan/wiki/Generic-Types
- Bug #4650: Template return type false positive (fixed in this branch)

## Notes

- This branch is for development only - **DO NOT COMMIT**
- All changes will be reviewed before merging to v6
- Focus on avoiding false positives over catching every edge case
- Performance is critical - measure before and after
