# anatomy.md

> Auto-maintained by OpenWolf. Last scanned: 2026-10-07T02:15:53.171Z
> Files: 581 tracked | Anatomy hits: 0 | Misses: 0

## ../../../tmp/claude-1000/-home-rasmus-phan/a5bbe4fc-d70a-48ee-931f-56a76c85b419/scratchpad/

- `t5556_nullable.php` — A: test (~96 tok)
- `t5556_nullable2.php` — Box: test (~55 tok)
- `t5556.php` — A: getOrDefault, test1, test2 (~320 tok)
- `t5556b.php` — A: test, test, test (~176 tok)
- `t5558_codex2.php` — Foo5558b: output (~250 tok)
- `t5558_codex3.php` — Foo5558c: output, onlyOnA (~366 tok)
- `t5558_codex4.php` — Foo5558e: testClassStringDynamicClassConst, make, bar (~126 tok)
- `t5558_codex4b.php` — Foo5558f: output (~112 tok)
- `t5558_codex5.php` — Foo5558g: make, wrap (~79 tok)
- `t5558_instance.php` — Foo5558: instanceMethod, output (~166 tok)
- `t5558.php` — Foo: output (~258 tok)

## ../.claude/plans/

- `evaluate-pr-5557-glistening-dijkstra.md` — Fix issue #5558 — `class-string<X>` doesn't resolve for static calls (~2178 tok)
- `typed-sparking-lightning.md` — Fix: try/finally (no catch) false positive for possibly-undefined variables (~579 tok)

## ../.claude/projects/-home-rasmus-phan/memory/

- `bugfixes.md` — Issue #5462 — PhanUnreferencedUseNormal false positive in large arrays (~2552 tok)
- `MEMORY.md` — Phan Project Memory (~780 tok)
- `plugins.md` — DependentReturnTypeOverridePlugin (~981 tok)
- `testing.md` — Test File Structure (~448 tok)

## ../.myclaude/plans/

- `evaluate-issue-5565-and-async-pnueli.md` — Fix #5565: `!array_is_list($x)` wraps the array type instead of converting it (~1908 tok)
- `issue-5566-array-diff-intersect-signatures.md` — Fix #5566: array_diff/array_intersect family requires 2 arrays, but PHP 8.0+ requires 1 (~1751 tok)
- `plan-the-implementation-for-whimsical-newt.md` — Conditional return types in phpdoc (issue #5574) (~3183 tok)

## ./

- `.appveyor.yml` — This tests against the supported versions of Phan 6.x.y (PHP 8.1+) (~1158 tok)
- `.codeclimate.yml` (~194 tok)
- `.dockerignore` — Docker ignore rules (~80 tok)
- `.editorconfig` — Editor configuration (~82 tok)
- `.gitattributes` — Git attributes (~303 tok)
- `.gitignore` — Git ignore rules (~84 tok)
- `5553.md` — Foo: neverReturns, method, fatalErrorOnNull (~892 tok)
- `5572.md` — Review response to PR #5572 (truthy literal comparison split): numeric strings, operand type, NEWS, test namespace (~700 tok)
- `bug4419.md` — Fix false positive PhanPossiblyUndeclaredVariable in try/finally with no catch (~486 tok)
- `bug5440.md` — Declares assumptions (~264 tok)
- `bug5441.md` — Fix false-positive PhanRedundantCondition/PhanCoalescingNeverNull for ArrayAccess isset (#5441) (~344 tok)
- `bug5442.md` — Fix InvalidFQSENException crash on `non-empty-mixed` method calls (#5442) (~335 tok)
- `bug5444-again.md` — Summary (~506 tok)
- `bug5444.md` — Bug (~554 tok)
- `bug5462.md` — Declares references (~532 tok)
- `bug5483.md` — Fix AnalyzeFunctionCallCapability infrastructure issues (#5483) (~943 tok)
- `bug5491.md` — Summary (~541 tok)
- `bug5514.md` — Summary (~394 tok)
- `bug5521.md` — Fix #5521: Union types with spaces not parsed inside array shapes and generics (~403 tok)
- `bug5522.md` — Fix #5522: False positive PhanTypeMismatchArgument with bounded template types (~844 tok)
- `bug5528.md` — Summary (~463 tok)
- `bug5531.md` — Summary (~611 tok)
- `bug5535.md` — Summary (~785 tok)
- `bug5544.md` — Fix #5544: Inherit ancestor property type when a child redeclares it (~730 tok)
- `bug5551.md` — Fix #5551: Make substr_replace() return type depend on the $string argument (~682 tok)
- `bug5553.md` — Issue #5553: `never` return type not always interpreted correctly on instance methods (~1368 tok)
- `bug5556.md` — Issue #5556: generic `@var` type erased on natively-typed properties (~1311 tok)
- `bug5558.md` — Issue #5558: `class-string<X>` doesn't resolve for static calls (~11452 tok)
- `bug5565.md` — Summary (~1004 tok)
- `bug5566.md` — Summary (~886 tok)
- `CLAUDE.md` — OpenWolf (~3648 tok)
- `CODE_OF_CONDUCT.md` — Code of Conduct (~820 tok)
- `composer.json` — PHP package manifest (~620 tok)
- `DCO.txt` (~356 tok)
- `GEMINI.md` (~0 tok)
- `improve-analyze-twice.md` — Improve `--analyze-twice` and add `--analyze-until-convergence` (~1160 tok)
- `LICENSE` — Project license (~409 tok)
- `LICENSE.LANGUAGE_SERVER` (~268 tok)
- `LICENSE.PHP_PARSER` (~437 tok)
- `LICENSE.PHPSTORM_STUBS` — Declares signature (~83 tok)
- `MAINTAINERS.md` — Maintainers (~193 tok)
- `NEWS.md` — Phan NEWS (~85052 tok)
- `phan` — @phan-file-suppress PhanPluginRemoveDebugAny (~112 tok)
- `phan_client` — Usage: phan_client -l path/to/file.php (~8784 tok)
- `Phan-AGENTS.md` — Phan for Coding Agents (~3903 tok)
- `PhanMCP.md` — Summary (~850 tok)
- `phound-hof-handlers.md` — PhoundPlugin: Track callables passed to higher-order array functions (~483 tok)
- `PhoundPR.md` — Summary (~634 tok)
- `phpcs.xml` — Declares initialization (~1013 tok)
- `phpdoc.dist.xml` (~227 tok)
- `phpspy.2726159.err` (~0 tok)
- `phpspy.2726159.out` (~0 tok)
- `phpspy.2764530.err` (~0 tok)
- `phpspy.2764530.out` (~122645 tok)
- `phpspy.2764719.err` (~0 tok)
- `phpspy.2764719.out` (~122645 tok)
- `phpspy.2764963.err` (~0 tok)
- `phpspy.2764963.out` (~122645 tok)
- `phpspy.2765450.err` (~0 tok)
- `phpspy.2765450.out` (~122645 tok)
- `phpunit.xml` (~1106 tok)
- `prep` (~15 tok)
- `README.md` — Project documentation (~4570 tok)
- `test` (~13 tok)
- `tocheckstyle` (~538 tok)
- `V6_GENERICS.md` — Phan Generics Improvement Roadmap (v6) (~7113 tok)

## .claude/

- `settings.json` (~441 tok)
- `settings.local.json` — Declares in (~2368 tok)

## .claude/plugins/phan-lsp/

- `.lsp.json` (~61 tok)
- `plugin.json` (~41 tok)

## .claude/rules/

- `openwolf.md` (~313 tok)

## .github/

- `CONTRIBUTING.md` (~292 tok)
- `copilot-instructions.md` — Copilot Review Instructions for Phan (~380 tok)

## .github/ISSUE_TEMPLATE/

- `bug_report.md` (~142 tok)
- `config.yml` (~117 tok)
- `feature_request.md` (~227 tok)

## .github/workflows/

- `main.yml` — Runs phan's tests (~437 tok)

## .phan/

- `baseline.php.example` — This is an **example** of an automatically generated baseline for Phan issues. (~239 tok)
- `config.php` — This configuration will be read and overlaid on top of the (~9141 tok)
- `suppress_config.example.php` — Phan Suppression Tool Configuration Example (~272 tok)

## .phan/bin/

- `mkfilelist` (~67 tok)
- `phan` — Root directory of project (~238 tok)

## .phan/plugins/

- `AddNeverReturnTypePlugin.php` — This plugin checks if a function or method will not return (and has no overrides). (~1193 tok)
- `AlwaysReturnPlugin.php` — This file checks if a function, closure or method unconditionally returns. (~2401 tok)
- `AsymmetricVisibilityPlugin.php` — This file checks Asymmetric Visibility (~697 tok)
- `AvoidableGetterPlugin.php` — This plugin checks for uses of getters that can be avoided inside of a class. (~1172 tok)
- `CaseMismatchPlugin.php` — Warns when a reference to a class, function, or method uses different casing (~11470 tok)
- `ConstantVariablePlugin.php` — This plugin detects variables with constant values (~778 tok)
- `DemoPlugin.php` — This file demonstrates plugins for Phan. (~1822 tok)
- `DeprecateAliasPlugin.php` — This plugin deprecates aliases of global functions. (~4590 tok)
- `DollarDollarPlugin.php` — This plugin checks for occurrences of `$$x`, (~572 tok)
- `DuplicateArrayKeyPlugin.php` — Checks for duplicate/equivalent array keys and case statements, as well as arrays mixing `key => value, with `value,`. (~4327 tok)
- `DuplicateConstantPlugin.php` — This plugin checks for duplicate constant declarations within a statement list. (~1024 tok)
- `DuplicateExpressionPlugin.php` — This plugin checks for duplicate expressions in a statement (~5460 tok)
- `EmptyMethodAndFunctionPlugin.php` — Plugin which looks for empty methods/functions (~1019 tok)
- `EmptyStatementListPlugin.php` — This file checks for empty statement lists in loops/branches. (~4109 tok)
- `FFIAnalysisPlugin.php` — This plugin modifies Phan's analysis of code using FFI\CData variables. (~1195 tok)
- `HasPHPDocPlugin.php` — This file checks if an element (class or property) has a PHPDoc comment, (~3988 tok)
- `InlineHTMLPlugin.php` — This plugin checks for accidental whitespace in regular php files. (~1508 tok)
- `InvalidVariableIssetPlugin.php` — This plugin detects undeclared variables within isset() checks. (~1078 tok)
- `InvokePHPNativeSyntaxCheckPlugin.php` — This plugin invokes the equivalent of `php --no-php-ini --syntax-check $analyzed_file_path`. (~4240 tok)
- `LoopVariableReusePlugin.php` (~33 tok)
- `MoreSpecificElementTypePlugin.php` — This plugin checks for return types that can be made more specific. (~2697 tok)
- `NoAssertPlugin.php` — This plugin checks for occurrences of `assert(cond)` for Phan's self-analysis. (~676 tok)
- `NonBoolBranchPlugin.php` — This plugin warns if an expression which has types other than `bool` is used in an if/else if. (~830 tok)
- `NonBoolInLogicalArithPlugin.php` — This plugin checks for non-booleans in either side of logical arithmetic operators (~882 tok)
- `NotFullyQualifiedUsagePlugin.php` — This warns if references to global functions or global constants are not fully qualified. (~2058 tok)
- `NumericalComparisonPlugin.php` — This plugin enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`), (~807 tok)
- `PhanSelfCheckPlugin.php` — This plugin checks for invalid calls to emitIssue, emitPluginIssue, Issue::maybeEmit(), etc. (~3117 tok)
- `PHPDocInWrongCommentPlugin.php` — This plugin checks for the use of phpdoc annotations in non-phpdoc comments (~909 tok)
- `PHPDocRedundantPlugin.php` — This plugin checks for redundant doc comments on functions, closures, and methods. (~3935 tok)
- `PHPDocToRealTypesPlugin.php` — This plugin suggests real types that can be used instead of phpdoc types. (~1796 tok)
- `PHPUnitAssertionPlugin.php` — Mark PHPUnit helper assertions as having side effects. (~2963 tok)
- `PHPUnitNotDeadCodePlugin.php` — Mark all phpunit test cases as used for dead code detection during Phan's self-analysis. (~1643 tok)
- `PossiblyStaticMethodPlugin.php` — This file checks if a method can be made static without causing any errors. (~2633 tok)
- `PreferNamespaceUsePlugin.php` — This plugin checks for FQSEN usages that could be simplified by leveraging an `use` already present in the (~1987 tok)
- `PregRegexCheckerPlugin.php` — This plugin checks for invalid regexes in calls to preg_match. (And all of the other internal PCRE functions). (~4082 tok)
- `PrintfCheckerPlugin.php` — This plugin checks for invalid format strings and invalid uses of format strings in printf and sprintf, etc. (~9574 tok)
- `README.md` — Project documentation (~10322 tok)
- `RedundantAssignmentPlugin.php` — This plugin checks for assignments where the variable already (~1538 tok)
- `RemoveDebugStatementPlugin.php` — This plugin checks for possible debugging statements. (~1591 tok)
- `ShortArrayPlugin.php` — Demo plugin to suggest using short array syntax. (~504 tok)
- `SimplifyExpressionPlugin.php` — This plugin checks for expressions that can be simplified based on the union types. (~1716 tok)
- `SleepCheckerPlugin.php` — This plugin checks uses of __sleep() (~2336 tok)
- `StaticVariableMisusePlugin.php` — NOTE: This is automatically loaded by phan. Do not include it in a config. (~614 tok)
- `StrictComparisonPlugin.php` — This plugin checks for uses of in_array where $strict is not true. (~1770 tok)
- `StrictLiteralComparisonPlugin.php` — This plugin warns about using `==`/`!=` for string literals. (~844 tok)
- `SuspiciousParamOrderPlugin.php` — A plugin that checks if calls to a function or method pass in arguments in a suspicious order. (~4476 tok)
- `UncoveredEnumCasesInMatchPlugin.php` — This plugin checks for non-exhaustive match expressions that could throw (~6544 tok)
- `UnknownClassElementAccessPlugin.php` — This plugin checks for accesses to unknown class elements that can't be type checked. (~1585 tok)
- `UnknownElementTypePlugin.php` — This file checks if any elements in the codebase have undeclared types. (~4583 tok)
- `UnreachableCodePlugin.php` — This file checks for syntactically unreachable statements in (~1024 tok)
- `UnsafeCodePlugin.php` — This plugin checks for occurrences of unsafe constructs such as shell_exec, eval(), etc. (~940 tok)
- `UnusedSuppressionPlugin.php` — Check for unused (at)suppress annotations. (~3283 tok)
- `UseReturnValuePlugin.php` (~94 tok)
- `WhitespacePlugin.php` — This plugin checks the whitespace in analyzed PHP files for (1) tabs, (2) windows newlines, and (3) trailing whitespace. (~877 tok)

## .phan/plugins/CaseMismatchPlugin/

- `Fixers.php` — Implements --automatic-fix for CaseMismatchPlugin. (~1612 tok)

## .phan/plugins/DeprecateAliasPlugin/

- `fixers.php` — Implements --automatic-fix for NotFullyQualifiedUsagePlugin (~729 tok)

## .phan/plugins/NotFullyQualifiedUsagePlugin/

- `fixers.php` — Implements --automatic-fix for NotFullyQualifiedUsagePlugin (~1282 tok)

## .phan/plugins/PHPDocRedundantPlugin/

- `Fixers.php` — This plugin implements --automatic-fix for PHPDocRedundantPlugin (~3638 tok)

## .phan/plugins/PHPDocToRealTypesPlugin/

- `Fixers.php` — This plugin implements --automatic-fix for PHPDocToRealTypesPlugin (~1325 tok)

## .phan/plugins/PreferNamespaceUsePlugin/

- `Fixers.php` — This plugin implements --automatic-fix for PreferNamespaceUsePlugin (~1441 tok)

## .phan/plugins/WhitespacePlugin/

- `fixers.php` — Fixers for --automatic-fix and WhitespacePlugin (~1263 tok)

## .phan/stubs/

- `AllowDynamicProperties.php` — Stub for PHP 8.1 (~44 tok)
- `mbstring.phan_php` — These stubs were generated by the phan stub generator. (~1278 tok)
- `README.md` — Project documentation (~35 tok)

## .phpunit.cache/

- `test-results` (~48989 tok)

## internal/

- `CLI-HELP.md` — Declares and (~4818 tok)
- `dump_fallback_ast.php` — The MIT License (MIT) (~1606 tok)
- `dump_html_styles.php` — A utility to dump the terminal color codes as HTML styles (~744 tok)
- `dump_vim_highlighting.php` (~317 tok)
- `emit_signature_map_for_php_version.php` — Emit the signature map for a given php version. (~374 tok)
- `extract_arg_info.php` — This extracts the real signature types for commonly used functions from opcache. (~3918 tok)
- `flatten_signature_map.php` — Temporary utility script to switch phan from the old flat style of signature map (~768 tok)
- `fuzz_test.php` — Utilities to fuzz test Phan when tokens are missing (~1148 tok)
- `internalsignatures.php` (~72 tok)
- `Issue-Types-Caught-by-Phan.md` — AccessError (~59065 tok)
- `Issue-Types-Caught-by-Phan.md.new` — Declares constant (~62998 tok)
- `line_deleter.php` — Utility to delete lines from a file. (~1283 tok)
- `make_phar` (~60 tok)
- `package.php` — Generates a phar file to be published with releases (~603 tok)
- `pdep_config.php` (~248 tok)
- `Phan-Config-Settings.md` — Configuring Files (~9473 tok)
- `Phan-Config-Settings.md.new` — Declares and (~10105 tok)
- `phpcbf` (~159 tok)
- `phpcs` (~158 tok)
- `README.md` — Project documentation (~298 tok)
- `reflection_completeness_check.php` — This checks that the function signatures are complete. (~1796 tok)
- `regenerate_test_folder_expect.sh` (~122 tok)
- `sanitycheck.php` — Loads the ReflectionFunction or ReflectionMethod for the given function or method name. (~4388 tok)
- `sort_signature_map.php` — Sort the functiton or delta signature maps provided as arguments on stdin (~408 tok)
- `suggest_functions_to_fully_qualify.php` — Print usage for suggest_functions_to_fully_qualify and exit. (~572 tok)

## internal/PHP_CodeSniffer/

- `composer.json` — PHP package manifest (~120 tok)

## internal/PHP_CodeSniffer/Sniffs/

- `ValidUnderscoreVariableNameSniff.php` — Checks the naming of variables and member variables. (~1909 tok)

## internal/lib/

- `IncompatibleRealStubsSignatureDetector.php` — This reads from a folder containing PHP stub files documenting internal extensions (e.g. those from php-src) (~7716 tok)
- `IncompatibleSignatureDetectorBase.php` — Implementations of this can be used to check Phan's function signature map. (~7031 tok)
- `IncompatibleStubsSignatureDetector.php` — This reads from a folder containing PHP stub files documenting internal extensions (e.g. those from PHPStorm) (~4984 tok)
- `IncompatibleXMLSignatureDetector.php` — A utility to read php.net's xml documentation for functions, methods, (~12576 tok)
- `NotFullyQualifiedReporterPlugin.php` — This warns if references to global functions or global constants are not fully qualified. (~1387 tok)

## internal/stubs/

- `ast.phan_php` — These stubs were generated by the phan stub generator. (~1677 tok)
- `bcmath.phan_php` — bcadd: bcsub, bcmul, bcdiv + 26 more (~791 tok)
- `ctype.phan_php` — These stubs were generated by the phan stub generator. (~125 tok)
- `igbinary.phan_php` — These stubs were generated by the phan stub generator. (~54 tok)
- `intl.phan_php` — INTL_MAX_LOCALE_LEN: intlcal_create_instance, intlcal_get_keyword_values_for_locale, intlcal_get_now + 87 more (~40162 tok)
- `ldap.phan_php` — Declares LDAP_DEREF_NEVER (~6233 tok)
- `mbstring.phan_php` — ifdef HAVE_MBREGEX (~2191 tok)
- `mysqli.phan_php` — These stubs were generated by the phan stub generator. (~4998 tok)
- `pcntl_php84.phan_php` — Declares WNOHANG (~4621 tok)
- `pcntl.phan_php` — ifdef WNOHANG (~4337 tok)
- `pdo_pgsql.phan_php` — Pgsql: escapeIdentifier, copyFromArray, copyFromFile + 8 more (~638 tok)
- `pgsql.phan_php` — PGSQL_LIBPQ_VERSION: pg_connect, pg_pconnect, pg_connect_poll + 17 more (~7327 tok)
- `phar.phan_php` — These stubs were generated by the phan stub generator. (~2272 tok)
- `posix.phan_php` — These stubs were generated by the phan stub generator. (~568 tok)
- `readline.phan_php` — These stubs were generated by the phan stub generator. (~201 tok)
- `README.md` — Project documentation (~248 tok)
- `rounding.phan_php` — @phan-file-suppress PhanPluginDuplicateUseNormal (~72 tok)
- `simplexml.phan_php` — These stubs were generated by the phan stub generator. (~433 tok)
- `soap.phan_php` — Url: use_soap_error_handler, is_soap_fault, __toString + 9 more (~3926 tok)
- `sockets.phan_php` — These stubs were generated by the phan stub generator. (~2464 tok)
- `spl_php81.phan_php` — Stub file for SPL with template support (~8419 tok)
- `spl.phan_php` — Stub file for SPL with template support (~8563 tok)
- `sqlite3.phan_php` — These stubs were generated by the phan stub generator. (~1037 tok)
- `standard_templates_php81.phan_php` — Stub file for standard library functions with template support (PHP 8.1-8.3) (~246 tok)
- `standard_templates_php84.phan_php` — Stub file for standard library functions with template support (PHP 8.4) functions with template support (~498 tok)
- `standard_templates.phan_php` — Stub file for standard library functions with template support (PHP 8.5+) (~654 tok)
- `sysvmsg.phan_php` — These stubs were generated by the phan stub generator. (~259 tok)
- `sysvsem.phan_php` — These stubs were generated by the phan stub generator. (~93 tok)
- `sysvshm.phan_php` — These stubs were generated by the phan stub generator. (~125 tok)
- `TEMPLATE_ANNOTATIONS.md` — Template Annotations Inventory - Phan Internal Stubs (~1187 tok)
- `tidy.phan_php` — Declares TIDY_NODETYPE_ROOT (~4707 tok)
- `url.phan_php` — UriComparisonMode: parse, getScheme, withScheme + 54 more (~1341 tok)
- `xdebug.phan_php` — These stubs were generated by the phan stub generator. (~699 tok)
- `xsl.phan_php` — XSL_CLONE_AUTO: importStylesheet, transformToDoc, transformToUri + 10 more (~819 tok)
- `zip.phan_php` — These stubs were generated by the phan stub generator. (~2087 tok)

## plugins/bash/

- `phan` — This is a bash completion script for Phan. (~1709 tok)

## plugins/codeclimate/

- `ast.ini` (~78 tok)
- `config-example.json` (~14 tok)
- `Dockerfile` — Docker container definition (~792 tok)
- `engine` — Declares and (~1188 tok)
- `Makefile` — Make build targets (~80 tok)
- `README.md` — Project documentation (~205 tok)

## plugins/vim/

- `phansnippet.vim` (~260 tok)

## plugins/vim/syntax/

- `php.vim` (~192 tok)
- `pylint.vim` (~1385 tok)

## plugins/zsh/

- `_phan` — compdef phan (~2732 tok)
- `README.md` — Project documentation (~220 tok)

## src/

- `codebase.php` (~170 tok)
- `phan.php` — Interface: and (0 methods) (~350 tok)
- `prep.php` — Interface: and (0 methods) (~275 tok)
- `requirements.php` (~218 tok)

## src/Phan/

- `Analysis.php` — is: declare(strict_types=1);, $file_path = 'internal';, getInternalStubCacheStats (~9612 tok)
- `BlockAnalysisVisitor.php` — Analyze blocks of code (~43934 tok)
- `Bootstrap.php` — Set up error handlers, exception handlers, autoloaders, etc. Check that all dependencies are met for running Phan or its utilities. (~4894 tok)
- `CLI.php` — Contains methods for parsing CLI arguments to Phan, (~36578 tok)
- `CLIBuilder.php` — Helper method to build instances of CLI. (~514 tok)
- `CodeBase.php` — A CodeBase represents the known state of a code base (~25591 tok)
- `Config.php` — Declares array_key_exists (~23496 tok)
- `Daemon.php` — A simple analyzing daemon that can be used by IDEs. (see `phan_client`) (~2988 tok)
- `Debug.php` — Debug utilities (~4553 tok)
- `filter_var.php_polyfill` — Implements only the subset of filter_var() used by Phan. (~1877 tok)
- `ForkPool.php` — Fork off to n-processes and divide up tasks between (~3495 tok)
- `Issue.php` — An issue emitted during analysis. (~75885 tok)
- `IssueFixSuggester.php` — Utilities to suggest fixes for emitted Issues (~8860 tok)
- `IssueInstance.php` — Represents an instance of an issue at a given file and line for the given template parameters. (~2209 tok)
- `Memoize.php` — A utility trait to memoize (cache) the result of instance methods and static methods. (~611 tok)
- `Ordering.php` — This determines the order in which files will be analyzed. (~1533 tok)
- `Phan.php` — This executes the parse, method/function, then the analysis phases. (~12995 tok)
- `PluginV3.php` — Plugins can be defined in the config and will have (~2277 tok)
- `Prep.php` — A utility that can be used to scan a list of files and apply a closure to every node. (~478 tok)
- `Profile.php` — Utility for profiling Phan runs. Used if the profiler_enabled config setting is true. (~823 tok)
- `README.md` — Project documentation (~1418 tok)
- `Suggestion.php` — This may be extended later to support the language server protocol (~457 tok)

## src/Phan/AST/

- `AnalysisVisitor.php` — A visitor used for analysis. (~871 tok)
- `ArrowFunc.php` — Utilities for computing uses of an ast\AST_ARROW_FUNC node. (~1096 tok)
- `ASTHasher.php` — This converts a PHP AST Node into a hash. (~476 tok)
- `ASTNormalizer.php` — Normalizes AST nodes to a consistent representation across different AST versions and parsers. (~880 tok)
- `ASTReverter.php` — This converts a PHP AST into an approximate string representation. (~6963 tok)
- `ASTSimplifier.php` — This simplifies a PHP AST into a form which is easier to analyze, (~13181 tok)
- `ContextNode.php` — Methods for an AST node in context (~32784 tok)
- `FallbackUnionTypeVisitor.php` — Determines the UnionType associated with a given node as a fallback, (~8233 tok)
- `InferPureAndNoThrowVisitor.php` — Used to check if a method is pure. (~1065 tok)
- `InferPureSnippetVisitor.php` — Used to check if a snippet in a method is pure. (~1520 tok)
- `InferPureVisitor.php` — Used to check if a method is pure. (~4720 tok)
- `InferPureVisitorTrait.php` — Contains methods to reduce boilerplate (~229 tok)
- `InferValue.php` — Utilities for inferring the value of operations in the analyzed code. (~1199 tok)
- `Parser.php` — Parser parses the passed in PHP code based on configuration settings. (~6647 tok)
- `PhanAnnotationAdder.php` — This adds annotations for Phan analysis to a given node, (~2384 tok)
- `PipeExpression.php` — Helper utilities for analyzing the PHP pipe operator expressions. (~519 tok)
- `ScopeImpactCheckingVisitor.php` — This checks if the expression/statement is likely to have an impact on inferences in the current scope. (~1149 tok)
- `UnionTypeVisitor.php` — Determines the UnionType associated with a given node. (~58452 tok)

## src/Phan/AST/TolerantASTConverter/

- `ast_shim.php` — Based on PHPDoc stub file for ast extension from (~2426 tok)
- `CachingTolerantASTConverter.php` — This is a plain ast\Node generator that adds caching of the PhpParser\Nodes. (~691 tok)
- `CompatibleParser.php` — Tokenizes content using PHP's built-in `token_get_all`, and converts to "lightweight" Token representation. (~286 tok)
- `CompatiblePhpTokenizer.php` — Like PhpTokenizer but supports the following: (~353 tok)
- `InvalidNodeException.php` — An exception thrown when TolerantASTConverter is processing something that would become an invalid Node. (~102 tok)
- `NodeDumper.php` — Source: https://github.com/TysonAndre/tolerant-php-parser-to-php-ast (~1712 tok)
- `NodeUtils.php` — Miscellaneous utilities for converting nodes to strings. (~498 tok)
- `ParseException.php` — An error in the polyfill PHP parser used for unparseable code. (~247 tok)
- `ParseResult.php` — All details about the results of parsing. (~173 tok)
- `PhpParserNodeEntry.php` — The Microsoft\PhpParser instance produced for a given file contents for the currently running php version's tokenizer. (~219 tok)
- `Shim.php` — Loads missing declarations (~1408 tok)
- `ShimFunctions.php` — Loads missing declarations (~1392 tok)
- `StringUtil.php` — This class is based on code from https://github.com/nikic/PHP-Parser/blob/master/lib/PhpParser/Node/Scalar/String_.php (~1876 tok)
- `TolerantASTConverter.php` — Source: https://github.com/TysonAndre/tolerant-php-parser-to-php-ast (~46334 tok)
- `TolerantASTConverterPreservingOriginal.php` — This is a subclass of TolerantASTConverter (~872 tok)
- `TolerantASTConverterTrait.php` — This is a trait to be used multiple times to account for https://wiki.php.net/rfc/static_variable_inheritance changing behavior in php 8.1 (~928 tok)
- `TolerantASTConverterWithNodeMapping.php` — This is a subclass of TolerantASTConverter (~5390 tok)

## src/Phan/AST/Visitor/

- `Element.php` — This contains functionality needed by various visitor implementations (~4803 tok)
- `FlagVisitor.php` — A visitor of AST nodes based on the node's flag value (~2569 tok)
- `FlagVisitorImplementation.php` — A visitor of AST nodes based on the node's flag value (~2208 tok)
- `KindVisitor.php` — A visitor of AST nodes based on the node's kind value (~3422 tok)
- `KindVisitorImplementation.php` — A visitor of AST nodes based on the node's kind value (~3128 tok)

## src/Phan/Analysis/

- `AbstractMethodAnalyzer.php` — This verifies that the inherited abstract methods are all implemented on non-abstract classes. (~684 tok)
- `Analyzable.php` — Objects implementing this trait store a handle to (~1249 tok)
- `ArgumentType.php` — This visitor analyzes arguments of calls to methods, functions, and closures (~23393 tok)
- `AssignmentVisitor.php` — Analyzes assignments. (~33276 tok)
- `AssignOperatorAnalysisVisitor.php` — This visitor determines the returned union type of an assignment operation. (~7883 tok)
- `AssignOperatorFlagVisitor.php` — This visitor returns a Context with the updated changes caused by an assignment operation (e.g. changes to Variables, Variable types) (~3600 tok)
- `AttributeAnalyzer.php` — Analyzer of the attributes of declarations. (~3629 tok)
- `BinaryOperatorFlagVisitor.php` — This implements Phan's analysis of the type of binary operators (Node->kind=ast\AST_BINARY_OP). (~11317 tok)
- `BlockExitStatusChecker.php` — This checks what exit statuses are possible for AST nodes: `break;`, `continue;`, `throw`, `return`, (~13814 tok)
- `ClassConstantTypesAnalyzer.php` — An analyzer that checks a class's properties for issues. (~3394 tok)
- `ClassInheritanceAnalyzer.php` — A checker for whether the given Clazz(class/trait/interface) properly inherits (~2550 tok)
- `CompositionAnalyzer.php` — This analyzer checks if the signatures of inherited properties match (~1680 tok)
- `ConditionVisitor.php` — A visitor that takes a Context and a Node for a condition and returns a Context that has been updated with that condition. (~16599 tok)
- `ConditionVisitorInterface.php` — This implements common functionality to update variables based on checks within a conditional (of an if/elseif/else/while/for/assert(), etc.) (~821 tok)
- `ConditionVisitorUtil.php` — This implements common functionality to update variables based on checks within a conditional (of an if/elseif/else/while/for/assert(), etc.) (~23406 tok)
- `ContextMergeVisitor.php` — This will merge inferred variable types from multiple contexts in branched control structures (~6127 tok)
- `ConvergenceWorklist.php` — Worklist-based incremental re-analysis for type convergence. (~2044 tok)
- `DuplicateClassAnalyzer.php` — Analyzer that checks for duplicate classes/traits/interfaces. (~936 tok)
- `DuplicateFunctionAnalyzer.php` — Checks to see if the given method is a duplicate of another method (~800 tok)
- `FallbackMethodTypesVisitor.php` — Conservatively determines types set for a variable anywhere in a function as a fallback. (~2035 tok)
- `GotoAnalyzer.php` — Analyzes uses of goto (~631 tok)
- `LoopConditionVisitor.php` — Used to avoid false positives analyzing loop conditions for redundant conditions. (~1046 tok)
- `NegatedConditionVisitor.php` — A visitor that takes a Context and a Node for a condition and returns a Context that has been updated with the negation of that condition. (~13827 tok)
- `ParameterTypesAnalyzer.php` — Analyzer of the parameters of function/closure/method signatures. (~17021 tok)
- `ParentConstructorCalledAnalyzer.php` — Analyzer that checks if the constructor of the given Clazz calls the parent constructor. (~486 tok)
- `PostOrderAnalysisVisitor.php` — PostOrderAnalysisVisitor is where we do the post-order part of the analysis (~57771 tok)
- `PreOrderAnalysisVisitor.php` — PreOrderAnalysisVisitor is where we do the pre-order part of the analysis (~8724 tok)
- `PropertyTypesAnalyzer.php` — An analyzer that checks a class's properties for issues. (~1059 tok)
- `ReachabilityChecker.php` — This checks if $inner is unconditionally reachable from the passed in node. (~2238 tok)
- `RedundantCondition.php` — Contains miscellaneous utilities for warning about redundant and impossible conditions (~2345 tok)
- `ReferenceCountsAnalyzer.php` — This emits PhanUnreferenced* issues for class-likes, constants, properties, and functions/methods. (~7007 tok)
- `RegexAnalyzer.php` — This infers the union type of $matches in preg_match, (~1250 tok)
- `ScopeVisitor.php` — An abstract visitor with methods to track elements in the current scope. (~2407 tok)
- `ThrowsTypesAnalyzer.php` — An analyzer that checks method phpdoc (at)throws types of function-likes to make sure they're valid (~1800 tok)

## src/Phan/Analysis/ConditionVisitor/

- `BinaryCondition.php` — This represents an assertion implementation acting on two sides of a condition (!=, ==, ===, etc) (~473 tok)
- `ComparisonCondition.php` — This represents a relative comparison assertion implementation acting on two sides of a condition (<, <=, >, >=) (~1163 tok)
- `EqualsCondition.php` — This represents an equals assertion implementation acting on two sides of a condition (==) (~1679 tok)
- `HasTypeCondition.php` — An expression with the side effect that the given node has type T (~730 tok)
- `IdenticalCondition.php` — This represents an identical assertion implementation acting on two sides of a condition (===) (~1534 tok)
- `NotEqualsCondition.php` — This represents a not equals assertion implementation acting on two sides of a condition (!=) (~446 tok)
- `NotHasTypeCondition.php` — An expression with the side effect that the given node does not have type T (~760 tok)
- `NotIdenticalCondition.php` — This represents a not identical assertion implementation acting on two sides of a condition (!==) (~446 tok)

## src/Phan/CodeBase/

- `ClassMap.php` — Maps for elements associated with an individual class (~882 tok)
- `UndoTracker.php` — UndoTracker maps a file path to a list of operations(e.g. Closures) that must be executed to (~2318 tok)

## src/Phan/Config/

- `InitializedSettings.php` — This class is used by `phan --init` (~246 tok)
- `Initializer.php` — This class is used by 'phan --init' to generate a phan config for a composer project. (~8415 tok)

## src/Phan/Daemon/

- `ExitException.php` — An exception thrown to indicate that the caller should exit() with the given error code. (~112 tok)
- `ParseRequest.php` — This is used to signal to Phan\AST\Parser that Phan is running in daemon mode, (~119 tok)
- `Request.php` — Represents the state of a client request to a daemon, and contains methods for sending formatted responses. (~8703 tok)

## src/Phan/Daemon/Transport/

- `CapturerResponder.php` — Instead of sending the data over a stream, (~372 tok)
- `Responder.php` — This is an interface abstracting the transport which the worker process uses to send a response. (~177 tok)
- `StreamResponder.php` — Sends json encoded data over a socket stream. (~833 tok)

## src/Phan/Debug/

- `Breakpoint.php` — A debugger used by the original Phan maintainers for debugging Phan. (~374 tok)
- `DebugUnionType.php` — Utility for debugging assignments to a given union type. (~321 tok)
- `Frame.php` — Debug utilities for working with frames of debug_backtrace() (~1368 tok)
- `SignalHandler.php` — Utilities for debugging why Phan or a plugin is hanging or taking longer than expected. (~479 tok)

## src/Phan/Exception/

- `CodeBaseException.php` — Thrown to indicate that retrieving the element for an FQSEN from the CodeBase failed. (~333 tok)
- `EmptyFQSENException.php` — Thrown to indicate that an empty FQSEN was used where a valid FQSEN was expected. (~55 tok)
- `FQSENException.php` — Thrown to indicate that an empty/invalid FQSEN was used where a valid FQSEN was expected. (~233 tok)
- `InvalidFQSENException.php` — Thrown to indicate that an invalid FQSEN was used where a valid FQSEN was expected. (~56 tok)
- `IssueException.php` — # Example Usage (~359 tok)
- `NodeException.php` — Thrown to indicate that the given Node could not be analyzed. (~264 tok)
- `RecursionDepthException.php` — Thrown to indicate that recursion exceeded the limits of what Phan supports (~62 tok)
- `UnanalyzableException.php` — Thrown when Phan unexpectedly fails to analyze a given Node and cannot proceed. (~55 tok)
- `UnanalyzableMagicPropertyException.php` — Thrown when Phan unexpectedly fails to analyze a given magic property and cannot proceed. (~265 tok)
- `UsageException.php` — Thrown to indicate that retrieving the element for an FQSEN from the CodeBase failed. (~311 tok)

## src/Phan/ForkPool/

- `Progress.php` — Represents the current progress of a forked analysis worker. (~303 tok)
- `Reader.php` — This reads messages from a forked worker. (~1264 tok)
- `Writer.php` — This writes messages from a forked worker. (~554 tok)

## src/Phan/Internal/

- `InternalStubCacheEntry.php` — InternalStubCacheEntry: Checks whether the cached entry matches the provid, Replays cached definitions into the provided code (~826 tok)

## src/Phan/Language/

- `AnnotatedUnionType.php` — This is used to represent a union type that has various annotations. (~2268 tok)
- `Context.php` — An object representing the context in which any (~13338 tok)
- `ElementContext.php` — A context referring to an element that hasn't been created yet. (~328 tok)
- `EmptyUnionType.php` — NOTE: there may also be instances of UnionType that are empty, due to the constructor being public (~11298 tok)
- `FileRef.php` — An object representing the context in which any (~1833 tok)
- `FQSEN.php` — A Fully-Qualified Structural Element Name (~408 tok)
- `FutureUnionType.php` — A FutureUnionType is a UnionType that is lazily loaded. (~626 tok)
- `NamespaceMapEntry.php` — Tracks a `use Foo\Bar;` statement inside of a namespace. (~653 tok)
- `Scope.php` — Represents the scope of a Context. (~4147 tok)
- `Type.php` — The base class for all of Phan's types. (~44049 tok)
- `TypePart.php` — A part of a type extracted from phpdoc (~264 tok)
- `UnionType.php` — Phan's internal representation of union types, and methods for working with union types. (~61916 tok)
- `UnionTypeBuilder.php` — Utilities to build a union type. (~580 tok)

## src/Phan/Language/Element/

- `AddressableElement.php` — An addressable element is a TypedElement with an FQSEN. (~2846 tok)
- `AddressableElementInterface.php` — An AddressableElementInterface is a TypedElementInterface with an FQSEN. (~1118 tok)
- `Attribute.php` — Represents the information Phan has about a declaration's attribute (~1693 tok)
- `ClassAliasRecord.php` — A ClassAliasRecord represents the information Phan parsed from calls (~246 tok)
- `ClassConstant.php` — ClassConstant represents the information Phan has (~2324 tok)
- `ClassElement.php` — ClassElement is a base class of an element belonging to a class/trait/interface (~2909 tok)
- `Clazz.php` — Clazz represents the information Phan knows about a class, trait, or interface, (~49631 tok)
- `ClosedScopeElement.php` — A trait for closed scope elements (classes, functions, methods, (~200 tok)
- `Comment.php` — Handles extracting information(param types, return types, magic methods/properties, etc.) from phpdoc comments. (~7240 tok)
- `ConstantInterface.php` — Represents APIs used when Phan is setting up/analyzing (~323 tok)
- `ConstantTrait.php` — Represents functionality common to GlobalConstant and ClassConstant (~556 tok)
- `ElementFutureUnionType.php` — Implements functionality of an element with a union type that is evaluated lazily. (~612 tok)
- `ElementProxyTrait.php` — Trait for classes that wrap an element and proxy (~488 tok)
- `EnumCase.php` — EnumCase represents the information Phan has (~571 tok)
- `Flags.php` — Flags contains bit flags that Phan adds to elements (~1354 tok)
- `Func.php` — Phan's representation of a closure or global function. (~4656 tok)
- `FunctionFactory.php` — This returns internal function declarations for a given function/method FQSEN, (~3574 tok)
- `FunctionInterface.php` — Interface defining the behavior of both Methods and Functions (~4906 tok)
- `FunctionTrait.php` — This contains functionality common to global functions, closures, and methods (~24916 tok) Also withoutTypesNotCastableToSignatureType (shared signature-compatibility filter used by Method::computeNewTypeForComment).
- `GlobalConstant.php` — Phan's representation of a global constant (~1733 tok)
- `GlobalVariable.php` — This class represents a global variable in a local scope, allowing to partially (~206 tok)
- `HasAttributesTrait.php` — This contains functionality common to declarations that have attributes (~796 tok)
- `MarkupDescription.php` — APIs for generating markup (markdown) description of elements (~5596 tok)
- `Method.php` — Phan's representation of a class's method. (~12995 tok)
- `Parameter.php` — Represents the information Phan has about a function-like's Parameter (~9844 tok)
- `PassByReferenceVariable.php` — This class wraps a parameter and an element and proxies (~1355 tok)
- `Property.php` — Phan's representation of a class/trait/interface's property (including magic and dynamic properties) (~6392 tok)
- `PropertyHook.php` — Phan's representation of a property hook (get or set) (~1340 tok)
- `TraitAdaptations.php` — This contains info for a single sub-node of a node of type \ast\AST_USE_TRAIT (~277 tok)
- `TraitAliasSource.php` — This contains info for the source method of a trait alias. (~387 tok)
- `TypedElement.php` — Any PHP structural element that also has a type and is (~2272 tok)
- `TypedElementInterface.php` — Any PHP structural element that also has a type and is (~230 tok)
- `UnaddressableTypedElement.php` — Any PHP structural element that also has a type and is (~1761 tok)
- `Variable.php` — Phan's representation of a Variable, as well as methods for accessing and modifying variables. (~2792 tok)
- `VariadicParameter.php` — Contains Phan's representation of a variadic parameter of a method declaration, and methods to access/modify/use the variadic parameters. (~1348 tok)

## src/Phan/Language/Element/Comment/

- `Assertion.php` — Represents an assertion on a parameter type. (~239 tok)
- `Builder.php` — This constructs comments from doc comments (or other comment types). (~19422 tok)
- `ConditionalReturnType.php` — An immutable representation of a PHPStan/Psalm-style conditional return type, e.g. (~2243 tok)
- `Method.php` — Phan's representation of a magic method (~1074 tok)
- `NullComment.php` — A comment for an empty doc-block or when comment parsing is disabled (~263 tok)
- `Parameter.php` — Stores information Phan knows about the PHPDoc parameter of a given function-like. (~1774 tok)
- `Property.php` — Represents information about a given (at)property annotation in a PHPDoc comment (~518 tok)
- `ConditionalReturnType.php` — Immutable PHPStan-style conditional return type `($x is [not] T ? A : B)`: flatten, mapTypes, withRenamedParams, resolve (~2200 tok)
- `ReturnComment.php` — Represents the (at)return annotation of a doc comment. (now also carries ?ConditionalReturnType; withType() preserves it) (~700 tok)

## src/Phan/Language/FQSEN/

- `AbstractFQSEN.php` — A Fully-Qualified Name (~996 tok)
- `Alternatives.php` — This trait allows an FQSEN to have an alternate ID for when (~534 tok)
- `FullyQualifiedClassConstantName.php` — A Fully-Qualified Class Constant Name (~171 tok)
- `FullyQualifiedClassElement.php` — A Fully-Qualified Class Name (~2087 tok)
- `FullyQualifiedClassName.php` — A Fully-Qualified Class Name (~796 tok)
- `FullyQualifiedConstantName.php` — A Fully-Qualified Constant Name (~51 tok)
- `FullyQualifiedFunctionLikeName.php` — A Fully-Qualified Function or Method Name (~146 tok)
- `FullyQualifiedFunctionName.php` — A Fully-Qualified Function Name (~949 tok)
- `FullyQualifiedGlobalConstantName.php` — A Fully-Qualified Constant Name (~233 tok)
- `FullyQualifiedGlobalStructuralElement.php` — A Fully-Qualified Global Structural Element (~4730 tok)
- `FullyQualifiedMethodName.php` — A Fully-Qualified Method Name (~858 tok)
- `FullyQualifiedPropertyName.php` — A Fully-Qualified Property Name (~48 tok)

## src/Phan/Language/Internal/

- `ClassDocumentationMap.php` — This contains descriptions used by Phan for hover text of internal classes and interfaces (~51103 tok)
- `ConstantDocumentationMap.php` — This contains descriptions used by Phan for hover text of internal constants (global and class constants) in the language server mode. (~125446 tok)
- `DynamicPropertyMap.php` — A list of classes that support dynamic properties. These have (~67 tok)
- `FunctionSignatureMap_php82_delta.php` (~1561 tok)
- `FunctionSignatureMap_php83_delta.php` (~2327 tok)
- `FunctionSignatureMap_php84_delta.php` (~1360 tok)
- `FunctionSignatureMap_php85_delta.php` (~986 tok)
- `FunctionSignatureMap.php` — Format (~351033 tok)
- `FunctionSignatureMapReal_php81.php` — This lists all of the possible real return types of various global functions. (~33226 tok)
- `FunctionSignatureMapReal_php82.php` — This lists all of the possible real return types of various global functions. (~34165 tok)
- `FunctionSignatureMapReal_php83.php` — This lists all of the possible real return types of various global functions. (~35901 tok)
- `FunctionSignatureMapReal.php` — This lists all of the possible real return types of various global functions. (~45077 tok)
- `PropertyDocumentationMap.php` — This contains descriptions used by Phan for hover text of internal properties in the language server mode. (~15096 tok)
- `PropertyMap.php` — A mapping from class name to property name to property type. (~3725 tok)

## src/Phan/Language/Scope/

- `BranchScope.php` — A branch scope represents a scope created by branching off of the current scope (~802 tok)
- `ClassConstantScope.php` — Represents the Scope of the Context of a class's constant declaration. (~529 tok)
- `ClassScope.php` — Phan's representation of the scope within a class declaration. (~563 tok)
- `ClosedScope.php` — ClosedScope represents a scope that does not inherit variables from the parent scope (~176 tok)
- `ClosureScope.php` — Represents the Scope of a closure declaration, used by a Closure's Context. (~687 tok)
- `FunctionLikeScope.php` — The scope of a function, method, or closure. (~590 tok)
- `GlobalScope.php` — Represents the global scope (and stores global variables) (~2777 tok)
- `PropertyScope.php` — Represents the Scope of the Context of a class's property declaration. (~503 tok)
- `TemplateScope.php` — A scope that adds (at)template annotations to the current outer scope. (~217 tok)

## src/Phan/Language/Template/

- `TemplateVarianceUtil.php` — Utility helpers for tracking template variance usages within complex type structures. (~1246 tok)

## src/Phan/Language/Type/

- `ArrayKeyType.php` — The base class for various array-key types IntType, StringType (~238 tok)
- `ArrayShapeType.php` — This is generated from phpdoc such as array{field:int} (~12230 tok)
- `ArrayType.php` — Phan's representation of the type for `array`. (~5618 tok)
- `AssociativeArrayType.php` — Phan's representation for types such as `associative-array<MyClass>` and `associative-array<int, MyClass>` (~1035 tok)
- `BoolType.php` — Phan's representation of the type for `bool`. (~898 tok)
- `CallableArrayType.php` — Phan's representation of the type for `callable-array`. (~716 tok)
- `CallableDeclarationType.php` — Phan's representation for types such as `callable(MyClass):MyOtherClass` (~512 tok)
- `CallableInterface.php` — This is generated from phpdoc such as callable-string, callable, callable(int):void, etc. (~68 tok)
- `CallableObjectType.php` — Represents the type `callable-object` (an instance of an unspecified callable class) (~880 tok)
- `CallableStringType.php` — Phan's representation for `callable-string` (~1139 tok)
- `CallableType.php` — Phan's representation for `callable` (~778 tok)
- `ClassStringType.php` — A type representing a string with an unknown value that is a fully qualified class name. (~1609 tok)
- `ClosureDeclarationParameter.php` — Not a type, but used by ClosureDeclarationType (~1739 tok)
- `ClosureDeclarationType.php` — Phan's representation for annotations such as `Closure(MyClass):MyOtherClass` (~825 tok)
- `ClosureType.php` — Phan's representation of `Closure` and of closures associated with a given function-like's FQSEN (~2109 tok)
- `FalseType.php` — Phan's representation of PHPDoc `false` (~907 tok)
- `FloatType.php` — Phan's representation of the type for `float` (~483 tok)
- `FunctionLikeDeclarationType.php` — Phan's base class for representations of `callable(MyClass):MyOtherClass` and `Closure(MyClass):MyOtherClass` (~8776 tok)
- `GenericArrayInterface.php` — This is generated from phpdoc such as array<string,mixed>, array{field:int}, etc. (~219 tok)
- `GenericArrayTemplateKeyType.php` — A generic array type with a template as the key (~961 tok)
- `GenericArrayType.php` — Phan's representation for the types `array<string,MyClass>` and `MyClass[]` (~8541 tok)
- `GenericIterableType.php` — Phan's representation of the type `iterable<KeyType,ValueType>` (~2967 tok)
- `GenericMultiArrayType.php` — A temporary representation of `array<KeyType, T1|T2...>` (~2524 tok)
- `GenericMultiType.php` — A temporary representation of `array<KeyType, T1|T2...>` (~1124 tok)
- `IntersectionType.php` — Represents the intersection of two or more object types (~8787 tok)
- `IntRangeType.php` — Represents the utility type `int-range<min,max>`. (~1724 tok)
- `IntType.php` — Phan's representation of `int` (~549 tok)
- `IterableType.php` — Phan's representation of `iterable` (~884 tok)
- `KeyOfType.php` — Represents the utility type `key-of<T>` which resolves to the union of possible keys of `T`. (~2208 tok)
- `ListType.php` — Phan's representation for types such as `list` and `list<MyClass>` (~860 tok)
- `LiteralFloatType.php` — Phan's representation of the type for a specific float, e.g. `-1.2` (~2488 tok)
- `LiteralIntType.php` — Phan's representation of the type for a specific integer, e.g. `-1` (~2657 tok)
- `LiteralStringType.php` — Phan's representation of the type for a specific string, e.g. `'a string'` (~4693 tok)
- `LiteralTypeInterface.php` — Empty interface used by quick checks if a Type is a specific literal int/string. (~89 tok)
- `MixedType.php` — Represents the PHPDoc `mixed` type, which can cast to/from any type (~1677 tok)
- `MultiType.php` — Callers should split this up into multiple Type instances. (~99 tok)
- `NativeType.php` — Phan's base class for native types such as IntType, ObjectType, etc. (~3610 tok)
- `NativeTypeTrait.php` — Phan's base class for native types such as IntType, ObjectType, etc. (~594 tok)
- `NegativeIntType.php` — Represents the phpdoc utility type `negative-int`. (~520 tok)
- `NeverType.php` — Represents the return type `never` in phpdoc signatures (and in php 8.1 in https://wiki.php.net/rfc/never_type) (~1418 tok)
- `NonEmptyArrayInterface.php` — Common functionality for types such as `non-empty-list` and `non-empty-array` (~93 tok)
- `NonEmptyAssociativeArrayType.php` — Phan's representation for types such as `non-empty-associative-array` and `non-empty-associative-array<string, MyClass>` (~895 tok)
- `NonEmptyGenericArrayType.php` — Phan's representation for types such as `non-empty-array` and `non-empty-array<string,MyClass>` (~868 tok)
- `NonEmptyListType.php` — Phan's representation for types such as `non-empty-list` and `non-empty-list<MyClass>` (~821 tok)
- `NonEmptyMixedType.php` — Represents the PHPDoc `non-empty-mixed` type, which can cast to/from any non-empty type and is truthy. (~1030 tok)
- `NonEmptyStringType.php` — Phan's representation of the type for `non-empty-string`. (~1384 tok)
- `NonFalsyStringType.php` — Phan's representation of the type for `non-falsy-string` (a truthy string). (~1356 tok)
- `NonNullMixedType.php` — Represents the PHPDoc `non-empty-mixed` type, which can cast to/from any non-null type and is non-null (~1008 tok)
- `NonZeroIntType.php` — Phan's representation of a non-zero-int. (~1389 tok)
- `NullType.php` — Singleton representing the type `null` (~1826 tok)
- `ObjectType.php` — Represents the type `object` (an instance of an unspecified class) (~722 tok)
- `PositiveIntType.php` — Represents the phpdoc utility type `positive-int`. (~520 tok)
- `ResourceType.php` — Represents the type `resource` (~246 tok)
- `ScalarRawType.php` — A temporary representation of the type `scalar`. (~330 tok)
- `ScalarType.php` — The base class for various scalar types (BoolType, StringType, ScalarRawType, (~1198 tok)
- `SelfType.php` — Represents the PHPDoc type `self`. (~1203 tok)
- `StaticOrSelfType.php` — Represents the PHPDoc type `self` or `static`. (~134 tok)
- `StaticType.php` — Represents the PHPDoc type `static`. (~1288 tok)
- `StdClassShapeType.php` — Represents a stdClass with a set of known property shape constraints. (~2440 tok)
- `StringType.php` — Represents the type `string`. (~988 tok)
- `TemplateType.php` — Represents a template type that has not yet been resolved. (~5039 tok)
- `TrueType.php` — Represents the type `true` (~791 tok)
- `ValueOfType.php` — Represents the utility type `value-of<T>` which resolves to the union of possible values of `T`. (~2419 tok)
- `VoidType.php` — Represents the return type `void` (~2032 tok)

## src/Phan/LanguageServer/

- `CachedHoverResponse.php` — Caches the data for a hover response and information to check if the request was equivalent. (~403 tok)
- `ClientHandler.php` — Used to send notifications and requests to the language server client of the Phan Language Server. (~920 tok)
- `CompletionRequest.php` — Represents the Language Server Protocol's "Completion" request for an element (~2006 tok)
- `CompletionResolver.php` — This implements closures for finding completions for valid/invalid nodes where isSelected is set (~5726 tok)
- `DefinitionResolver.php` — This implements closures for finding definitions for nodes where isSelected is set (~4939 tok)
- `FileMapping.php` — A class to keep track of overrides by language server clients with open files. (~660 tok)
- `GoToDefinitionRequest.php` — Represents the Language Server Protocol's "Go to Definition" or "Go to Type Definition" or "Hover" request for a usage of an Element (~4147 tok)
- `IdGenerator.php` — Generates unique, incremental IDs for use as request IDs (~143 tok)
- `LanguageClient.php` — Source: https://github.com/felixfbecker/php-language-server/tree/master/src/LanguageClient.php (~158 tok)
- `LanguageServer.php` — Based on https://github.com/felixfbecker/php-language-server/blob/master/bin/php-language-server.php (~12697 tok)
- `Logger.php` — A logger used by Phan for developing or debugging the language server. (~808 tok)
- `NodeInfoRequest.php` — Represents the Language Server Protocol's request for information about a location of a file (~649 tok)

## src/Phan/LanguageServer/Client/

- `TextDocument.php` — Provides method handlers for all textDocument/* methods (~320 tok)

## src/Phan/Parse/

- `ParseVisitor.php` — The class is a visitor for AST nodes that does parsing. Each (~25429 tok)

## src/Phan/Plugin/Internal/

- `DependentReturnTypeOverridePlugin.php` — NOTE: This is automatically loaded by phan. Do not include it in a config. (~6539 tok)

## src/Phan/bootstrap/

- `polyfills.php` (~140 tok)

## tests/Phan/Language/

- `UnionTypeTest.php` — Unit tests of the many methods of UnionType (~11698 tok)

## tests/docker/

- `Dockerfile` — Docker container definition (~151 tok)

## tests/files/expected/

- `4884_try_possibly_undefined.php.expected` (~80 tok)
- `5433_template_array_bounds.php.expected` — Declares T (~294 tok)
- `5921_try_catch_finally_possibly_undefined.php.expected` (~22 tok)
- `5927_spaces_in_type_union.php.expected` (~0 tok)
- `array_diff_single_argument.php.expected` — expected warnings for #5566 (~350 tok)
- `array_is_list_negation.php.expected` — @phan-debug-var output for #5565 (~600 tok)

## tests/files/src/

- `4884_try_possibly_undefined.php` — Test fixtures covering issue #4884. (~671 tok)
- `5531_readonly_coalesce_assign.php` — Non-nullable readonly property: ??= is always safe. (~463 tok)
- `5921_try_catch_finally_possibly_undefined.php` — Tests for false positive PhanPossiblyUndeclaredVariable with try-catch-finally. (~443 tok)
- `5927_spaces_in_type_union.php` — Test that spaces around | and & in union types are accepted inside array shapes and generics. (~227 tok)
- `5928_template_bound_cast.php` — Regression test for https://github.com/phan/phan/issues/5522 (~394 tok)
- `5929_never_calls_never.php` — Regression test for https://github.com/phan/phan/issues/5535 (~399 tok)
- `5931_property_override_inherit_type.php` — Regression test for https://github.com/phan/phan/issues/5544 (~473 tok)
- `5932_never_instance_method_unreachable.php` — Fatal5932: neverReturns, neverReturns, method, neverReturns + 5 more (~834 tok)
- `5933_intersection_is_callable.php` — Regression test for https://github.com/phan/phan/issues/5555 (~447 tok)
- `5934_generic_typed_property.php` — Regression test for https://github.com/phan/phan/issues/5556 (~692 tok)
- `5935_class_string_static_call.php` — Regression test for https://github.com/phan/phan/issues/5558 (~2330 tok)
- `array_diff_single_argument.php` — Regression test for https://github.com/phan/phan/issues/5566: PHP 8 one-array calls to array_diff/array_intersect family (~650 tok)
- `array_is_list_negation.php` — Regression test for https://github.com/phan/phan/issues/5565: `!array_is_list($x)` type inference (~700 tok)
- `substr_replace_dependent_return.php` — Regression test for https://github.com/phan/phan/issues/5551 (~247 tok)

## tests/php83_files/src/

- `002_typed_class_constants.php` — Test PHP 8.3+ typed class constants (~710 tok)

## tests/plugin_test/expected/

- `217_noreturn_control_flow_extra.php.expected` — Declares of (~179 tok)
- `322_class_string_strict_method_checking.php.expected` (~312 tok)

## tests/plugin_test/src/

- `321_noreturn_method_instance_variable.php` — Regression test for issue #5553: a never-returning instance method called on a (~114 tok)
- `322_class_string_strict_method_checking.php` — Regression test for https://github.com/phan/phan/issues/5558 (~314 tok)
