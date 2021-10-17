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

The following settings can be used in `.phan/config.php`:
 - `'plugin_config' => ['unused_suppression_ignore_list' => ['FlakyPluginIssueName']]` will make this plugin avoid emitting `Unused*Suppression` for a list of issue names.
 - `'plugin_config' => ['unused_suppression_whitelisted_only' => true]` will make this plugin report unused suppressions only for issues in `whitelist_issue_types`.

#### FFIAnalysisPlugin.php

This is only necessary if you are using [PHP 7.4's FFI (Foreign Function Interface) support](https://wiki.php.net/rfc/ffi)

This makes Phan infer that assignments to variables that originally contained CData will continue to be CData.

### 2. General-Use Plugins

These plugins are useful across a wide variety of code styles, and should give low false positives.
Also see [DollarDollarPlugin.php](#dollardollarpluginphp) for a meaningful real-world example.

#### AlwaysReturnPlugin.php

Checks if a function or method with a non-void return type will **unconditionally** return or throw.
This is stricter than Phan's default checks (Phan accepts a function or method that **may** return something, or functions that unconditionally throw).

- **PhanPluginInconsistentReturnMethod**: `Method {METHOD} has no return type and will inconsistently return or not return`
- **PhanPluginAlwaysReturnMethod**: `Method {METHOD} has a return type of {TYPE}, but may fail to return a value`
- **PhanPluginInconsistentReturnFunction**: `Function {FUNCTION} has no return type and will inconsistently return or not return`
- **PhanPluginAlwaysReturnFunction**: `Function {FUNCTION} has a return type of {TYPE}, but may fail to return a value`

#### DuplicateArrayKeyPlugin.php

Warns about common errors in php array keys and switch statements. Has the following checks (This is able to resolve global and class constants to their scalar values).

- **PhanPluginDuplicateArrayKey**: a duplicate or equivalent array key literal.

  (E.g `[2 => "value", "other" => "s", "2" => "value2"]` duplicates the key `2`)
- **PhanPluginDuplicateArrayKeyExpression**: `Duplicate/Equivalent dynamic array key expression ({CODE}) detected in array - the earlier entry will be ignored if the expression had the same value.`
  (E.g. `[$x => 'value', $y => "s", $y => "value2"]`)
- **PhanPluginDuplicateSwitchCase**: a duplicate or equivalent case statement.

  (E.g `switch ($x) { case 2: echo "A\n"; break; case 2: echo "B\n"; break;}` duplicates the key `2`. The later case statements are ignored.)
- **PhanPluginDuplicateSwitchCaseLooseEquality**: a case statement that is loosely equivalent to an earlier case statement.

  (E.g `switch ('foo') { case 0: echo "0\n"; break; case 'foo': echo "foo\n"; break;}` has `0 == 'foo'`, and echoes `0` because of that)
- **PhanPluginMixedKeyNoKey**: mixing array entries of the form [key => value,] with entries of the form [value,].

  (E.g. `['key' => 'value', 'othervalue']` is often found in code because the key for `'othervalue'` was forgotten)

#### PregRegexCheckerPlugin

This plugin checks for invalid regexes.
This plugin is able to resolve literals, global constants, and class constants as regexes.

- **PhanPluginInvalidPregRegex**: The provided regex is invalid, according to PHP.
- **PhanPluginInvalidPregRegexReplacement**: The replacement string template of `preg_replace` refers to a match group that doesn't exist. (e.g. `preg_replace('/x(a)/', 'y$2', $strVal)`)
- **PhanPluginRegexDollarAllowsNewline**: `Call to {FUNCTION} used \'$\' in {STRING_LITERAL}, which allows a newline character \'\n\' before the end of the string. Add D to qualifiers to forbid the newline, m to match any newline, or suppress this issue if this is deliberate`
  (This issue type is specific to coding style, and only checked for when configuration includes `['plugin_config' => ['regex_warn_if_newline_allowed_at_end' => true]]`)

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
- **PhanPluginPrintfIncompatibleArgumentType**: `Format string {STRING_LITERAL} refers to argument #{INDEX} as {DETAILS}, so type {TYPE} is expected, but {FUNCTION} was passed incompatible type {TYPE}`
- **PhanPluginPrintfVariableFormatString**: `Code {CODE} has a dynamic format string that could not be inferred by Phan`

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

As of Phan 2.7.2, it is also possible to locally configure the PHP binary (or binaries) to run syntax checks with.
e.g. `phan --native-syntax-check php --native-syntax-check /usr/bin/php7.4` would run checks both with `php` (resolved with `$PATH`)
and the absolute path `/usr/bin/php7.4`. (see `phan --extended-help`)

#### UseReturnValuePlugin.php

This plugin warns when code fails to use the return value of internal functions/methods such as `sprintf` or `array_merge` or `Exception->getCode()`.
(functions/methods where the return value should almost always be used)

This also warns when using a return value of a function that returns the type `never`.

- **PhanPluginUseReturnValueInternalKnown**: `Expected to use the return value of the internal function/method {FUNCTION}` (and similar issues),
- **PhanPluginUseReturnValueGenerator**: `Expected to use the return value of the function/method {FUNCTION} returning a generator of type {TYPE}`,
- **PhanUseReturnValueOfNever**: `Saw use of value of expression {CODE} which likely uses the function {FUNCTIONLIKE} with a return type of '{TYPE}' - this will not return normally`,

`'plugin_config' => ['infer_pure_method' => true]` will make this plugin automatically infer which methods are pure, recursively.
This is a best-effort heuristic.
This is done only for the functions and methods that are not excluded from analysis,
and it isn't done for methods that override or are overridden by other methods.

Note that functions such as `fopen()` are not pure due to side effects.
UseReturnValuePlugin also warns about those because their results should be used.

* This setting is ignored in the language server or daemon mode,
  due to being extremely slow and memory intensive.

Automatic inference of function purity is done recursively.

This plugin also has a dynamic mode(disabled by default and slow) where it will warn if a function or method's return value is unused.
This checks if the function/method's return value is used 98% or more of the time, then warns about the remaining places where the return value was unused.
Note that this prevents the hardcoded checks from working.

- **PhanPluginUseReturnValue**: `Expected to use the return value of the user-defined function/method {FUNCTION} - {SCALAR}%% of calls use it in the rest of the codebase`,
- **PhanPluginUseReturnValueInternal**: `Expected to use the return value of the internal function/method {FUNCTION} - {SCALAR}%% of calls use it in the rest of the codebase`,
- **PhanPluginUseReturnValueGenerator**: `Expected to use the return value of the function/method {FUNCTION} returning a generator of type {TYPE}`,

See [UseReturnValuePlugin.php](./UseReturnValuePlugin.php) for configuration options.

#### PHPUnitAssertionPlugin.php

This plugin will make Phan infer side effects from calls to some of the helper methods that PHPUnit provides in test cases.

- Infer that a condition is truthy from `assertTrue()` and `assertNotFalse()` (e.g. `assertTrue($x instanceof MyClass)`)
- Infer that a condition is null/not null from `assertNull()` and `assertNotNull()`
- Infer class types from `assertInstanceOf(MyClass::class, $actual)`
- Infer types from `assertInternalType($expected, $actual)`
- Infer that $actual has the exact type of $expected after calling `assertSame($expected, $actual)`
- Other methods aren't supported yet.

#### EmptyStatementListPlugin.php

This file checks for empty statement lists in loops/branches.
Due to Phan's AST rewriting for easier analysis, this may miss some edge cases for if/elseif.

By default, this plugin won't warn if it can find a TODO/FIXME/"Deliberately empty" comment around the empty statement list (case insensitive).
(This may miss some TODOs due to `php-ast` not providing the end line numbers)
The setting `'plugin_config' => ['empty_statement_list_ignore_todos' => true]` can be used to make it unconditionally warn about empty statement lists.

- **PhanPluginEmptyStatementDoWhileLoop** `Empty statement list statement detected for the do-while loop`
- **PhanPluginEmptyStatementForLoop** `Empty statement list statement detected for the for loop`
- **PhanPluginEmptyStatementForeachLoop** `Empty statement list statement detected for the foreach loop`
- **PhanPluginEmptyStatementIf**: `Empty statement list statement detected for the last if/elseif statement`
- **PhanPluginEmptyStatementSwitch** `No side effects seen for any cases of this switch statement`
- **PhanPluginEmptyStatementTryBody** `Empty statement list statement detected for the try statement's body`
- **PhanPluginEmptyStatementPossiblyNonThrowingTryBody**: `Found a try block that looks like it might not throw. Note that this check is a heuristic prone to false positives, especially because error handlers, signal handlers, destructors, and other things may all lead to throwing.`
- **PhanPluginEmptyStatementTryFinally** `Empty statement list statement detected for the try's finally body`
- **PhanPluginEmptyStatementWhileLoop** `Empty statement list statement detected for the while loop`

### LoopVariableReusePlugin.php

This plugin detects reuse of loop variables.

- **PhanPluginLoopVariableReuse** `Variable ${VARIABLE} used in loop was also used in an outer loop on line {LINE}`

### RedundantAssignmentPlugin.php

This plugin checks for assignments where the variable already
has the given value.
(E.g. `$result = false; if (cond()) { $result = false; }`)

- **PhanPluginRedundantAssignment** `Assigning {TYPE} to variable ${VARIABLE} which already has that value`
- **PhanPluginRedundantAssignmentInLoop** `Assigning {TYPE} to variable ${VARIABLE} which already has that value`
- **PhanPluginRedundantAssignmentInGlobalScope** `Assigning {TYPE} to variable ${VARIABLE} which already has that value`

### UnknownClassElementAccessPlugin.php

This plugin checks for accesses to unknown class elements that can't be type checked (which may hide potential runtime errors such as having too few parameters).
To reduce false positives, this will suppress warnings if at least one recursive analysis could infer class/interface types for the object.

- **PhanPluginUnknownObjectMethodCall**: `Phan could not infer any class/interface types for the object of the method call {CODE} - inferred a type of {TYPE}`

This works best when there is only one analysis process (the default, i.e. `--processes 1`).
`--analyze-twice` will reduce the number of issues this emits.

### MoreSpecificElementTypePlugin.php

This plugin checks for return types that can be made more specific.
**This has a large number of false positives - it can be used manually to point out comments that should be made more specific, but is not recommended as part of a build.**

- **PhanPluginMoreSpecificActualReturnType**: `Phan inferred that {FUNCTION} documented to have return type {TYPE} returns the more specific type {TYPE}`
- **PhanPluginMoreSpecificActualReturnTypeContainsFQSEN**: `Phan inferred that {FUNCTION} documented to have return type {TYPE} (without an FQSEN) returns the more specific type {TYPE} (with an FQSEN)`

It's strongly recommended to use this with a single analysis process (the default, i.e. `--processes 1`).

This uses the following heuristics to reduce the number of false positives.

- Avoids warning about methods that are overrides or are overridden.
- Avoids checking generators.
- Flattens array shapes and literals before comparing types
- Avoids warning when the actual return type contains multiple types and the declared return type is a single FQSEN
  (e.g. don't warn about `Subclass1|Subclass2` being more specific than `BaseClass`)

#### UnsafeCodePlugin.php

This warns about code constructs that may be unsafe and prone to being used incorrectly in general.

- **PhanPluginUnsafeEval**: `eval() is often unsafe and may have better alternatives such as closures and is unanalyzable. Suppress this issue if you are confident that input is properly escaped for this use case and there is no better way to do this.`
- **PhanPluginUnsafeShellExec**: `This syntax for shell_exec() ({CODE}) is easily confused for a string and does not allow proper exit code/stderr handling. Consider proc_open() instead.`
- **PhanPluginUnsafeShellExecDynamic**: `This syntax for shell_exec() ({CODE}) is easily confused for a string and does not allow proper exit code/stderr handling, and is used with a non-constant. Consider proc_open() instead.`

### 3. Plugins Specific to Code Styles

These plugins may be useful to enforce certain code styles,
but may cause false positives in large projects with different code styles.

#### NonBool

##### NonBoolBranchPlugin.php

- **PhanPluginNonBoolBranch** Warns if an expression which has types other than `bool` is used in an if/else if.

  (E.g. warns about `if ($x)`, where $x is an integer. Fix by checking `if ($x != 0)`, etc.)

##### NonBoolInLogicalArithPlugin.php

- **PhanPluginNonBoolInLogicalArith** Warns if an expression where the left/right-hand side has types other than `bool` is used in a binary operation.

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
- This can be used to warn about duplicate method/property descriptions with `plugin_config => [..., 'has_phpdoc_check_duplicates' => true]`
  (this skips checking method overrides, magic methods, and deprecated methods/properties)

The warning types for methods are below:

- **PhanPluginNoCommentOnPublicMethod**: `Public method {METHOD} has no doc comment` (Also exists for Private and Protected)
- **PhanPluginDescriptionlessCommentOnPublicMethod**: `Public method {METHOD} has no readable description: {STRING_LITERAL}` (Also exists for Private and Protected)
- **PhanPluginDuplicatePropertyDescription**: `Property {PROPERTY} has the same description as the property {PROPERTY} on line {LINE}: {COMMENT}`
- **PhanPluginDuplicateMethodDescription**: `Method {METHOD} has the same description as the method {METHOD} on line {LINE}: {COMMENT}`

#### PHPDocInWrongCommentPlugin

This plugin warns about using phpdoc annotations such as `@param` in block comments(`/*`) instead of phpdoc comments(`/**`).
This also warns about using `#` instead of `//` for line comments, because `#[` is used for php 8.0 attributes and will cause confusion.

- **PhanPluginPHPDocInWrongComment**: `Saw possible phpdoc annotation in ordinary block comment {COMMENT}. PHPDoc comments should start with "/**", not "/*"`
- **PhanPluginPHPDocHashComment**: `Saw comment starting with # in {COMMENT} - consider using // instead to avoid confusion with php 8.0 #[ attributes`

#### InvalidVariableIssetPlugin.php

Warns about invalid uses of `isset`. This README documentation may be inaccurate for this plugin.

- **PhanPluginInvalidVariableIsset** : Forces all uses of `isset` to be on arrays or variables.

  E.g. it will warn about `isset(foo()['key'])`, because foo() is not a variable or an array access.
- **PhanUndeclaredVariable**: Warns if `$array` is undeclared in `isset($array[$key])`

#### NoAssertPlugin.php

Discourages the usage of assert() in the analyzed project.
See https://secure.php.net/assert

- **PhanPluginNoAssert**: `assert() is discouraged. Although phan supports using assert() for type annotations, PHP's documentation recommends assertions only for debugging, and assert() has surprising behaviors.`

#### NotFullyQualifiedUsagePlugin.php

Encourages the usage of fully qualified global functions and constants (slightly faster, especially for functions such as `strlen`, `count`, etc.)

- **PhanPluginNotFullyQualifiedFunctionCall**: `Expected function call to {FUNCTION}() to be fully qualified or have a use statement but none were found in namespace {NAMESPACE}`
- **PhanPluginNotFullyQualifiedOptimizableFunctionCall**: `Expected function call to {FUNCTION}() to be fully qualified or have a use statement but none were found in namespace {NAMESPACE} (opcache can optimize fully qualified calls to this function in recent php versions)`
- **PhanPluginNotFullyQualifiedGlobalConstant**: `Expected usage of {CONST} to be fully qualified or have a use statement but none were found in namespace {NAMESPACE}`

#### NumericalComparisonPlugin.php

Enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`), and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).

- **PhanPluginNumericalComparison**: `nonnumerical values compared by the operators '==' or '!=='; numerical values compared by the operators '===' or '!=='`

#### StrictLiteralComparisonPlugin.php

Enforces that strict equality is used for comparisons to constant/literal integers or strings.
This is used to avoid surprising behaviors such as `0 == 'a'`, `"10" == "1e1"`, etc.
*Following the advice of this plugin may subtly break existing code (e.g. break implicit null/false checks, or code relying on these unexpected behaviors).*

- **PhanPluginComparisonNotStrictForScalar**: `Expected strict equality check when comparing {TYPE} to {TYPE} in {CODE}`

Also see [`StrictComparisonPlugin`](#StrictComparisonPlugin.php) and [`NumericalComparisonPlugin`](#NumericalComparisonPlugin.php).

#### PHPUnitNotDeadCodePlugin.php

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
- **PhanPluginUnknownFunctionParamType**: `Function {FUNCTION} has no declared or inferred parameter type for ${PARAMETER}`
- **PhanPluginUnknownClosureReturnType**: `Closure {FUNCTION} has no declared or inferred return type`
- **PhanPluginUnknownClosureParamType**: `Closure {FUNCTION} has no declared or inferred parameter type for ${PARAMETER}`
- **PhanPluginUnknownPropertyType**: `Property {PROPERTY} has an initial type that cannot be inferred`

#### DuplicateExpressionPlugin.php

This plugin checks for duplicate expressions in a statement
that are likely to be a bug. (e.g. `expr1 == expr`)

This will significantly increase the memory used by Phan, but that's rarely an issue in small projects.

- **PhanPluginDuplicateExpressionAssignment**: `Both sides of the assignment {OPERATOR} are the same: {CODE}`
- **PhanPluginDuplicateExpressionBinaryOp**: `Both sides of the binary operator {OPERATOR} are the same: {CODE}`
- **PhanPluginDuplicateConditionalTernaryDuplication**: `"X ? X : Y" can usually be simplified to "X ?: Y". The duplicated expression X was {CODE}`
- **PhanPluginDuplicateConditionalNullCoalescing**: `"isset(X) ? X : Y" can usually be simplified to "X ?? Y" in PHP 7. The duplicated expression X was {CODE}`
- **PhanPluginBothLiteralsBinaryOp**: `Suspicious usage of a binary operator where both operands are literals. Expression: {CODE} {OPERATOR} {CODE} (result is {CODE})` (e.g. warns about `null == 'a literal` in `$x ?? null == 'a literal'`)
- **PhanPluginDuplicateConditionalUnnecessary**: `"X ? Y : Y" results in the same expression Y no matter what X evaluates to. Y was {CODE}`
- **PhanPluginDuplicateCatchStatementBody**: `The implementation of catch({CODE}) and catch({CODE}) are identical, and can be combined if the application only needs to supports php 7.1 and newer`
- **PhanPluginDuplicateAdjacentStatement**: `Statement {CODE} is a duplicate of the statement on the above line. Suppress this issue instance if there's a good reason for this.`

  Note that equivalent catch statements may be deliberate or a coding style choice, and this plugin does not check for TODOs.

#### WhitespacePlugin.php

This plugin checks for unexpected whitespace in PHP files.

- **PhanPluginWhitespaceCarriageReturn**: `The first occurrence of a carriage return ("\r") was seen here. Running "dos2unix" can fix that.`
- **PhanPluginWhitespaceTab**: `The first occurrence of a tab was seen here. Running "expand" can fix that.`
- **PhanPluginWhitespaceTrailing**: `The first occurrence of trailing whitespace was seen here.`

#### InlineHTMLPlugin.php

This plugin checks for unexpected inline HTML.

This can be limited to a subset of files with an `inline_html_whitelist_regex` - e.g. `@^(src/|lib/)@`.

Files can be excluded with `inline_html_blacklist_regex`, e.g. `@(^src/templates/)|(\.html$)@`

- **PhanPluginInlineHTML**: `Saw inline HTML between the first and last token: {STRING_LITERAL}`
- **PhanPluginInlineHTMLLeading**: `Saw inline HTML at the start of the file: {STRING_LITERAL}`
- **PhanPluginInlineHTMLTrailing**: `Saw inline HTML at the end of the file: {STRING_LITERAL}`

#### SuspiciousParamOrderPlugin.php

This plugin guesses if arguments to a function call are out of order, based on heuristics on the name in the expression (e.g. variable name).
This will only warn if the argument types are compatible with the alternate parameters being suggested.
This may be useful when analyzing methods with long parameter lists.

E.g. warns about invoking `function example($first, $second, $third)` as `example($mySecond, $myThird, $myFirst)`

- **PhanPluginSuspiciousParamOrder**: `Suspicious order for arguments named {DETAILS} - These are being passed to parameters {DETAILS} of {FUNCTION} defined at {FILE}:{LINE}`
- **PhanPluginSuspiciousParamOrderInternal**: `Suspicious order for arguments named {DETAILS} - These are being passed to parameters {DETAILS}`

#### PossiblyStaticMethodPlugin.php

Checks if a method can be made static without causing any errors.

- **PhanPluginPossiblyStaticPublicMethod**: `Public method {METHOD} can be static` (Also exists for Private and Protected)
- **PhanPluginPossiblyStaticClosure**: `{FUNCTION} can be static`

Warnings may need to be completely disabled due to the large number of method declarations in a typical codebase:

- Warnings are not emitted for methods that override methods in the parent class.
- Warnings are not emitted for methods that are overridden in child classes.
- Warnings can be suppressed based on the method FQSEN with `plugin_config => [..., 'possibly_static_method_ignore_regex' => (a PCRE regex)]`

#### PHPDocToRealTypesPlugin.php

This plugin suggests real types that can be used instead of phpdoc types.
Currently, this just checks param and return types.
Some of the suggestions made by this plugin will cause inheritance errors.

This doesn't suggest changes if classes have subclasses (but this check doesn't work when inheritance involves traits).
`PHPDOC_TO_REAL_TYPES_IGNORE_INHERITANCE=1` can be used to force this to check **all** methods and emit issues.

This also supports `--automatic-fix` to add the types to the real type signatures.

- **PhanPluginCanUseReturnType**: `Can use {TYPE} as a return type of {METHOD}`
- **PhanPluginCanUseNullableReturnType**: `Can use {TYPE} as a return type of {METHOD}` (useful if there is a minimum php version of 7.1)
- **PhanPluginCanUsePHP71Void**: `Can use php 7.1's void as a return type of {METHOD}` (useful if there is a minimum php version of 7.1)

This supports `--automatic-fix`.
- `PHPDocRedundantPlugin` will be useful for cleaning up redundant phpdoc after real types were added.
- `PreferNamespaceUsePlugin` can be used to convert types from fully qualified types back to unqualified types ()

#### PHPDocRedundantPlugin.php

This plugin warns about function/method/closure phpdoc that does nothing but repeat the information in the type signature.
E.g. this will warn about `/** @return void */ function () : void {}` and `/** */`, but not `/** @return void description of what it does or other annotations */`

This supports `--automatic-fix`

- **PhanPluginRedundantFunctionComment**: `Redundant doc comment on function {FUNCTION}(). Either add a description or remove the comment: {COMMENT}`
- **PhanPluginRedundantMethodComment**: `Redundant doc comment on method {METHOD}(). Either add a description or remove the comment: {COMMENT}`
- **PhanPluginRedundantClosureComment**: `Redundant doc comment on closure {FUNCTION}. Either add a description or remove the comment: {COMMENT}`
- **PhanPluginRedundantReturnComment**: `Redundant @return {TYPE} on function {FUNCTION}. Either add a description or remove the @return annotation: {COMMENT}`

#### PreferNamespaceUsePlugin.php

This plugin suggests using `ClassName` instead of `\My\Ns\ClassName` when there is a `use My\Ns\ClassName` annotation (or for uses in namespace `\My\Ns`)
Currently, this only checks **real** (not phpdoc) param/return annotations.

- **PhanPluginPreferNamespaceUseParamType**: `Could write param type of ${PARAMETER} of {FUNCTION} as {TYPE} instead of {TYPE}`
- **PhanPluginPreferNamespaceUseReturnType**: `Could write return type of {FUNCTION} as {TYPE} instead of {TYPE}`

##### StrictComparisonPlugin.php

This plugin warns about non-strict comparisons. It warns about the following issue types:

1. Using `in_array` and `array_search` without explicitly passing true or false to `$strict`.
2. Using equality or comparison operators when both sides are possible objects.

- **PhanPluginComparisonNotStrictInCall**: `Expected {FUNCTION} to be called with a third argument for {PARAMETER} (either true or false)`
- **PhanPluginComparisonObjectEqualityNotStrict**: `Saw a weak equality check on possible object types {TYPE} and {TYPE} in {CODE}`
- **PhanPluginComparisonObjectOrdering**: `Saw a weak equality check on possible object types {TYPE} and {TYPE} in {CODE}`

##### EmptyMethodAndFunctionPlugin.php

This plugin looks for empty methods/functions.
Note that this is not emitted for empty statement lists in functions or methods that are overrides, are overridden, or are deprecated.

- **PhanEmptyClosure**: `Empty closure`
- **PhanEmptyFunction**: `Empty function {FUNCTION}`
- **PhanEmptyPrivateMethod**: `Empty private method {METHOD}`
- **PhanEmptyProtectedMethod**: `Empty protected method {METHOD}`
- **PhanEmptyPublicMethod**: `Empty public method {METHOD}`

#### DollarDollarPlugin.php

Checks for complex variable access expressions `$$x`, which may be hard to read, and make the variable accesses hard/impossible to analyze.

- **PhanPluginDollarDollar**: Warns about the use of $$x, ${(expr)}, etc.

### DeprecateAliasPlugin.php

Makes Phan analyze aliases of global functions (e.g. `join()`, `sizeof()`) as if they were deprecated.
Supports `--automatic-fix`.

#### PHP53CompatibilityPlugin.php

Catches common incompatibilities from PHP 5.3 to 5.6.
**This plugin does not aim to be comprehensive - read the guides on https://www.php.net/manual/en/appendices.php if you need to migrate from php versions older than 5.6**

`InvokePHPNativeSyntaxCheckPlugin` with `'php_native_syntax_check_binaries' => [PHP_BINARY, '/path/to/php53']` in the `'plugin_config'` is a better but slower way to check that syntax used does not cause errors in PHP 5.3.

`backward_compatibility_checks` should also be enabled if migrating a project from php 5 to php 7.

Emitted issue types:

- **PhanPluginCompatibilityShortArray**: `Short arrays ({CODE}) require support for php 5.4+`
- **PhanPluginCompatibilityArgumentUnpacking**: `Argument unpacking ({CODE}) requires support for php 5.6+`
- **PhanPluginCompatibilityVariadicParam**: `Variadic functions ({CODE}) require support for php 5.6+`

#### DuplicateConstantPlugin.php

Checks for duplicate constant names for calls to `define()` or `const X =` within the same statement list.

- **PhanPluginDuplicateConstant**: `Constant {CONST} was previously declared at line {LINE} - the previous declaration will be used instead`

#### AvoidableGetterPlugin.php

This plugin checks for uses of getters on `$this` that can be avoided inside of a class.
(E.g. calling `$this->getFoo()` when the property `$this->foo` is accessible, and there are no known overrides of the getter)

- **PhanPluginAvoidableGetter**: `Can replace {METHOD} with {PROPERTY}`
- **PhanPluginAvoidableGetterInTrait**: `Can replace {METHOD} with {PROPERTY}`

Note that switching to properties makes the code slightly faster,
but may break code outside of the library that overrides those getters,
or hurt the readability of code.

This will also remove runtime type checks that were enforced by the getter's return type.

#### ConstantVariablePlugin.php

This plugin warns about using variables when they probably have only one possible scalar value (or the only inferred type is `null`).
This may catch some logic errors such as `echo($result === null ? json_encode($result) : 'default')`, or indicate places where it may or may not be clearer to use the constant itself.
Most of the reported issues will likely not be worth fixing, or be false positives due to references/loops.

- **PhanPluginConstantVariableBool**: `Variable ${VARIABLE} is probably constant with a value of {TYPE}`
- **PhanPluginConstantVariableNull**: `Variable ${VARIABLE} is probably constant with a value of {TYPE}`
- **PhanPluginConstantVariableScalar**: `Variable ${VARIABLE} is probably constant with a value of {TYPE}`

#### ShortArrayPlugin.php

This suggests using shorter array syntaxes if supported by the `minimum_target_php_version`.

- **PhanPluginLongArray**: `Should use [] instead of array()`
- **PhanPluginLongArrayList**: `Should use [] instead of list()`

#### RemoveDebugStatementPlugin.php

This suggests removing debugging output statements such as `echo`, `print`, `printf`, fwrite(STDERR)`, `var_export()`, inline html, etc.
This is only useful in applications or libraries that print output in only a few places, as a sanity check that debugging statements are not accidentally left in code.

- **PhanPluginRemoveDebugEcho**: `Saw output expression/statement in {CODE}`
- **PhanPluginRemoveDebugCall**: `Saw call to {FUNCTION} for debugging`

Suppression comments can use the issue name `PhanPluginRemoveDebugAny` to suppress all issue types emitted by this plugin.

#### AddNeverReturnTypePlugin.php

This plugin checks if a function or method will not return (and has no overrides).
If the function doesn't have a return type of never.
then this plugin will emit an issue.
Closures and short error functions are currently not checked

- **PhanPluginNeverReturnMethod**: `Method {METHOD} never returns and has a return type of {TYPE}, but phpdoc type {TYPE} could be used instead`
- **PhanPluginNeverReturnFunction**: `Function {FUNCTION} never returns and has a return type of {TYPE}, but phpdoc type {TYPE} could be used instead`

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

### 5. Third party plugins

- https://github.com/Drenso/PhanExtensions is a third party project with several plugins to do the following:

  - Analyze Symfony doc comment annotations.
  - Mark elements in inline doc comments (which Phan doesn't parse) as referencing types from `use statements` as not dead code.

- https://github.com/TysonAndre/PhanTypoCheck checks all tokens of PHP files for typos, including within string literals.
  It is also able to analyze calls to `gettext()`.

### 6. Self-analysis plugins:

#### PhanSelfCheckPlugin.php

This plugin checks for invalid calls to `PluginV2::emitIssue`, `Issue::maybeEmit()`, etc.
This is useful for developing Phan and Phan plugins.

- **PhanPluginTooFewArgumentsForIssue**: `Too few arguments for issue {STRING_LITERAL}: expected {COUNT}, got {COUNT}`
- **PhanPluginTooManyArgumentsForIssue**: `Too many arguments for issue {STRING_LITERAL}: expected {COUNT}, got {COUNT}`
- **PhanPluginUnknownIssueType**: `Unknown issue type {STRING_LITERAL} in a call to {METHOD}(). (may be a false positive - check if the version of Phan running PhanSelfCheckPlugin is the same version that the analyzed codebase is using)`
