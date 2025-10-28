# Phan Internal Stubs

This folder contains bundled stubs for various PHP extensions with enhanced type information.

## Purpose

These stubs provide:
- **Template annotations** for generic types (e.g., `SplObjectStorage<T>`, `array_filter<T>()`)
- **Improved signatures** with more precise type information than PHP's reflection
- **PHP version compatibility** (e.g., separate SPL stubs for PHP 8.1-8.3 vs 8.4+)

## Default Behavior

Phan automatically loads these stubs by default when:
- No `.phan/config.php` exists (uses built-in defaults from bundled stubs)
- Running `phan --init` (generates config referencing bundled stubs in `vendor/phan/phan/internal/stubs/`)

## Template Annotations

**Classes with templates:**
- SPL: `SplObjectStorage<T>`, `WeakMap<TKey,TValue>`, and iterator classes

**Functions with templates:**
- Standard: `array_filter<T>()`, `array_map<T>()`, `array_find<T>()`, etc.

See `TEMPLATE_ANNOTATIONS.md` for complete documentation of template annotations.
