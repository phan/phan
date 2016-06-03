Phan is a static analyzer for PHP.

[![Code Climate](https://codeclimate.com/github/etsy/phan/badges/gpa.svg)](https://codeclimate.com/github/etsy/phan) [![Build Status](https://travis-ci.org/etsy/phan.svg?branch=master)](https://travis-ci.org/etsy/phan) [![Gitter](https://badges.gitter.im/etsy/phan.svg)](https://gitter.im/etsy/phan?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

# Features

* Checks for all methods, functions, classes, traits, interfaces, constants, properties and variables to be defined and accessible.
* Checks for type safety and arity issues on method/function/closure calls.
* Checks for PHP7/PHP5 backward compatibility
* Checks for sanity with array accesses
* Checks for type safety on binary operations
* Checks for valid and type safe return values on methods, functions, and closures
* Checks for No-Ops on arrays, closures, constants, properties, variables.
* Checks for unused/dead code.
* Checks for classes, functions and methods being redefined
* Supports namespaces, traits and variadics
* Supports [Union Types](https://github.com/etsy/phan/wiki/About-Union-Types)
* Supports generic arrays such as `int[]`, `UserObject[]`, etc..
* Supports phpdoc [type annotations](https://github.com/etsy/phan/wiki/Annotating-Your-Source-Code)
* Supports `@deprecated` annotation for deprecating classes, methods and functions
* Supports `@suppress <ISSUE_TYPE>` annotations for [suppressing issues](https://github.com/etsy/phan/wiki/Annotating-Your-Source-Code#suppress).
* Offers extensive configuration for weakening the analysis to make it useful on large sloppy code bases
* Can be run on many cores.
* Output is emitted in text, checkstyle, json or codeclimate formats.

See [Phan Error Types](https://github.com/etsy/phan/wiki/Issue-Types-Caught-by-Phan) for descriptions
and examples of all issues that can be detected by Phan. Take a look at the
[\Phan\Issue](https://github.com/etsy/phan/blob/master/src/Phan/Issue.php) to see the
definition of each error type.

Take a look at the [Tutorial for Analyzing a Large Sloppy Code Base](https://github.com/etsy/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base) to get a sense of what the process of doing ongoing analysis might look like for you.

See the [tests][tests] directory for some examples of the various checks.

# Getting Phan Running

Take a look at [Getting Started](https://github.com/etsy/phan/wiki/Getting-Started) for various methods of getting Phan running on your system.

Phan depends on PHP 7+ and the [php-ast](https://github.com/nikic/php-ast) extension. With those dependencies installed, you can get Phan running via

* [Composer](https://github.com/etsy/phan/wiki/Getting-Started#composer)
* [Source](https://github.com/etsy/phan/wiki/Getting-Started#from-source)
* [Phan.phar](https://github.com/etsy/phan/wiki/Getting-Started#from-phanphar)
* [Docker Image](https://github.com/etsy/phan/wiki/Getting-Started#from-a-docker-image)
* [Code Climate](https://github.com/etsy/phan/wiki/Getting-Started#from-code-climate)
* [Homebrew](https://github.com/etsy/phan/wiki/Getting-Started#from-homebrew)

Once installed, you can make sure Phan is running correctly by running `phan -h` to see its command-line options.

With Phan running, you'll want to [create a `.phan/config.php` file](https://github.com/etsy/phan/wiki/Getting-Started#creating-a-config-file) in
your project to tell Phan how to analyze your source code. Take a look at [Incrementally Strengthening Analysis](https://github.com/etsy/phan/wiki/Incrementally-Strengthening-Analysis)
for some tips on how to slowly ramp up the stricntess of the analysis as your code becomes better equipped to be analyzed.

Take a look at [Installing Phan Dependencies](https://github.com/etsy/phan/wiki/Getting-Started#installing-phan-dependencies) for help
getting Phan's dependencies installed on your system.

# Usage

Running `phan --help` will show usage information for the CLI tool.

```
Usage: ./phan [options] [files...]
 -f, --file-list <filename>
  A file containing a list of PHP files to be analyzed

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is useful when running Phan from a stored state file and
  passing in a small subset of files to be re-analyzed.

 -l, --directory <directory>
  A directory to recursively read PHP files from to analyze

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories for which any files
  therein should be parsed but not analyzed.

 -s, --state-file <filename>
  Save state to the given file and read from it to speed up
  future executions

 -d, --project-root-directory
  Hunt for a directory named .phan in the current or parent
  directory and read configuration file config.php from that
  path.

 -m <mode>, --output-mode
  Output mode: text, codeclimate

 -o, --output <filename>
  Output filename

 -p, --progress-bar
  Show progress bar

 -a, --dump-ast
  Emit an AST for each file rather than analyze

 -e, --expand-file-list
  Expand the list of files passed in to include any files
  that depend on elements defined in those files. This is
  useful when running Phan from a state file and passing in
  just the set of changed files.

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

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
  possibly be removed.

 -h,--help
  This help information
```

A thorough analysis might be run via something like

```bash
phan --minimum-severity=0 \
     --backward-compatibility-checks \
     `find src -type f -path '*.php'`
```

while a casual analysis just looking for the worst offenders might look like

```bash
phan --minimum-severity=10 \
     --quick \
     --ignore-undeclared \
     `find src -type f -path '*.php'`
```


# Generating a file list

This static analyzer does not track includes or try to figure out autoloader magic. It treats
all the files you throw at it as one big application. For code encapsulated in classes this
works well. For code running in the global scope it gets a bit tricky because order
matters. If you have an `index.php` including a file that sets a bunch of global variables and
you then try to access those after the include in `index.php` the static analyzer won't
know anything about these.

In practical terms this simply means that you should put your entry points and any files
setting things in the global scope at the top of your file list. If you have a `config.php`
that sets global variables that everything else needs put that first in the list followed by your
various entry points, then all your library files containing your classes.

## More on phpdoc types

All the [phpdoc][doctypes] types listed on that page should work with one exception.
It says that `(int|string)[]` would indicate an array of ints or strings. phan doesn't support
a mixed-type constraint like that. You can say `int[]|string[]` meaning that the array has to
contain either all ints or all strings, but if you have mixed types, just use `array`.

That means you can do:

```php
<?php
/**
 * MyFunc
 * @param int                 $arg1
 * @param int|string          $arg2
 * @param int[]|int           $arg3
 * @param Datetime|Datetime[] $arg4
 * @return array|null
 */
function MyFunc($arg1, $arg2, $arg3, $arg4=null) {
	return null;
}
```
Just like in PHP, any type can be nulled in the function declaration which also
means a null is allowed to be passed in for that parameter.

By default, and completely arbitrarily, for things like `int[]` it checks the first 5
elements. If the first 5 are of the same type, it assumes the rest are as well. If it can't
determine the array sub-type it just becomes `array` which will pass through most type
checks. In practical terms, this means that `[1,2,'a']` is seen as `array` but `[1,2,3]`
is `int[]` and `['a','b','c']` as `string[]`.

## Quick Mode Explained

In Quick-mode the scanner doesn't rescan a function or a method's code block every time
a call is seen. This means that the problem here won't be detected:

```php
<?php
function test($arg):int {
	return $arg;
}
test("abc");
```

This would normally generate:

```sh
test.php:3 TypeError return string but `test()` is declared to return int
```

The initial scan of the function's code block has no type information for `$arg`. It
isn't until we see the call and rescan test()'s code block that we can detect
that it is actually returning the passed in `string` instead of an `int` as declared.

  [phpast]: https://github.com/nikic/php-ast
  [scrutinizer]: https://scrutinizer-ci.com/docs/guides/php/automated-code-reviews
  [doctypes]: http://www.phpdoc.org/docs/latest/guides/types.html
  [tests]: https://github.com/etsy/phan/blob/master/tests/files
  [php7ast]: https://wiki.php.net/rfc/abstract_syntax_tree
  [php7dev]: https://github.com/rlerdorf/php7dev
  [uniform]: https://wiki.php.net/rfc/uniform_variable_syntax

# Development

Take a look at [Developer's Guide to Phan](https://github.com/etsy/phan/wiki/Developer's-Guide-To-Phan) for help getting started hacking on Phan.

## Bugs

When you find an issue, please take the time to create a tiny reproducing code snippet that illustrates
the bug. And once you have done that, fix it. Then turn your code snippet into a test and add it to
[tests][tests] then `./test` and send a PR with your fix and test. Alternatively, you can open an Issue with
details.

## How it works

One of the big changes in PHP 7 is the fact that the parser now uses a real
Abstract Syntax Tree ([AST][php7ast]). This makes it much easier to write code
analysis tools by pulling the tree and walking it looking for interesting things.

Phan has 2 passes. On the first pass it reads every file, gets the AST and recursively parses it
looking only for functions, methods and classes in order to populate a bunch of
global hashes which will hold all of them. It also loads up definitions for all internal
functions and classes. The type info for these come from a big file called FunctionSignatureMap.

The real complexity hits you hard in the second pass. Here some things are done recursively depth-first
and others not. For example, we catch something like `foreach($arr as $k=>$v)` because we need to tell the
foreach code block that `$k` and `$v` exist. For other things we need to recurse as deeply as possible
into the tree before unrolling our way back out. For example, for something like `c(b(a(1)))` we need
to call `a(1)` and check that `a()` actually takes an int, then get the return type and pass it to `b()`
and check that, before doing the same to `c()`.

There is a Scope object which keeps track of all variables. It mimics PHP's scope handling in that it
has a globals along with entries for each function, method and closure. This is used to detect
undefined variables and also type-checked on a `return $var`.

## Running tests

```sh
composer install
./vendor/bin/phpunit
```
