# Template Annotations Inventory - Phan Internal Stubs

## Overview
This document tracks all hand-crafted template annotations in `.phan/internal_stubs/` that MUST be preserved during stub regeneration.

Generated: 2025-10-27

---

## 1. spl.phan_php (PHP 8.4+)

### SplObjectStorage
**Location:** Lines 741-758
```php
/**
 * @template TObject of object
 * @template TValue
 * @implements Iterator<int, TObject>
 * @implements ArrayAccess<TObject, TValue>
 */
class SplObjectStorage implements \Countable, \SeekableIterator, \Serializable, \ArrayAccess {
    /**
     * @suppress PhanGenericConstructorTypes
     */
    public function __construct() {}
```

**Method-level annotations:**
- `attach(object $object, mixed $info)`: `@param TObject $object`, `@param TValue $info`
- `detach(object $object)`: `@param TObject $object`
- `contains(object $object)`: `@param TObject $object`
- `addAll(\SplObjectStorage $storage)`: `@param SplObjectStorage<TObject, TValue> $storage`
- `removeAll(\SplObjectStorage $storage)`: `@param SplObjectStorage<TObject, TValue> $storage`
- `removeAllExcept(\SplObjectStorage $storage)`: `@param SplObjectStorage<TObject, TValue> $storage`
- `getInfo()`: `@return TValue`
- `setInfo(mixed $info)`: `@param TValue $info`
- `current()`: `@return TObject`
- `offsetExists($object)`: `@param TObject $object`
- `offsetGet($object)`: `@param TObject $object`, `@return TValue`
- `offsetSet($object, mixed $info)`: `@param TObject $object`, `@param TValue $info`
- `offsetUnset($object)`: `@param TObject $object`

### WeakMap
**Location:** Lines 927-957
**Note:** WeakMap is actually from Core extension but kept in SPL stub for templating convenience

```php
/**
 * @template TKey of object
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class WeakMap implements \ArrayAccess, \IteratorAggregate, \Countable {
```

**Method-level annotations:**
- `offsetGet(object $object)`: `@param TKey $object`, `@return TValue`
- `offsetSet(object $object, mixed $value)`: `@param TKey $object`, `@param TValue $value`
- `offsetExists(object $object)`: `@param TKey $object`
- `offsetUnset(object $object)`: `@param TKey $object`
- `getIterator()`: `@return \Iterator<TKey, TValue>`

---

## 2. spl_php81.phan_php (PHP 8.1-8.3)

### SplObjectStorage
**Location:** Lines 784-849
**Note:** Same template annotations as spl.phan_php but implements `\Iterator` instead of `\SeekableIterator` (seek() method added in PHP 8.4)

```php
/**
 * @template TObject of object
 * @template TValue
 * @implements Iterator<int, TObject>
 * @implements ArrayAccess<TObject, TValue>
 */
class SplObjectStorage implements \Countable, \Traversable, \Iterator, \Serializable, \ArrayAccess {
```

All method-level annotations same as spl.phan_php above.

### WeakMap
**Location:** Lines 966-996
**Note:** Identical to spl.phan_php version

---

## 3. standard_templates.phan_php

**Purpose:** Provides template annotations for standard library functions that preserve types through transformations.

### Functions with Templates:

#### array_find
```php
/**
 * @template TKey
 * @template TValue
 * @param array<TKey, TValue> $array
 * @param callable(TValue, TKey): bool $callback
 * @return TValue|null
 */
function array_find(array $array, callable $callback) {}
```

#### array_find_key
```php
/**
 * @template TKey
 * @template TValue
 * @param array<TKey, TValue> $array
 * @param callable(TValue, TKey): bool $callback
 * @return TKey|null
 */
function array_find_key(array $array, callable $callback) {}
```

#### array_any
```php
/**
 * @template TKey
 * @template TValue
 * @param array<TKey, TValue> $array
 * @param callable(TValue, TKey): bool $callback
 */
function array_any(array $array, callable $callback): bool {}
```

#### array_all
```php
/**
 * @template TKey
 * @template TValue
 * @param array<TKey, TValue> $array
 * @param callable(TValue, TKey): bool $callback
 */
function array_all(array $array, callable $callback): bool {}
```

---

## Preservation Strategy

1. **Never regenerate these files directly** - Always use manual merging
2. **Template extraction script**: Create script to extract templates before regeneration
3. **Template merge script**: Create script to re-inject templates after regeneration
4. **Validation**: Ensure templates are intact after any stub updates

---

## PHP Version Compatibility

| File | PHP 8.1 | PHP 8.2 | PHP 8.3 | PHP 8.4 | PHP 8.5 |
|------|---------|---------|---------|---------|---------|
| spl.phan_php | ✗ | ✗ | ✗ | ✓ | ✓ |
| spl_php81.phan_php | ✓ | ✓ | ✓ | ✗ | ✗ |
| standard_templates.phan_php | ✓ | ✓ | ✓ | ✓ | ✓ |

**Key difference:** PHP 8.4+ supports typed constants and SplObjectStorage::seek() method
