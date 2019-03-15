<!-- This is mirrored at https://github.com/phan/phan/wiki/Phan-Config-Settings -->
<!-- The copy distributed with Phan is in the internal folder because it may be removed or moved elsewhere -->
<!-- This is regenerated from the comments and defaults in src/Phan/Config.php by the script internal/update_wiki_config_types.php -->

See [`\Phan\Config`](https://github.com/phan/phan/blob/master/src/Phan/Config.php) for the most up to date list of configuration settings.

Table of Contents
=================

Phan's configuration settings fall into the following categories:

- [Configuring Files](#configuring-files)
- [Issue Filtering](#issue-filtering)
- [Analysis](#analysis)
- [Analysis (of a PHP version)](#analysis-of-a-php-version)
- [Type Casting](#type-casting)
- [Dead Code Detection](#dead-code-detection)
- [Output](#Output)

# Configuring Files

These settings can be used to control the files that Phan will parse and analyze,
as well as the order in which these will be analyzed.

## analyzed_file_extensions

List of case-insensitive file extensions supported by Phan.
(e.g. `['php', 'html', 'htm']`)

(Default: `["php"]`)

## consistent_hashing_file_order

Setting this to true makes the process assignment for file analysis
as predictable as possible, using consistent hashing.

Even if files are added or removed, or process counts change,
relatively few files will move to a different group.
(use when the number of files is much larger than the process count)

NOTE: If you rely on Phan parsing files/directories in the order
that they were provided in this config, don't use this.
See [this note in Phan's wiki](https://github.com/phan/phan/wiki/Different-Issue-Sets-On-Different-Numbers-of-CPUs).

(Default: `false`)

## directory_list

A list of directories that should be parsed for class and
method information. After excluding the directories
defined in [`exclude_analysis_directory_list`](#exclude_analysis_directory_list), the remaining
files will be statically analyzed for errors.

Thus, both first-party and third-party code being used by
your application should be included in this list.

(Default: `[]`)

## exclude_analysis_directory_list

A directory list that defines files that will be excluded
from static analysis, but whose class and method
information should be included.

Generally, you'll want to include the directories for
third-party code (such as "vendor/") in this list.

n.b.: If you'd like to parse but not analyze 3rd
      party code, directories containing that code
      should be added to the [`directory_list`](#directory_list) as well as
      to `exclude_analysis_directory_list`.

(Default: `[]`)

## exclude_file_list

A file list that defines files that will be excluded
from parsing and analysis and will not be read at all.

This is useful for excluding hopelessly unanalyzable
files that can't be removed for whatever reason.

(Default: `[]`)

## exclude_file_regex

A regular expression to match files to be excluded
from parsing and analysis and will not be read at all.

This is useful for excluding groups of test or example
directories/files, unanalyzable files, or files that
can't be removed for whatever reason.
(e.g. `'@Test\.php$@'`, or `'@vendor/.*/(tests|Tests)/@'`)

(Default: `""`)

## file_list

A list of individual files to include in analysis
with a path relative to the root directory of the
project.

(Default: `[]`)

## include_analysis_file_list

A file list that defines files that will be included
in static analysis, to the exclusion of others.

(Default: `[]`)

# Issue Filtering

These settings can be used to control what issues show up in Phan's output.

## disable_file_based_suppression

Set to true in order to ignore file-based issue suppressions.

(Default: `false`)

## disable_line_based_suppression

Set to true in order to ignore line-based issue suppressions.
Disabling both line and file-based suppressions is mildly faster.

(Default: `false`)

## disable_suppression

Set to true in order to ignore issue suppression.
This is useful for testing the state of your code, but
unlikely to be useful outside of that.

(Default: `false`)

## minimum_severity

The minimum severity level to report on. This can be
set to `Issue::SEVERITY_LOW`, `Issue::SEVERITY_NORMAL` or
`Issue::SEVERITY_CRITICAL`. Setting it to only
critical issues is a good place to start on a big
sloppy mature code base.

(Default: `Issue::SEVERITY_LOW`)

## suppress_issue_types

Add any issue types (such as `'PhanUndeclaredMethod'`)
to this black-list to inhibit them from being reported.

(Default: `[]`)

## whitelist_issue_types

If empty, no filter against issues types will be applied.
If this white-list is non-empty, only issues within the list
will be emitted by Phan.

See https://github.com/phan/phan/wiki/Issue-Types-Caught-by-Phan
for the full list of issues that Phan detects.

Phan is capable of detecting hundreds of types of issues.
Projects should almost always use [`suppress_issue_types`](#suppress_issue_types) instead.

(Default: `[]`)

# Analysis

These configuration settings affects the way that Phan analyzes your project.
(E.g. they may enable/disable additional checks, or change the way that certain checks are carried out.)

## allow_missing_properties

If enabled, missing properties will be created when
they are first seen. If false, we'll report an
error message if there is an attempt to write
to a class property that wasn't explicitly
defined.

(Default: `false`)

## analyze_signature_compatibility

If enabled, check all methods that override a
parent method to make sure its signature is
compatible with the parent's.

This check can add quite a bit of time to the analysis.

This will also check if final methods are overridden, etc.

(Default: `true`)

## autoload_internal_extension_signatures

You can put paths to stubs of internal extensions in this config option.
If the corresponding extension is **not** loaded, then Phan will use the stubs instead.
Phan will continue using its detailed type annotations,
but load the constants, classes, functions, and classes (and their Reflection types)
from these stub files (doubling as valid php files).
Use a different extension from php to avoid accidentally loading these.
The `tools/make_stubs` script can be used to generate your own stubs (compatible with php 7.0+ right now)

(e.g. `['xdebug' => '.phan/internal_stubs/xdebug.phan_php']`)

(Default: `[]`)

## backward_compatibility_checks

Backwards Compatibility Checking. This is slow
and expensive, but you should consider running
it before upgrading your version of PHP to a
new version that has backward compatibility
breaks.

If you are migrating from PHP 5 to PHP 7,
you should also look into using
[php7cc (no longer maintained)](https://github.com/sstalle/php7cc)
and [php7mar](https://github.com/Alexia/php7mar),
which have different backwards compatibility checks.

(Default: `true`)

## cache_polyfill_asts

If enabled, Phan will cache ASTs generated by the polyfill/fallback to disk
(except when running in the background as a language server/daemon)

ASTs generated by the native AST library (php-ast) are never cached,
because php-ast is faster than loading and unserializing data from the cache.

Disabling this is faster when the cache won't be reused,
e.g. if this would be run in a docker image without mounting the cache as a volume.

The cache can be found at `sys_get_tmp_dir() . "/phan-$USERNAME"`.

(Default: `true`)

## check_docblock_signature_param_type_match

If true, check to make sure the param types declared
in the doc-block (if any) matches the param types
declared in the method signature.

(Default: `true`)

## check_docblock_signature_return_type_match

If true, check to make sure the return type declared
in the doc-block (if any) matches the return type
declared in the method signature.

(Default: `true`)

## enable_class_alias_support

If true, Phan will read `class_alias()` calls in the global scope, then

1. create aliases from the *parsed* files if no class definition was found, and
2. emit issues in the global scope if the source or target class is invalid.
   (If there are multiple possible valid original classes for an aliased class name,
   the one which will be created is unspecified.)

NOTE: THIS IS EXPERIMENTAL, and the implementation may change.

(Default: `false`)

## enable_extended_internal_return_type_plugins

Set this to true to enable the plugins that Phan uses to infer more accurate return types of `implode`, `json_decode`, and many other functions.

Phan is slightly faster when these are disabled.

(Default: `false`)

## enable_include_path_checks

Enable this to enable checks of require/include statements referring to valid paths.

(Default: `false`)

## enable_internal_return_type_plugins

Set this to false to disable the plugins that Phan uses to infer more accurate return types of `array_map`, `array_filter`, and many other functions.

Phan is slightly faster when these are disabled.

(Default: `true`)

## exception_classes_with_optional_throws_phpdoc

Phan will not warn about lack of documentation of `@throws` for any of the configured classes or their subclasses.
This only matters when [`warn_about_undocumented_throw_statements`](#warn_about_undocumented_throw_statements) is true.
The default is the empty array (Don't suppress any warnings)

(E.g. `['RuntimeException', 'AssertionError', 'TypeError']`)

(Default: `[]`)

## generic_types_enabled

Enable or disable support for generic templated
class types.

(Default: `true`)

## globals_type_map

Override to hardcode existence and types of (non-builtin) globals in the global scope.
Class names should be prefixed with `\`.

(E.g. `['_FOO' => '\FooClass', 'page' => '\PageClass', 'userId' => 'int']`)

(Default: `[]`)

## guess_unknown_parameter_type_using_default

Set this to true to make Phan guess that undocumented parameter types
(for optional parameters) have the same type as default values
(Instead of combining that type with `mixed`).

E.g. `function my_method($x = 'val')` would make Phan infer that `$x` had a type of `string`, not `string|mixed`.
Phan will not assume it knows specific types if the default value is `false` or `null`.

(Default: `false`)

## ignore_undeclared_functions_with_known_signatures

Set this to false to emit `PhanUndeclaredFunction` issues for internal functions that Phan has signatures for,
but aren't available in the codebase, or the internal functions used to run Phan
(may lead to false positives if an extension isn't loaded)

If this is true(default), then Phan will not warn.

(Default: `true`)

## ignore_undeclared_variables_in_global_scope

If true, seemingly undeclared variables in the global
scope will be ignored.

This is useful for projects with complicated cross-file
globals that you have no hope of fixing.

(Default: `false`)

## include_paths

A list of [include paths](https://secure.php.net/manual/en/ini.core.php#ini.include-path) to check when checking if `require_once`, `include`, etc. are pointing to valid files.

To refer to the directory of the file being analyzed, use `'.'`
To refer to the project root directory, use \Phan\Config::getProjectRootDirectory()

(E.g. `['.', \Phan\Config::getProjectRootDirectory() . '/src/folder-added-to-include_path']`)

This is ignored if [`enable_include_path_checks`](#enable_include_path_checks) is not `true`.

(Default: `["."]`)

## inherit_phpdoc_types

If enabled, inherit any missing phpdoc for types from
the parent method if none is provided.

NOTE: This step will only be performed if [`analyze_signature_compatibility`](#analyze_signature_compatibility) is also enabled.

(Default: `true`)

## max_literal_string_type_length

If a literal string type exceeds this length,
then Phan converts it to a regular string type.
This setting cannot be less than 50.

This setting can be overridden if users wish to store strings that are even longer than 50 bytes.

(Default: `200`)

## parent_constructor_required

A set of fully qualified class-names for which
a call to `parent::__construct()` is required.

(Default: `[]`)

## phpdoc_type_mapping

This setting maps case-insensitive strings to union types.

This is useful if a project uses phpdoc that differs from the phpdoc2 standard.

If the corresponding value is the empty string,
then Phan will ignore that union type (E.g. can ignore 'the' in `@return the value`)

If the corresponding value is not empty,
then Phan will act as though it saw the corresponding UnionTypes(s)
when the keys show up in a UnionType of `@param`, `@return`, `@var`, `@property`, etc.

This matches the **entire string**, not parts of the string.
(E.g. `@return the|null` will still look for a class with the name `the`, but `@return the` will be ignored with the below setting)

(These are not aliases, this setting is ignored outside of doc comments).
(Phan does not check if classes with these names exist)

Example setting: `['unknown' => '', 'number' => 'int|float', 'char' => 'string', 'long' => 'int', 'the' => '']`

(Default: `[]`)

## plugin_config

This can be used by third-party plugins that expect configuration.

E.g. this is used by `InvokePHPNativeSyntaxCheckPlugin`

(Default: `[]`)

## plugins

A list of plugin files to execute.

Plugins which are bundled with Phan can be added here by providing their name (e.g. `'AlwaysReturnPlugin'`)

Documentation about available bundled plugins can be found [here](https://github.com/phan/phan/tree/master/.phan/plugins).

Alternately, you can pass in the full path to a PHP file with the plugin's implementation (e.g. `'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php'`)

(Default: `[]`)

## prefer_narrowed_phpdoc_param_type

If true, make narrowed types from phpdoc params override
the real types from the signature, when real types exist.
(E.g. allows specifying desired lists of subclasses,
 or to indicate a preference for non-nullable types over nullable types)

Affects analysis of the body of the method and the param types passed in by callers.

(*Requires [`check_docblock_signature_param_type_match`](#check_docblock_signature_param_type_match) to be true*)

(Default: `true`)

## prefer_narrowed_phpdoc_return_type

(*Requires [`check_docblock_signature_return_type_match`](#check_docblock_signature_return_type_match) to be true*)

If true, make narrowed types from phpdoc returns override
the real types from the signature, when real types exist.

(E.g. allows specifying desired lists of subclasses,
or to indicate a preference for non-nullable types over nullable types)

This setting affects the analysis of return statements in the body of the method and the return types passed in by callers.

(Default: `true`)

## processes

The number of processes to fork off during the analysis
phase.

(Default: `1`)

## quick_mode

If true, this runs a quick version of checks that takes less
time at the cost of not running as thorough
of an analysis. You should consider setting this
to true only when you wish you had more **undiagnosed** issues
to fix in your code base.

In quick-mode the scanner doesn't rescan a function
or a method's code block every time a call is seen.
This means that the problem here won't be detected:

```php
<?php
function test($arg):int {
    return $arg;
}
test("abc");
```

This would normally generate:

```
test.php:3 PhanTypeMismatchReturn Returning type string but test() is declared to return int
```

The initial scan of the function's code block has no
type information for `$arg`. It isn't until we see
the call and rescan `test()`'s code block that we can
detect that it is actually returning the passed in
`string` instead of an `int` as declared.

(Default: `false`)

## read_magic_method_annotations

If disabled, Phan will not read docblock type
annotation comments for `@method`.

Note: [`read_type_annotations`](#read_type_annotations) must also be enabled.

(Default: `true`)

## read_magic_property_annotations

If disabled, Phan will not read docblock type
annotation comments for `@property`.

- When enabled, in addition to inferring existence of magic properties,
  Phan will also warn when writing to `@property-read` and reading from `@property-read`.
Phan will warn when writing to read-only properties and reading from write-only properties.

Note: [`read_type_annotations`](#read_type_annotations) must also be enabled.

(Default: `true`)

## read_type_annotations

If disabled, Phan will not read docblock type
annotation comments (such as for `@return`, `@param`,
`@var`, `@suppress`, `@deprecated`) and only rely on
types expressed in code.

(Default: `true`)

## runkit_superglobals

A custom list of additional superglobals and their types. **Only needed by projects using runkit/runkit7.**

(Corresponding keys are declared in `runkit.superglobal` ini directive)

[`globals_type_map`](#globals_type_map) should be set for setting the types of these superglobals.
E.g `['_FOO']`;

(Default: `[]`)

## simplify_ast

If true, then before analysis, try to simplify AST into a form
which improves Phan's type inference in edge cases.

This may conflict with [`dead_code_detection`](#dead_code_detection).
When this is true, this slows down analysis slightly.

E.g. rewrites `if ($a = value() && $a > 0) {...}`
into `$a = value(); if ($a) { if ($a > 0) {...}}`

(Default: `true`)

## warn_about_relative_include_statement

Enable this to warn about the use of relative paths in `require_once`, `include`, etc.
Relative paths are harder to reason about, and opcache may have issues with relative paths in edge cases.

This is ignored if [`enable_include_path_checks`](#enable_include_path_checks) is not `true`.

(Default: `false`)

## warn_about_undocumented_throw_statements

If enabled, warn about throw statement where the exception types
are not documented in the PHPDoc of functions, methods, and closures.

(Default: `false`)

# Analysis (of a PHP Version)

These settings affect the way that Phan analyzes your project.
The values you will want depend on what PHP versions you are checking for compatibility with.

## allow_method_param_type_widening

Set this to true to allow contravariance in real parameter types of method overrides
(Users may enable this if analyzing projects that support only php 7.2+)

See [this note about PHP 7.2's new features](https://secure.php.net/manual/en/migration72.new-features.php#migration72.new-features.param-type-widening).
This is false by default. (By default, Phan will warn if real parameter types are omitted in an override)

If this is overridden to be null, this will be inferred from `target_php_version`.

(Default: `false`)

## polyfill_parse_all_element_doc_comments

Make the tolerant-php-parser polyfill generate doc comments
for all types of elements, even if php-ast wouldn't (for an older PHP version)

(Default: `true`)

## pretend_newer_core_methods_exist

Default: true. If this is set to true,
and `target_php_version` is newer than the version used to run Phan,
Phan will act as though functions added in newer PHP versions exist.

NOTE: Currently, this only affects `Closure::fromCallable()`

(Default: `true`)

## target_php_version

Supported values: `'5.6'`, `'7.0'`, `'7.1'`, `'7.2'`, `'7.3'`, `'7.4'`, `null`.
If this is set to `null`,
then Phan assumes the PHP version which is closest to the minor version
of the php executable used to execute Phan.

Note that the **only** effect of choosing `'5.6'` is to infer that functions removed in php 7.0 exist.
(See [`backward_compatibility_checks`](#backward_compatibility_checks) for additional options)

(Default: `null`)

# Type Casting

These configuration settings affect the rules Phan uses to check if a given type can be cast to another type.
These affect what issues will be emitted, as well as the types that Phan will infer for elements.

## array_casts_as_null

If enabled, allow any array-like type to be cast to null.
This is an incremental step in migrating away from [`null_casts_as_any_type`](#null_casts_as_any_type).
If [`null_casts_as_any_type`](#null_casts_as_any_type) is true, this has no effect.

(Default: `false`)

## null_casts_as_any_type

If enabled, null can be cast to any type and any
type can be cast to null. Setting this to true
will cut down on false positives.

(Default: `false`)

## null_casts_as_array

If enabled, allow null to be cast as any array-like type.

This is an incremental step in migrating away from [`null_casts_as_any_type`](#null_casts_as_any_type).
If [`null_casts_as_any_type`](#null_casts_as_any_type) is true, this has no effect.

(Default: `false`)

## scalar_array_key_cast

If enabled, any scalar array keys (int, string)
are treated as if they can cast to each other.
E.g. `array<int,stdClass>` can cast to `array<string,stdClass>` and vice versa.
Normally, a scalar type such as int could only cast to/from int and mixed.

(Default: `false`)

## scalar_implicit_cast

If enabled, scalars (int, float, bool, string, null)
are treated as if they can cast to each other.
This does not affect checks of array keys. See [`scalar_array_key_cast`](#scalar_array_key_cast).

(Default: `false`)

## scalar_implicit_partial

If this has entries, scalars (int, float, bool, string, null)
are allowed to perform the casts listed.

E.g. `['int' => ['float', 'string'], 'float' => ['int'], 'string' => ['int'], 'null' => ['string']]`
allows casting null to a string, but not vice versa.
(subset of [`scalar_implicit_cast`](#scalar_implicit_cast))

(Default: `[]`)

## strict_method_checking

If enabled, Phan will warn if **any** type in a method invocation's object
is definitely not an object,
or if **any** type in an invoked expression is not a callable.
Setting this to true will introduce numerous false positives
(and reveal some bugs).

(Default: `false`)

## strict_param_checking

If enabled, Phan will warn if **any** type in the argument's union type
cannot be cast to a type in the parameter's expected union type.
Setting this to true will introduce numerous false positives
(and reveal some bugs).

(Default: `false`)

## strict_property_checking

If enabled, Phan will warn if **any** type in a property assignment's union type
cannot be cast to a type in the property's declared union type.
Setting this to true will introduce numerous false positives
(and reveal some bugs).

(Default: `false`)

## strict_return_checking

If enabled, Phan will warn if **any** type in a returned value's union type
cannot be cast to the declared return type.
Setting this to true will introduce numerous false positives
(and reveal some bugs).

(Default: `false`)

# Dead Code Detection

These settings affect how Phan will track what elements are referenced to warn about them.

## dead_code_detection

Set to true in order to attempt to detect dead
(unreferenced) code. Keep in mind that the
results will only be a guess given that classes,
properties, constants and methods can be referenced
as variables (like `$class->$property` or
`$class->$method()`) in ways that we're unable
to make sense of.

(Default: `false`)

## dead_code_detection_prefer_false_negative

If true, the dead code detection rig will
prefer false negatives (not report dead code) to
false positives (report dead code that is not
actually dead).

In other words, the graph of references will have
too many edges rather than too few edges when guesses
have to be made about what references what.

(Default: `true`)

## force_tracking_references

Set to true in order to force tracking references to elements
(functions/methods/consts/protected).

[`dead_code_detection`](#dead_code_detection) is another option which also causes references
to be tracked.

(Default: `false`)

## unused_variable_detection

Set to true in order to attempt to detect unused variables.
[`dead_code_detection`](#dead_code_detection) will also enable unused variable detection.

This has a few known false positives, e.g. for loops or branches.

(Default: `false`)

## warn_about_redundant_use_namespaced_class

Enable this to warn about harmless redundant use for classes and namespaces such as `use Foo\bar` in namespace Foo.

Note: This does not affect warnings about redundant uses in the global namespace.

(Default: `false`)

# Output

These settings will affect how the issues that Phan detects will be output,
as well as how Phan will warn about being misconfigured.

## color_scheme

Allow overriding color scheme in `.phan/config.php` for printing issues, for individual types.

See the keys of `Phan\Output\Colorizing::STYLES` for valid color names,
and the keys of `Phan\Output\Colorizing::DEFAULT_COLOR_FOR_TEMPLATE` for valid color names.

E.g. to change the color for the file (of an issue instance) to red, set this to `['FILE' => 'red']`

E.g. to use the terminal's default color for the line (of an issue instance), set this to `['LINE' => 'none']`

(Default: `[]`)

## disable_suggestions

Set this to true to disable suggestions for what to use instead of undeclared variables/classes/etc.

(Default: `false`)

## skip_missing_tokenizer_warning

By default, Phan will warn if the 'tokenizer' module isn't installed and enabled.

(Default: `false`)

## skip_slow_php_options_warning

By default, Phan will log error messages to stdout if PHP is using options that slow the analysis.
(e.g. PHP is compiled with `--enable-debug` or when using XDebug)

(Default: `false`)

## suggestion_check_limit

Phan will give up on suggesting a different name in issue messages
if the number of candidates (for a given suggestion category) is greater than `suggestion_check_limit`.

Set this to `0` to disable most suggestions for similar names, and only suggest identical names in other namespaces.
Set this to `PHP_INT_MAX` (or other large value) to always suggest similar names and identical names in other namespaces.

Phan will be a bit slower when this config setting is large.
A lower value such as 50 works for suggesting misspelled classes/constants in namespaces,
but won't give you suggestions for globally namespaced functions.

(Default: `1000`)
