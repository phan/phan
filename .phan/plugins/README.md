Plugins
=======

The plugins in this folder can be used to add additional capabilities to phan.
Add their relative path (.phan/plugins/...) to the `plugins` entry of .phan/config.php.

Plugin Documentation
--------------------

[Wiki Article: Writing Plugins For Phan](https://github.com/phan/phan/wiki/Writing-Plugins-for-Phan)

Plugin List
-----------

This section contains short descriptions of plugin files, and lists the issue types which they emit.

They are grouped into the following sections:

1. Plugins Affecting Phan Analysis
2. General-Use Plugins
3. Plugins Specific to Code Styles
4. Demo Plugins (Plugin authors should base new plugins off of these, if they don't see a similar plugin)

### 1. Plugins Affecting Phan Analysis

(More plugins will be added later, e.g. if they add new methods, add types to Phan's analysis of a return type, etc)

#### UnusedSuppressionPlugin.php

Warns if an `@suppress` annotation is no longer needed to suppress issue types on a function, method, closure, or class.
(Suppressions may stop being needed if Phan's analysis improves/changes in a release,
or if the relevant parts of the codebase fixed the bug/added annotations)
**This must be run with exactly one worker process**

- **UnusedSuppression**: `Element {FUNCTIONLIKE} suppresses issue {ISSUETYPE} but does not use it`
- **UnusedPluginSuppression**: `Plugin {STRING_LITERAL} suppresses issue {ISSUETYPE} on this line but this suppression is unused or suppressed elsewhere`
- **UnusedPluginFileSuppression**: `Plugin {STRING_LITERAL} suppresses issue {ISSUETYPE} in this file but this suppression is unused or suppressed elsewhere`

### 2. General-Use Plugins

These plugins are useful across a wide variety of code styles, and should give low false positives.
Also see [DollarDollarPlugin.php](#dollardollarpluginphp) for a meaningful real-world example.

#### AlwaysReturnPlugin.php

Checks if a function or method with a non-void return type will **unconditionally** return or throw.
This is stricter than Phan's default checks (Phan accepts a function or method that **may** return something, or functions that unconditionally throw).

#### DuplicateArrayKeyPlugin.php

Warns about common errors in php array keys and switch statements. Has the following checks (This is able to resolve global and class constants to their scalar values).

- **PhanPluginDuplicateArrayKey**: a duplicate or equivalent array key literal.

  (E.g `[2 => "value", "other" => "s", "2" => "value2"]` duplicates the key `2`)
- **PhanPluginDuplicateSwitchCase**: a duplicate or equivalent case statement.

  (E.g `switch ($x) { case 2: echo "A\n"; break; case 2: echo "B\n"; break;}` duplicates the key `2`. The later case statements are ignored.)
- **PhanPluginMixedKeyNoKey**: mixing array entries of the form [key => value,] with entries of the form [value,].

  (E.g. `['key' => 'value', 'othervalue']` is often found in code because the key for `'othervalue'` was forgotten)

#### PregRegexCheckerPlugin

This plugin checks for invalid regexes.
This plugin is able to resolve literals, global constants, and class constants as regexes.

- **PhanPluginInvalidPregRegex**: The provided regex is invalid, according to PHP.
- **PhanPluginInvalidPregRegexReplacement**: The replacement string template of `preg_replace` refers to a match group that doesn't exist. (e.g. `preg_replace('/x(a)/', 'y$2', $strVal)`)

#### PrintfCheckerPlugin

Checks for invalid format strings, incorrect argument counts, and unused arguments in printf calls.
Additionally, warns about incompatible union types (E.g. passing `string` for the argument corresponding to `%d`)
This plugin is able to resolve literals, global constants, and class constants as format strings.


- **PhanPluginPrintfNonexistentArgument**: `Format string {STRING_LITERAL} refers to nonexistent argument #{INDEX} in {STRING_LITERAL}`
- **PhanPluginPrintfNoArguments**: `No format string arguments are given for {STRING_LITERAL}, consider using {FUNCTION} instead`
- **PhanPluginPrintfNoSpecifiers**: `None of the formatting arguments passed alongside format string {STRING_LITERAL} are used`
- **PhanPluginPrintfUnusedArgument**: `Format string {STRING_LITERAL} does not use provided argument #{INDEX}`
- **PhanPluginPrintfNotPercent**: `Format string {STRING_LITERAL} contains something that is not a percent sign, it will be treated as a format string '{STRING_LITERAL}' with padding. Use %% for a literal percent sign, or '{STRING_LITERAL}' to be less ambiguous`
  (Usually a typo, e.g. `printf("%s is 20% done", $taskName)` treats `% d` as a second argument)
- **PhanPluginPrintfWidthNotPosition**: `Format string {STRING_LITERAL} is specifying a width({STRING_LITERAL}) instead of a position({STRING_LITERAL})`
- **PhanPluginPrintfIncompatibleSpecifier**: `Format string {STRING_LITERAL} refers to argument #{INDEX} in different ways: {DETAILS}` (e.g. `"%1$s of #%1$d"`. May be an off by one error.)
- **PhanPluginPrintfIncompatibleArgumentTypeWeak**: `Format string {STRING_LITERAL} refers to argument #{INDEX} as {DETAILS}, so type {TYPE} is expected. However, {FUNCTION} was passed the type {TYPE} (which is weaker than {TYPE})`
- **PhanPluginPrintfIncompatibleArgumentType**: `PhanPluginPrintfIncompatibleArgumentType`

Note (for projects using `gettext`):
Subclassing this plugin (and overriding `gettextForAllLocales`) will allow you to analyze translations of a project for compatibility.
This will require extra work to set up.
See [PrintfCheckerPlugin's source](./PrintfCheckerPlugin.php) for details.

#### UnreachableCodePlugin.php

Checks for syntactically unreachable statements in the global scope or function bodies.
(E.g. function calls after unconditional `continue`/`break`/`throw`/`return`/`exit()` statements)

- **PhanPluginUnreachableCode**: `Unreachable statement detected`

#### Unused variable detection

This is now built into Phan itself, and can be enabled via `--unused-variable-detection`.

#### InvokePHPNativeSyntaxCheckPlugin.php

This invokes `php --no-php-ini --syntax-check $analyzed_file_path` for you. (See
This is useful for cases Phan doesn't cover (e.g. [Issue #449](https://github.com/phan/phan/issues/449) or [Issue #277](https://github.com/phan/phan/issues/277)).

Note: This may double the time Phan takes to analyze a project. This plugin can be safely used along with `--processes N`.

This does not run on files that are parsed but not analyzed.

Configuration settings can be added to `.phan/config.php`:

```php
    'plugin_config' => [
        // A list of 1 or more PHP binaries (Absolute path or program name found in $PATH)
        // to use to analyze your files with PHP's native `--syntax-check`.
        //
        // This can be used to simultaneously run PHP's syntax checks with multiple PHP versions.
        // e.g. `'plugin_config' => ['php_native_syntax_check_binaries' => ['php72', 'php70', 'php56']]`
        // if all of those programs can be found in $PATH

        // 'php_native_syntax_check_binaries' => [PHP_BINARY],

        // The maximum number of `php --syntax-check` processes to run at any point in time
        // (Minimum: 1. Default: 1).
        // This may be temporarily higher if php_native_syntax_check_binaries
        // has more elements than this process count.
        'php_native_syntax_check_max_processes' => 4,
    ],
```

If you wish to make sure that analyzed files would be accepted by those PHP versions
(Requires that php72, php70, and php56 be locatable with the `$PATH` environment variable)

### 3. Plugins Specific to Code Styles

These plugins may be useful to enforce certain code styles,
but may cause false positives in large projects with different code styles.

#### NonBool

##### NonBoolBranchPlugin.php

- **PhanPluginNonBoolBranch** Warns if a expression which has types other than `bool` is used in an if/else if.

  (E.g. warns about `if ($x)`, where $x is an integer. Fix by checking `if ($x != 0)`, etc.)

##### NonBoolInLogicalArithPlugin.php

- **PhanPluginNonBoolInLogicalArith** Warns if a expression where the left/right hand side has types other than `bool` is used in a binary operation.

  (E.g. warns about `if ($x && $x->fn())`, where $x is an object. Fix by checking `if (($x instanceof MyClass) && $x->fn())`)

#### HasPHPDocPlugin.php

Checks if an element (class or property) has a PHPDoc comment,
and that Phan can extract a plaintext summary/description from that comment.

- **PhanPluginNoCommentOnClass**: `Class {CLASS} has no doc comment`
- **PhanPluginDescriptionlessCommentOnClass**: `Class {CLASS} has no readable description: {STRING_LITERAL}`
- **PhanPluginNoCommentOnFunction**: `Function {FUNCTION} has no doc comment`
- **PhanPluginDescriptionlessCommentOnFunction**: `Function {FUNCTION} has no readable description: {STRING_LITERAL}`
- **PhanPluginNoCommentOnPublicProperty**: `Public property {PROPERTY} has no doc comment` (Also exists for Private and Protected)
- **PhanPluginDescriptionlessCommentOnPublicProperty**: `Public property {PROPERTY} has no readable description: {STRING_LITERAL}` (Also exists for Private and Protected)

Warnings about method verbosity also exist, many categories may need to be completely disabled due to the large number of method declarations in a typical codebase:

- Warnings are not emitted for `@internal` methods.
- Warnings are not emitted for methods that override methods in the parent class.
- Warnings can be suppressed based on the method FQSEN with `plugin_config => [..., 'has_phpdoc_method_ignore_regex' => (a PCRE regex)]`

  (e.g. to suppress issues about tests, or about missing documentation about getters and setters, etc.)

The warning types for methods are below:

- **PhanPluginNoCommentOnPublicMethod**: `Public method {METHOD} has no doc comment` (Also exists for Private and Protected)
- **PhanPluginDescriptionlessCommentOnPublicMethod**: `Public method {METHOD} has no readable description: {STRING_LITERAL}` (Also exists for Private and Protected)

#### InvalidVariableIssetPlugin.php

Warns about invalid uses of `isset`. This README documentation may be inaccurate for this plugin.

- **PhanPluginInvalidVariableIsset** : Forces all uses of `isset` to be on arrays or variables.

  E.g. it will warn about `isset(foo()['key'])`, because foo() is not a variable or an array access.
- **PhanUndeclaredVariable**: Warns if `$array` is undeclared in `isset($array[$key])`

#### NoAssertPlugin.php

Discourages the usage of assert() in the analyzed project.
See https://secure.php.net/assert

- **PhanPluginNoAssert**: `assert() is discouraged. Although phan supports using assert() for type annotations, PHP's documentation recommends assertions only for debugging, and assert() has surprising behaviors.`

#### NumericalComparisonPlugin.php

Enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`), and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).

- **PhanPluginNumericalComparison**: non numerical values compared by the operators '==' or '!=='; numerical values compared by the operators '===' or '!=='

#### PHPUnitNotDeadCodePlugin

Marks unit tests and dataProviders of subclasses of PHPUnit\Framework\TestCase as referenced.
Avoids false positives when `--dead-code-detection` is enabled.

(Does not emit any issue types)

#### SleepCheckerPlugin.php

Warn about returning non-arrays in [`__sleep`](https://secure.php.net/__sleep),
as well as about returning array values with invalid property names in `__sleep`.

- **SleepCheckerInvalidReturnStatement`**: `__sleep must return an array of strings. This is definitely not an array.`
- **SleepCheckerInvalidReturnType**: `__sleep is returning {TYPE}, expected string[]`
- **SleepCheckerInvalidPropNameType**: `__sleep is returning an array with a value of type {TYPE}, expected string`
- **SleepCheckerInvalidPropName**: `__sleep is returning an array that includes {PROPERTY}, which cannot be found`
- **SleepCheckerMagicPropName**: `__sleep is returning an array that includes {PROPERTY}, which is a magic property`
- **SleepCheckerDynamicPropName**: `__sleep is returning an array that includes {PROPERTY}, which is a dynamically added property (but not a declared property)`
- **SleepCheckerPropertyMissingTransient**: `Property {PROPERTY} that is not serialized by __sleep should be annotated with @transient or @phan-transient`,

#### UnknownElementTypePlugin.php

Warns about elements containing unknown types (function/method/closure return types, parameter types)

- **PhanPluginUnknownMethodReturnType**: `Method {METHOD} has no declared or inferred return type`
- **PhanPluginUnknownMethodParamType**: `Method {METHOD} has no declared or inferred parameter type for ${PARAMETER}`
- **PhanPluginUnknownFunctionReturnType**: `Function {FUNCTION} has no declared or inferred return type`
- **PhanPluginUnknownFunctionParamType**: `Function {FUNCTION} has no declared or inferred return type for ${PARAMETER}`
- **PhanPluginUnknownClosureReturnType**: `Closure {FUNCTION} has no declared or inferred return type`
- **PhanPluginUnknownClosureParamType**: `Closure {FUNCTION} has no declared or inferred return type for ${PARAMETER}`
- **PhanPluginUnknownPropertyType**: `Property {PROPERTY} has an initial type that cannot be inferred`

#### DuplicateExpressionPlugin.php

This plugin checks for duplicate expressions in a statement
that are likely to be a bug. (e.g. `expr1 == expr`)

- **PhanPluginDuplicateExpressionBinaryOp**: `Both sides of the binary operator {OPERATOR} are the same: {CODE}`
- **PhanPluginDuplicateConditionalTernaryDuplication**: `"X ? X : Y" can usually be simplified to "X ?: Y". The duplicated expression X was {CODE}`
- **PhanPluginDuplicateConditionalNullCoalescing**: `"isset(X) ? X : Y" can usually be simplified to "X ?? Y" in PHP 7. The duplicated expression X was {CODE}`

### 4. Demo plugins:

These files demonstrate plugins for Phan.

#### DemoPlugin.php

Look at this class's documentation if you want an example to base your plugin off of.
Generates the following issue types under the types:

- **DemoPluginClassName**: a declared class isn't called 'Class'
- **DemoPluginFunctionName**: a declared function isn't called `function`
- **DemoPluginMethodName**: a declared method isn't called `function`
  PHP's default checks(`php -l` would catch the class/function name types.)
- **DemoPluginInstanceof**: codebase contains `(expr) instanceof object` (usually invalid, and `is_object()` should be used instead. That would actually be a check for `class object`).

#### DollarDollarPlugin.php

Checks for complex variable access expressions `$$x`, which may be hard to read, and make the variable accesses hard/impossible to analyze.

- **PhanPluginDollarDollar**: Warns about the use of $$x, ${(expr)}, etc.
