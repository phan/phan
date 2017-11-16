Plugins
=======

The plugins in this folder can be used to add additional capabilities to phan.
Add their relative path (.phan/plugins/...) to the `plugins` entry of .phan/config.php.

Plugin Documentation
--------------------

[Wiki Article: Writing Plugins For Phan](https://github.com/etsy/phan/wiki/Writing-Plugins-for-Phan)

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

- **UnusedSuppression**: "Element func/class/etc. suppresses issue Phan... but does not use it"

### 2. General-Use Plugins

These plugins are useful across a wide variety of code styles, and should give low false positives.
Also see [DollarDollarPlugin.php](#dollardollarpluginphp) for a meaningful real-world example.

#### AlwaysReturnPlugin.php

Checks if a function or method with a non-void return type will **unconditionally** return or throw.
This is stricter than Phan's default checks (Phan accepts a function or method that **may** return something, or functions that unconditionally throw).

#### DuplicateArrayKeyPlugin.php

Warns about common errors in php array keys and switch statements. Has the following checks (This is able to resolve global and class constants to their scalar values).

- **PhanPluginDuplicateArrayKey**: a duplicate or equivalent array key literal.

  (E.g `switch ($x) { case 2: echo "A\n"; break; case 2: echo "B\n"; break;}` duplicates the key `2`. The later case statements are ignored.)
- **PhanPluginDuplicateSwitchCase**: a duplicate or equivalent case statement.

  (E.g `[2 => "value", "other" => "s", "2" => "value2"]` duplicates the key `2`)
- **PhanPluginMixedKeyNoKey**: mixing array entries of the form [key => value,] with entries of the form [value,].

  (E.g. `['key' => 'value', 'othervalue']` is often found in code because the key for `'othervalue'` was forgotten)

#### PregRegexCheckerPlugin

This plugin checks for invalid regexes.
This plugin is able to resolve literals, global constants, and class constants as regexes.

- **PhanPluginInvalidPregRegex**: The provided regex is invalid, according to PHP.

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

                        'PhanPluginPrintfNotPercent',
                        'PhanPluginPrintfTranslatedWidthNotPosition',
                    'PhanPluginPrintfIncompatibleSpecifier',
                        'PhanPluginPrintfIncompatibleArgumentTypeWeak',
                        'PhanPluginPrintfIncompatibleArgumentType',
                            $issue_type = 'PhanPluginPrintfTranslatedIncompatible';
                            $issue_type = 'PhanPluginPrintfTranslatedHasMoreArgs';
#### UnreachableCodePlugin.php

Checks for syntactically unreachable statements in the global scope or function bodies.
(E.g. function calls after unconditional `continue`/`break`/`throw`/`return`/`exit()` statements)

- **PhanPluginUnreachableCode**: `Unreachable statement detected`

#### Unused variable detection

See https://github.com/etsy/phan/issues/345

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

#### InvalidVariableIssetPlugin.php

Warns about invalid uses of `isset`. This README documentation may be inaccurate for this plugin.

- **PhanPluginInvalidVariableIsset** : Forces all uses of `isset` to be on arrays or variables.

  E.g. it will warn about `isset(foo()['key'])`, because foo() is not a variable or an array access.
- **PhanUndeclaredVariable**: Warns if `$array` is undeclared in `isset($array[$key])`

#### NumericalComparisonPlugin.php

Enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`), and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).

- **PhanPluginNumericalComparison**: non numerical values compared by the operators '==' or '!=='; numerical values compared by the operators '===' or '!=='

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
