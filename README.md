Phan is a static analyzer for PHP.

# Getting it running

Phan requires PHP 7+ with the [php-ast][phpast] extension loaded. The code you
analyze can be written for any version of PHP.

To get phan running;

1. Clone the repo
2. Run `composer install` to load dependencies
3. Run `./test` to run the test suite
4. Test phan on itself by running the following

```
./phan `find src/ -type f -path '*.php'`
```

If you don't have a version of PHP 7 installed, you can grab a [php7dev][php7dev] Vagrant image or one of the many Docker builds out there.

Then compile [php-ast][phpast]. Something along these lines should do it:

```sh
git clone https://github.com/nikic/php-ast.git
cd php-ast
phpize
./configure
make install
```

And add `extension=ast.so` to your `php.ini` file. Check that it is there with `php -m`.
If it isn't you probably added it to the wrong `php.ini` file. Check `php --ini` to see
where it is looking.


## Features

* Checks for calls and instantiations of undeclared functions, methods, closures and classes
* Checks types of all arguments and return values to/from functions, closures and methods
* Supports `@param`, `@return`, `@var` and `@deprecated` [phpdoc][doctypes] comments including union and void/null types
* Checks for [Uniform Variable Syntax][uniform] PHP 5 -> PHP 7 BC breaks
* Undefined variable tracking
* Supports namespaces, traits and variadics
* Generics (from phpdoc hints - int[], string[], UserObject[], etc.)

See the [tests][tests] directory for some examples of the various checks.


## Usage

```sh
phan *.php
```

or give it a text file containing a list of files (but see the next section) to scan:

```sh
phan -f filelist.txt
```

and it might generate output that looks like this:

```php
test1.php:191 UndefError call to undefined function get_real_size()
test1.php:232 UndefError static call to undeclared class core\session\manager
test1.php:386 UndefError Trying to instantiate undeclared class lang_installer
test2.php:4 TypeError arg#1(arg) is object but escapeshellarg() takes string
test2.php:4 TypeError arg#1(msg) is int but logmsg() takes string defined at sth.php:5
test2.php:4 TypeError arg#2(level) is string but logmsg() takes int defined at sth.php:5
test3.php:11 TypeError arg#1(number) is string but number_format() takes float
test3.php:12 TypeError arg#1(string) is int but htmlspecialchars() takes string
test3.php:13 TypeError arg#1(str) is int but md5() takes string
test3.php:14 TypeError arg#1(separator) is int but explode() takes string
test3.php:14 TypeError arg#2(str) is int but explode() takes string
```

You can see the full list of command line options by running `phan -h`.


## Generating a file list

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


## Bugs

When you find an issue, please take the time to create a tiny reproducing code snippet that illustrates
the bug. And once you have done that, fix it. Then turn your code snippet into a test and add it to
[tests][tests] then `./test` and send a PR with your fix and test. Alternatively, you can open an Issue with
details.

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

## Dealing with dynamic code that confuses the analyzer

There are times when there is just no way for the analyzer to get things right.
For example:

```php
<?php
function test() {
	$var = 0;
	$var = call_some_func_you_cant_hint();
	if(is_string($var)) {
		$pos = strpos($var, '|');
	}
}
```

Your best option is, of course, to go and add a `/** @return string|array */` comment to
the `call_some_func_you_cant_hint()` function, but there are times when that is not an
option. As far as the analyzer is concerned, `$var` is an int because all it sees is the
`$var = 0;` assignment. It will complain about you passing an int to `strpos()`. You can
help it out by adding a `@var` doc-type comment before the function:

```php
<?php
/**
 * @var string|array $var
 */
function test() {
	...
```

This tells the analyzer that along with the `int` that it figures out on its own, `$var` can
also be a string or an array inside that function. This is a departure from the normal use of the
`@var` tag which is to give properties types, so I don't suggest making a habit of using this hack.
But it can be handy to shut up the analyzer without having to refactor the code to not overload the
same variable with many different types.


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

## Quick Mode Explained

In Quick-mode the scanner doesn't rescan a function or a method's code block every time
a call is seen. This means that the problem here won't be detected:

```php
<?php
function test($arg):int {
	return $arg;
}
test("abc")
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
  [tests]: https://github.com/rlerdorf/phan/blob/master/tests
  [php7ast]: https://wiki.php.net/rfc/abstract_syntax_tree
  [php7dev]: https://github.com/rlerdorf/php7dev
  [uniform]: https://wiki.php.net/rfc/uniform_variable_syntax

# Running tests

```sh
vendor/bin/phpunit
```