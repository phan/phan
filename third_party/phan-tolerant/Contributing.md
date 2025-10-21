# Contributing
We would love to have you as a contributor! There are many ways to contribute to the project, including: 
reporting issues, requesting features, submitting suggestions, and making pull requests.

## Reporting Issues
Please include enough information in each issue to reproduce the bug. Extra points if you submit a failing test case to 
the `tests/cases` folder, and also include the test in `tests/skipped.json` - this will not run as part of the testsuite by
default, but make it very easy for others to contribute a fix (or read on if you want to do it yourself :wink:). 

## Building and Running 
1. Fork and clone the repository.
2. `composer install`
3. `vendor\bin\phpunit` from the root project directory to run the tests to run all the test suites defined in `phpunit.xml`. 
To run individual suites, run `vendor\bin\phpunit --testsuite <test suite name>`.
Note that the validation test suite requires you to also include relevant submodules: `git submodule update --init --recursive`

> :bulb: looking for your first PR? Take a look through the issue tracker, search the codebase for TODOs, or try enabling
one of the test cases in `skipped.json`. 

In addition to running the test suites from the command line, you have the option of running them directly from VS Code using
the included launch.json configuration.

![image](https://cloud.githubusercontent.com/assets/762848/22679079/471935c2-ecb4-11e6-831f-a7b2cfcf3dcf.png)

## Debugging Failed Tests
### Analyzing failed test output files 
The "grammar" and "validation" test suites both output files for failed tests to make it easier to debug,
and examining these files is the easiest way to debug those tests (see notes in `phpunit.xml` for more details). 

For instance, if you want to analyze the failures in the `drupal` validation tests, the easiest way to do so is
to open the `tests/output/drupal` folder using the [syntax visualizer extension](syntax-visualizer/client#php-parser-syntax-visualizer-tool) 
after running the validation tests - you'll see error squigglies wherever we fail to parse correctly 
(presuming the code is indeed valid) and can inspect the adjacent `.ast` file for more info.

![image](https://cloud.githubusercontent.com/assets/762848/22134701/f0a00ff2-de7d-11e6-908c-508d82f0841c.png)

### Using the debugger
> Note: Enabling Xdebug has a severe performance impact, and we recommend disabling it whenever you are not attaching the debugger.

For debugging, we recommend you install Felix Becker's [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=felixfbecker.php-debug). 

To debug individual test suites, simply set `php.xdebug.extension` to the Xdebug extension path in `.vscode/settings.json`:
![image](https://cloud.githubusercontent.com/assets/762848/22679143/b9014314-ecb4-11e6-9295-1ecdbdcfde33.png)

Alternatively, you can run the `Listen for XDebug` launch configuration included in this project, and then from the command line:
```
php -debug -d zend_extension=<my-path-to-extension> -d xdebug.remote_enable=1 -d xdebug.remote_autostart=1 vendor\phpunit\phpunit\phpunit <my-phpunit-arguments>
```

## Running code coverage
After enabling `xdebug`, run code coverage by executing:
```
php -d memory_limit=500M vendor/bin/phpunit --coverage-html tmp/ tests/
```

## Running performance tests
```
php -d memory_limit=500M validation/ParserPerformance.php
```

Note that there will be a lot of variance between runs. In the future, we plan to set
up an environment that can provide assurance of statistical significance.

## Example PRs
> :bulb: Note - These examples aren't perfect... they were from early "exploratory" stages of the project,
and the codebase has changed a bit since then. If you spot a particularly educational PR, feel free
to suggest it for the list!

### Adding a Feature
[This commit](https://github.com/Microsoft/tolerant-php-parser/commit/8d019cb731d6e5492eedf044c895124f5ab28089) adds return-statement support. There are a few things
to note.

1. Naming consistency with the PHP language spec
2. Formatting consistency with rest of codebase.
3. TODO comment for any missing functionality (now that we're on GitHub, a reference to a filed issue would also be nice)
4. Test cases that provide an input `.php` file and output `.tree` file (any cases in `tests/cases`)

### Fixing an issue
[This commit](https://github.com/Microsoft/tolerant-php-parser/commit/f1084a46e6be1e77cf6a1d1e6666a7390b359f4a) fixes a simple issue related to if-statement parsing. Note that
there are more test case changes than lines of code.

### Adding a Test Case
The more tests the better, so we also accept test contributions without any functional code changes.
* [Adding tests](https://github.com/Microsoft/tolerant-php-parser/commit/2ad62b99015561103b636d9cc8e0463498535b20) - Parser test cases provide an input `.php` file and an output `.tree`
file (Lexical test cases provide an output `.tokens` file). The output `.tree` file need not be generated by hand, and will be
overwritten if the test is failing. To generate the `tree` file, run the "grammar" test suite. If you've made edits to the file
that you'd like to keep, make sure you stage your changes in git so that they are not reverted. 
* [Skipping a failing test](https://github.com/Microsoft/tolerant-php-parser/commit/04c1cf9f0be20d115dc2f8c26019de4ea5bf4fc5) - we like to keep all of our tests green to
to make it easier to detect regressions, so this list keeps track of everything we want to come back to. 
