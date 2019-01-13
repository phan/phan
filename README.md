Phan is a static analyzer for PHP that prefers to minimize false-positives. Phan attempts to prove incorrectness rather than correctness.

Phan looks for common issues and will verify type compatibility on various operations when type
information is available or can be deduced. Phan has a good (but not comprehensive) understanding of flow control
and does not attempt to track values.

[![Maintainability](https://api.codeclimate.com/v1/badges/3940135c0dfbd5387c94/maintainability)](https://codeclimate.com/github/phan/phan/maintainability) [![Build Status](https://travis-ci.org/phan/phan.svg?branch=master)](https://travis-ci.org/phan/phan) [![Build Status (Windows)](https://ci.appveyor.com/api/projects/status/github/phan/phan?branch=master&svg=true)](https://ci.appveyor.com/project/TysonAndre/phan/branch/master)
[![Gitter](https://badges.gitter.im/phan/phan.svg)](https://gitter.im/phan/phan?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Latest Stable Version](https://img.shields.io/packagist/v/phan/phan.svg)](https://packagist.org/packages/phan/phan)
[![License](https://img.shields.io/packagist/l/phan/phan.svg)](https://github.com/phan/phan/blob/master/LICENSE)

# Getting Started

The easiest way to use Phan is via Composer.

```
composer require phan/phan
```

With Phan installed, you'll want to [create a `.phan/config.php` file](https://github.com/phan/phan/wiki/Getting-Started#creating-a-config-file) in
your project to tell Phan how to analyze your source code. Once configured, you can run it via `./vendor/bin/phan`.

This version (branch) of Phan depends on PHP 7.x with the [php-ast](https://github.com/nikic/php-ast) extension (0.1.5 or newer, uses AST version 50) and supports PHP version 7.0-7.2 syntax.
The master branch is the basis for the 1.x.y releases.
Installation instructions for php-ast can be found [here](https://github.com/nikic/php-ast#installation).
Having PHP's `pcntl` extension installed is strongly recommended (not available on Windows), in order to support using parallel processes for analysis
(`pcntl` is recommended for daemon mode and LSP to work efficiently, but both should work without that extension).

* **Alternative Installation Methods**<br />
  See [Getting Started](https://github.com/phan/phan/wiki/Getting-Started) for alternative methods of using
Phan and details on how to configure Phan for your project.<br />
* **Incrementally Strengthening Analysis**<br />
  Take a look at [Incrementally Strengthening Analysis](https://github.com/phan/phan/wiki/Incrementally-Strengthening-Analysis) for some tips on how to slowly ramp up the strictness of the analysis as your code becomes better equipped to be analyzed. <br />
* **Installing Dependencies**<br />
  Take a look at [Installing Phan Dependencies](https://github.com/phan/phan/wiki/Getting-Started#installing-phan-dependencies) for help getting Phan's dependencies installed on your system.

# Features

Phan is able to perform the following kinds of analysis.

* Check that all methods, functions, classes, traits, interfaces, constants, properties and variables are defined and accessible.
* Check for type safety and arity issues on method/function/closure calls.
* Check for PHP7/PHP5 backward compatibility.
* Check for features that weren't supported in older PHP 7.x minor releases (E.g. `object`, `void`, `iterable`, `?T`, `[$x] = ...;`, negative string offsets, multiple exception catches, etc.)
* Check for sanity with array accesses.
* Check for type safety on binary operations.
* Check for valid and type safe return values on methods, functions, and closures.
* Check for No-Ops on arrays, closures, constants, properties, variables, unary operators, and binary operators.
* Check for unused/dead/[unreachable](https://github.com/phan/phan/tree/master/.phan/plugins#unreachablecodepluginphp) code. (Pass in `--dead-code-detection`)
* Check for unused variables and parameters. (Pass in `--unused-variable-detection`)
* Check for unused `use` statements.
* Check for classes, functions and methods being redefined.
* Check for sanity with class inheritance (e.g. checks method signature compatibility).
  Phan also checks for final classes/methods being overridden, that abstract methods are implemented, and that the implemented interface is really an interface (and so on).
* Supports namespaces, traits and variadics.
* Supports [Union Types](https://github.com/phan/phan/wiki/About-Union-Types).
* Supports generic arrays such as `int[]`, `UserObject[]`, `array<int,UserObject>`, etc..
* Supports array shapes such as `array{key:string,otherKey:?stdClass}`, etc. (internally and in PHPDoc tags)
  This also supports indicating that fields of an array shape are optional
  via `array{requiredKey:string,optionalKey?:string}` (useful for `@param`)
* Supports phpdoc [type annotations](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code).
* Supports inheriting phpdoc type annotations.
* Supports checking that phpdoc type annotations are a narrowed form (E.g. subclasses/subtypes) of the real type signatures
* Supports inferring types from [assert() statements](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code) and conditionals in if elements/loops.
* Supports [`@deprecated` annotation](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code#deprecated) for deprecating classes, methods and functions
* Supports [`@internal` annotation](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code#internal) for elements (such as a constant, function, class, class constant, property or method) as internal to the package in which it's defined.
* Supports `@suppress <ISSUE_TYPE>` annotations for [suppressing issues](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code#suppress).
* Supports [magic @property annotations](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code#property) (partial) (`@property <union_type> <variable_name>`)
* Supports [magic @method annotations](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code#method) (`@method <union_type> <method_name>(<union_type> <param1_name>)`)
* Supports [`class_alias` annotations (experimental, off by default)](https://github.com/phan/phan/pull/586)
* Supports indicating the class to which a closure will be bound, via `@phan-closure-scope` ([example](tests/files/src/0264_closure_override_context.php))
* Supports analysis of closures and return types passed to `array_map`, `array_filter`, and other internal array functions.
* Offers extensive configuration for weakening the analysis to make it useful on large sloppy code bases
* Can be run on many cores. (requires `pcntl`)
* [Can run in the background (daemon mode)](https://github.com/phan/phan/wiki/Using-Phan-Daemon-Mode), to then quickly respond to requests to analyze the latest version of a file.
  This can also act as a linter in the [Language Server Protocol](https://github.com/Microsoft/language-server-protocol).
  Parts of the language server implementation are based on [felixfbecker/php-language-server](https://github.com/felixfbecker/php-language-server).
  While running in the background, Phan can be used from [various editors](https://github.com/phan/phan/wiki/Editor-Support).
* Output is emitted in text, checkstyle, json, pylint, csv, or codeclimate formats.
* Can run [user plugins on source for checks specific to your code](https://github.com/phan/phan/wiki/Writing-Plugins-for-Phan).
  [Phan includes various plugins you may wish to enable for your project](https://github.com/phan/phan/tree/master/.phan/plugins#2-general-use-plugins).

See [Phan Issue Types](https://github.com/phan/phan/wiki/Issue-Types-Caught-by-Phan) for descriptions
and examples of all issues that can be detected by Phan. Take a look at the
[\Phan\Issue](https://github.com/phan/phan/blob/master/src/Phan/Issue.php) to see the
definition of each error type.

Take a look at the [Tutorial for Analyzing a Large Sloppy Code Base](https://github.com/phan/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base) to get a sense of what the process of doing ongoing analysis might look like for you.

See the [tests](https://github.com/phan/phan/blob/master/tests/files) directory for some examples of the various checks.

Phan is imperfect and shouldn't be used to prove that your PHP-based rocket guidance system is free of defects.

## Features provided by plugins

Additional analysis features have been provided by [plugins](https://github.com/phan/phan/tree/master/.phan/plugins#plugins).

- [Checking for syntactically unreachable statements](https://github.com/phan/phan/tree/master/.phan/plugins#unreachablecodepluginphp) (E.g. `{ throw new Exception("Message"); return $value; }`)
- [Checking `*printf()` format strings against the provided arguments](https://github.com/phan/phan/tree/master/.phan/plugins#printfcheckerplugin) (as well as checking for common errors)
- [Checking that PCRE regexes passed to `preg_*()` are valid](https://github.com/phan/phan/tree/master/.phan/plugins#pregregexcheckerplugin)
- [Checking for `@suppress` annotations that are no longer needed.](https://github.com/phan/phan/tree/master/.phan/plugins#unusedsuppressionpluginphp)
- [Checking for duplicate or missing array keys.](https://github.com/phan/phan/tree/master/.phan/plugins#duplicatearraykeypluginphp)
- [Checking coding style conventions](https://github.com/phan/phan/tree/master/.phan/plugins#3-plugins-specific-to-code-styles)
- [Others](https://github.com/phan/phan/tree/master/.phan/plugins#plugins)

Example: [Phan's plugins for self-analysis.](https://github.com/phan/phan/blob/1.0.0/.phan/config.php#L542-L563)

# Usage

Phan needs to be configured with details on where to find code to analyze and how to analyze it. The
easiest way to tell Phan where to find source code is to [create a `.phan/config.php` file](https://github.com/phan/phan/wiki/Getting-Started#creating-a-config-file).
A simple `.phan/config.php` file might look something like the following.

```php
<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 */
return [

    // Supported values: '7.0', '7.1', '7.2', '7.3', null.
    // If this is set to null,
    // then Phan assumes the PHP version which is closest to the minor version
    // of the php executable used to execute phan.
    "target_php_version" => null,

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'src',
        'vendor/symfony/console',
    ],

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to the `directory_list` as
    //       to `exclude_analysis_directory_list`.
    "exclude_analysis_directory_list" => [
        'vendor/'
    ],

    // A list of plugin files to execute.
    // See https://github.com/phan/phan/tree/master/.phan/plugins for even more.
    // (Pass these in as relative paths.
    // Base names without extensions such as 'AlwaysReturnPlugin'
    // can be used to refer to a plugin that is bundled with Phan)
    'plugins' => [
        // checks if a function, closure or method unconditionally returns.

        // can also be written as 'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php'
        'AlwaysReturnPlugin',
        // Checks for syntactically unreachable statements in
        // the global scope or function bodies.
        'UnreachableCodePlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
    ],
];
```

Take a look at [Creating a Config File](https://github.com/phan/phan/wiki/Getting-Started#creating-a-config-file) and
[Incrementally Strengthening Analysis](https://github.com/phan/phan/wiki/Incrementally-Strengthening-Analysis) for
more details.

Running `phan --help` will show usage information and command-line options.

```
Usage: ./phan [options] [files...]
 -f, --file-list <filename>
  A file containing a list of PHP files to be analyzed

 -l, --directory <directory>
  A directory that should be parsed for class and
  method information. After excluding the directories
  defined in --exclude-directory-list, the remaining
  files will be statically analyzed for errors.

  Thus, both first-party and third-party code being used by
  your application should be included in this list.

  You may include multiple `--directory DIR` options.

 --exclude-file <file>
  A file that should not be parsed or analyzed (or read
  at all). This is useful for excluding hopelessly
  unanalyzable files.

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories that defines files
  that will be excluded from static analysis, but whose
  class and method information should be included.

  Generally, you'll want to include the directories for
  third-party code (such as "vendor/") in this list.

 --include-analysis-file-list <file_list>
  A comma-separated list of files that will be included in
  static analysis. All others won't be analyzed.

  This is primarily intended for performing standalone
  incremental analysis.

 -d, --project-root-directory </path/to/project>
  Hunt for a directory named `.phan` in the provided directory
  and read configuration file `.phan/config.php` from that path.

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is unlikely to be useful.

 -k, --config-file
  A path to a config file to load (instead of the default of
  `.phan/config.php`).

 -m <mode>, --output-mode
  Output mode from 'text', 'json', 'csv', 'codeclimate', 'checkstyle', or 'pylint'

 -o, --output <filename>
  Output filename

 --init
   [--init-level=3]
   [--init-analyze-dir=path/to/src]
   [--init-analyze-file=path/to/file.php]
   [--init-no-composer]

  Generates a `.phan/config.php` in the current directory
  based on the project's composer.json.
  The logic used to generate the config file is currently very simple.
  Some third party classes (e.g. in vendor/)
  will need to be manually added to 'directory_list' or excluded,
  and you may end up with a large number of issues to be manually suppressed.
  See https://github.com/phan/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base

  [--init-level] affects the generated settings in `.phan/config.php`
    (e.g. null_casts_as_array).
    `--init-level` can be set to 1 (strictest) to 5 (least strict)
  [--init-analyze-dir] can be used as a relative path alongside directories
    that Phan infers from composer.json's "autoload" settings
  [--init-analyze-file] can be used as a relative path alongside files
    that Phan infers from composer.json's "bin" settings
  [--init-no-composer] can be used to tell Phan that the project
    is not a composer project.
    Phan will not check for composer.json or vendor/,
    and will not include those paths in the generated config.
  [--init-overwrite] will allow 'phan --init' to overwrite .phan/config.php.

 -C, --color
  Add colors to the outputted issues. Tested in Unix.
  This is recommended for only the default --output-mode ('text')

 -p, --progress-bar
  Show progress bar

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 --target-php-version {7.0,7.1,7.2,7.3,native}
  The PHP version that the codebase will be checked for compatibility against.
  For best results, the PHP binary used to run Phan should have the same PHP version.
  (Phan relies on Reflection for some param counts
   and checks for undefined classes/methods/functions)

 -i, --ignore-undeclared
  Ignore undeclared functions and classes

 -y, --minimum-severity <level in {0,5,10}>
  Minimum severity level (low=0, normal=5, critical=10) to report.
  Defaults to 0.

 -c, --parent-constructor-required
  Comma-separated list of classes that require
  parent::__construct() to be called

 -x, --dead-code-detection
  Emit issues for classes, methods, functions, constants and
  properties that are probably never referenced and can
  be removed. This implies `--unused-variable-detection`.

 --unused-variable-detection
  Emit issues for variables, parameters and closure use variables
  that are probably never referenced.
  This has a few known false positives, e.g. for loops or branches.

 -j, --processes <int>
  The number of parallel processes to run during the analysis
  phase. Defaults to 1.

 -z, --signature-compatibility
  Analyze signatures for methods that are overrides to ensure
  compatibility with what they're overriding.

 --disable-plugins
  Don't run any plugins. Slightly faster.

 -P, --plugin <pluginName|path/to/Plugin.php>
  Add a plugin to run. This flag can be repeated.
  (Either pass the name of the plugin or a relative/absolute path to the plugin)

 --strict-method-checking
  Warn if any type in a method invocation's object is definitely not an object,
  or any type in an invoked expression is not a callable.
  (Enables the config option `strict_method_checking`)

 --strict-param-checking
  Warn if any type in an argument's union type cannot be cast to
  the parameter's expected union type.
  (Enables the config option `strict_param_checking`)

 --strict-property-checking
  Warn if any type in a property assignment's union type
  cannot be cast to a type in the property's declared union type.
  (Enables the config option `strict_property_checking`)

 --strict-return-checking
  Warn if any type in a returned value's union type
  cannot be cast to the declared return type.
  (Enables the config option `strict_return_checking`)

 -S, --strict-type-checking
  Equivalent to
  `--strict-method-checking --strict-param-checking --strict-property-checking --strict-return-checking`.

 --use-fallback-parser
  If a file to be analyzed is syntactically invalid
  (i.e. "php --syntax-check path/to/file" would emit a syntax error),
  then retry, using a different, slower error tolerant parser to parse it.
  (And phan will then analyze what could be parsed).
  This flag is experimental and may result in unexpected exceptions or errors.
  This flag does not affect excluded files and directories.

 --allow-polyfill-parser
  If the `php-ast` extension isn't available or is an outdated version,
  then use a slower parser (based on tolerant-php-parser) instead.
  Note that https://github.com/Microsoft/tolerant-php-parser
  has some known bugs which may result in false positive parse errors.

 --force-polyfill-parser
  Use a slower parser (based on tolerant-php-parser) instead of the native parser,
  even if the native parser is available.
  Useful mainly for debugging.

 -s, --daemonize-socket </path/to/file.sock>
  Unix socket for Phan to listen for requests on, in daemon mode.

 --daemonize-tcp-host
  TCP hostname for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for host 127.0.0.1, or `0.0.0.0` for
  usage with Docker). `phan_client` can be used to communicate with the Phan Daemon.

 --daemonize-tcp-port <default|1024-65535>
  TCP port for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for port 4846.)
  `phan_client` can be used to communicate with the Phan Daemon.

 -v, --version
  Print Phan's version number

 -h, --help
  This help information

 --extended-help
  This help information, plus less commonly used flags
  (E.g. for daemon mode)
```

## Annotating Your Source Code

Phan reads and understands most [PHPDoc](http://www.phpdoc.org/docs/latest/guides/types.html)
type annotations including [Union Types](https://github.com/phan/phan/wiki/About-Union-Types)
(like `int|MyClass|string|null`) and generic array types (like `int[]` or `string[]|MyClass[]` or `array<int,MyClass>`).

Take a look at [Annotating Your Source Code](https://github.com/phan/phan/wiki/Annotating-Your-Source-Code)
and [About Union Types](https://github.com/phan/phan/wiki/About-Union-Types) for some help
getting started with defining types in your code.

Phan supports `(int|string)[]` style annotations, and represents them internally as `int[]|string[]`
(Both annotations are treated like array which may have integers and/or strings).
When you have arrays of mixed types, just use `array`.

The following code shows off the various annotations that are supported.

```php
/**
 * @return void
 */
function f() {}

/** @deprecated */
class C {
    /** @var int */
    const C = 42;

    /** @var string[]|null */
    public $p = null;

    /**
     * @param int|null $p
     * @return string[]|null
     */
    public static function f($p) {
        if (is_null($p)) {
            return null;
        }

        return array_map(
            /** @param int $i */
            function($i) {
                return "thing $i";
            },
            range(0, $p)
        );
    }
}
```

Just like in PHP, any type can be nulled in the function declaration which also
means a null is allowed to be passed in for that parameter.

Phan checks the type of every single element of arrays (Including keys and values).
In practical terms, this means that `[$int1=>$int2,$int3=>$int4,$int5=>$str6]` is seen as `array<int,int|string>`,
which Phan represents as `array<int,int>|array<int,string>`.
`[$strKey => new MyClass(), $strKey2 => $unknown]` will be represented as
`array<string,MyClass>|array<string,mixed>`.

- Literals such as `[12,'myString']` will be represented internally as array shapes such as `array{0:12,1:'myString'}`

# Generating a file list

This static analyzer does not track includes or try to figure out autoloader magic. It treats
all the files you throw at it as one big application. For code encapsulated in classes this
works well. For code running in the global scope it gets a bit tricky because order
matters. If you have an `index.php` including a file that sets a bunch of global variables and
you then try to access those after the `include(...)` in `index.php` the static analyzer won't
know anything about these.

In practical terms this simply means that you should put your entry points and any files
setting things in the global scope at the top of your file list. If you have a `config.php`
that sets global variables that everything else needs, then you should put that first in the list followed by your
various entry points, then all your library files containing your classes.

# Development

Take a look at [Developer's Guide to Phan](https://github.com/phan/phan/wiki/Developer's-Guide-To-Phan) for help getting started hacking on Phan.

When you find an issue, please take the time to create a tiny reproducing code snippet that illustrates
the bug. And once you have done that, fix it. Then turn your code snippet into a test and add it to
[tests](tests) then `./test` and send a PR with your fix and test. Alternatively, you can open an Issue with
details.

To run Phan's unit tests, just run `./test`.

To run all of Phan's unit tests and integration tests, run `./tests/run_all_tests.sh`

# Code of Conduct

We are committed to fostering a welcoming community. Any participant and
contributor is required to adhere to our [Code of Conduct](./CODE_OF_CONDUCT.md).
