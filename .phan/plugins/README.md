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

#### DuplicateArrayKeyPlugin.php

Warns about common errors in php array keys. Has the following checks (Doesn't try to resolve constants).

- **PhanPluginDuplicateArrayKey**: a duplicate or equivalent array key literal

  (E.g `[2 => "value", "other" => "s", "2" => "value2"]` duplicates the key `2`)
- **PhanPluginMixedKeyNoKey**: mixing array entries of the form [key => value,] with entries of the form [value,].

  (E.g. `['key' => 'value', 'othervalue']` is often found in code because the key for `'othervalue'` was forgotten)

#### AlwaysReturnPlugin.php

Checks if a function or method with a non-void return type will **unconditionally** return or throw.
This is stricter than Phan's default checks (Phan accepts a function or method that **may** return something, or functions that unconditionally throw).

#### UnconditionalCodePlugin.php

Checks for syntactically unreachable statements in the global scope or function bodies.
(E.g. function calls after unconditional continue/break/throw/return/exit())

This is in development, and has some edge cases related to try blocks.

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
