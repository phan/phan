<?php declare(strict_types=1);
namespace Phan;

/**
 * Program configuration.
 *
 * Many of the settings in this class can be overridden in .phan/config.php.
 *
 * Some configuration can be overridden on the command line.
 * See `./phan -h` for command line usage, or take a
 * look at \Phan\CLI.php for more details on CLI usage.
 */
class Config
{
    /**
     * The version of the AST (defined in php-ast) that we're using.
     * Other versions are likely to have edge cases we no longer support,
     * and version 50 got rid of Decl.
     */
    const AST_VERSION = 50;

    /**
     * The version of the Phan plugin system.
     * Plugin files that wish to be backwards compatible may check this and
     * return different classes based on its existence and
     * the results of version_compare.
     * PluginV2 will correspond to 2.x.y, PluginV3 will correspond to 3.x.y, etc.
     * New features increment minor versions, and bug fixes increment patch versions.
     * @suppress PhanUnreferencedPublicClassConstant
     */
    const PHAN_PLUGIN_VERSION = '2.2.0';

    /**
     * @var string|null
     * The root directory of the project. This is used to
     * store canonical path names and find project resources
     */
    private static $project_root_directory = null;

    /**
     * Configuration options
     */
    private static $configuration = self::DEFAULT_CONFIGURATION;

    // The 6 most commonly accessed configs:
    /** @var bool */
    private static $null_casts_as_any_type = false;

    /** @var bool */
    private static $null_casts_as_array = false;

    /** @var bool */
    private static $array_casts_as_null = false;

    /** @var bool */
    private static $track_references = false;

    /** @var bool */
    private static $backward_compatibility_checks = false;

    /** @var bool */
    private static $quick_mode = false;
    // End of the 4 most commonly accessed configs.

    const DEFAULT_CONFIGURATION = [
        // A list of individual files to include in analysis
        // with a path relative to the root directory of the
        // project
        'file_list' => [],

        // A list of directories that should be parsed for class and
        // method information. After excluding the directories
        // defined in exclude_analysis_directory_list, the remaining
        // files will be statically analyzed for errors.
        //
        // Thus, both first-party and third-party code being used by
        // your application should be included in this list.
        'directory_list' => [],

        // List of case-insensitive file extensions supported by Phan.
        // (e.g. php, html, htm)
        'analyzed_file_extensions' => ['php'],

        // A regular expression to match files to be excluded
        // from parsing and analysis and will not be read at all.
        //
        // This is useful for excluding groups of test or example
        // directories/files, unanalyzable files, or files that
        // can't be removed for whatever reason.
        // (e.g. '@Test\.php$@', or '@vendor/.*/(tests|Tests)/@')
        'exclude_file_regex' => '',

        // A file list that defines files that will be excluded
        // from parsing and analysis and will not be read at all.
        //
        // This is useful for excluding hopelessly unanalyzable
        // files that can't be removed for whatever reason.
        'exclude_file_list' => [],

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
        //       to `excluce_analysis_directory_list`.
        'exclude_analysis_directory_list' => [],

        // A file list that defines files that will be included
        // in static analysis, to the exclusion of others.
        'include_analysis_file_list' => [],

        // Backwards Compatibility Checking. This is slow
        // and expensive, but you should consider running
        // it before upgrading your version of PHP to a
        // new version that has backward compatibility
        // breaks.
        'backward_compatibility_checks' => true,

        // A set of fully qualified class-names for which
        // a call to parent::__construct() is required.
        'parent_constructor_required' => [],

        // If true, this run a quick version of checks that takes less
        // time at the cost of not running as thorough
        // an analysis. You should consider setting this
        // to true only when you wish you had more **undiagnosed** issues
        // to fix in your code base.
        //
        // In quick-mode the scanner doesn't rescan a function
        // or a method's code block every time a call is seen.
        // This means that the problem here won't be detected:
        //
        // ```php
        // <?php
        // function test($arg):int {
        //     return $arg;
        // }
        // test("abc");
        // ```
        //
        // This would normally generate:
        //
        // ```sh
        // test.php:3 TypeError return string but `test()` is declared to return int
        // ```
        //
        // The initial scan of the function's code block has no
        // type information for `$arg`. It isn't until we see
        // the call and rescan test()'s code block that we can
        // detect that it is actually returning the passed in
        // `string` instead of an `int` as declared.
        'quick_mode' => false,

        // If enabled, check all methods that override a
        // parent method to make sure its signature is
        // compatible with the parent's. This check
        // can add quite a bit of time to the analysis.
        // This will also check if final methods are overridden, etc.
        'analyze_signature_compatibility' => true,

        // If enabled, inherit any missing phpdoc for types from
        // the parent method if none is provided.
        //
        // NOTE: This step will only be performed if analyze_signature_compatibility is also enabled.
        'inherit_phpdoc_types' => true,

        // The minimum severity level to report on. This can be
        // set to Issue::SEVERITY_LOW, Issue::SEVERITY_NORMAL or
        // Issue::SEVERITY_CRITICAL. Setting it to only
        // critical issues is a good place to start on a big
        // sloppy mature code base.
        'minimum_severity' => Issue::SEVERITY_LOW,

        // If true, missing properties will be created when
        // they are first seen. If false, we'll report an
        // error message if there is an attempt to write
        // to a class property that wasn't explicitly
        // defined.
        'allow_missing_properties' => false,

        // Allow null to be cast as any array-like type
        // This is an incremental step in migrating away from null_casts_as_any_type.
        // If null_casts_as_any_type is true, this has no effect.
        'null_casts_as_array' => false,

        // Allow any array-like type to be cast to null.
        // This is an incremental step in migrating away from null_casts_as_any_type.
        // If null_casts_as_any_type is true, this has no effect.
        'array_casts_as_null' => false,

        // Allow null to be cast as any type and for any
        // type to be cast to null. Setting this to false
        // will cut down on false positives.
        'null_casts_as_any_type' => false,

        // If enabled, scalars (int, float, bool, string, null)
        // are treated as if they can cast to each other.
        'scalar_implicit_cast' => false,

        // If this has entries, scalars (int, float, bool, string, null)
        // are allowed to perform the casts listed.
        // E.g. ['int' => ['float', 'string'], 'float' => ['int'], 'string' => ['int'], 'null' => ['string']]
        // allows casting null to a string, but not vice versa.
        // (subset of scalar_implicit_cast)
        'scalar_implicit_partial' => [],

        // If true, seemingly undeclared variables in the global
        // scope will be ignored. This is useful for projects
        // with complicated cross-file globals that you have no
        // hope of fixing.
        'ignore_undeclared_variables_in_global_scope' => false,

        // If true, check to make sure the return type declared
        // in the doc-block (if any) matches the return type
        // declared in the method signature.
        'check_docblock_signature_return_type_match' => true,

        // If true, check to make sure the param types declared
        // in the doc-block (if any) matches the param types
        // declared in the method signature.
        'check_docblock_signature_param_type_match' => true,

        // (*Requires check_docblock_signature_param_type_match to be true*)
        // If true, make narrowed types from phpdoc params override
        // the real types from the signature, when real types exist.
        // (E.g. allows specifying desired lists of subclasses,
        //  or to indicate a preference for non-nullable types over nullable types)
        // Affects analysis of the body of the method and the param types passed in by callers.
        'prefer_narrowed_phpdoc_param_type' => true,

        // (*Requires check_docblock_signature_return_type_match to be true*)
        // If true, make narrowed types from phpdoc returns override
        // the real types from the signature, when real types exist.
        // (E.g. allows specifying desired lists of subclasses,
        //  or to indicate a preference for non-nullable types over nullable types)
        // Affects analysis of return statements in the body of the method and the return types passed in by callers.
        'prefer_narrowed_phpdoc_return_type' => true,

        // Set to true in order to attempt to detect dead
        // (unreferenced) code. Keep in mind that the
        // results will only be a guess given that classes,
        // properties, constants and methods can be referenced
        // as variables (like `$class->$property` or
        // `$class->$method()`) in ways that we're unable
        // to make sense of.
        'dead_code_detection' => false,

        // Set to true in order to force tracking references to elements
        // (functions/methods/consts/protected).
        // dead_code_detection is another option which also causes references
        // to be tracked.
        'force_tracking_references' => false,

        // If true, the dead code detection rig will
        // prefer false negatives (not report dead code) to
        // false positives (report dead code that is not
        // actually dead) which is to say that the graph of
        // references will create too many edges rather than
        // too few edges when guesses have to be made about
        // what references what.
        'dead_code_detection_prefer_false_negative' => true,

        // If true, then before analysis, try to simplify AST into a form
        // which improves Phan's type inference in edge cases.
        //
        // This may conflict with 'dead_code_detection'.
        // When this is true, this slows down analysis slightly.
        //
        // E.g. rewrites `if ($a = value() && $a > 0) {...}`
        // into $a = value(); if ($a) { if ($a > 0) {...}}`
        'simplify_ast' => true,

        // If true, Phan will read `class_alias` calls in the global scope,
        // then (1) create aliases from the *parsed* files if no class definition was found,
        // and (2) emit issues in the global scope if the source or target class is invalid.
        // (If there are multiple possible valid original classes for an aliased class name,
        //  the one which will be created is unspecified.)
        // NOTE: THIS IS EXPERIMENTAL, and the implementation may change.
        'enable_class_alias_support' => false,

        // If disabled, Phan will not read docblock type
        // annotation comments for @property.
        // @property-read and @property-write are treated exactly the
        // same as @property for now.
        // Note: read_type_annotations must also be enabled.
        'read_magic_property_annotations' => true,

        // If disabled, Phan will not read docblock type
        // annotation comments for @method.
        // Note: read_type_annotations must also be enabled.
        'read_magic_method_annotations' => true,

        // If disabled, Phan will not read docblock type
        // annotation comments (such as for @return, @param,
        // @var, @suppress, @deprecated) and only rely on
        // types expressed in code.
        'read_type_annotations' => true,

        // This setting maps case insensitive strings to union types.
        // This is useful if a project uses phpdoc that differs from the phpdoc2 standard.
        // If the corresponding value is the empty string, Phan will ignore that union type (E.g. can ignore 'the' in `@return the value`)
        // If the corresponding value is not empty, Phan will act as though it saw the corresponding unionTypes(s) when the keys show up in a UnionType of @param, @return, @var, @property, etc.
        //
        // This matches the **entire string**, not parts of the string.
        // (E.g. `@return the|null` will still look for a class with the name `the`, but `@return the` will be ignored with the below setting)
        //
        // (These are not aliases, this setting is ignored outside of doc comments).
        // (Phan does not check if classes with these names exist)
        //
        // Example setting: ['unknown' => '', 'number' => 'int|float', 'char' => 'string', 'long' => 'int', 'the' => '']
        'phpdoc_type_mapping' => [ ],

        // Set to true in order to ignore issue suppression.
        // This is useful for testing the state of your code, but
        // unlikely to be useful outside of that.
        'disable_suppression' => false,

        // If set to true, we'll dump the AST instead of
        // analyzing files
        'dump_ast' => false,

        // If set to a string, we'll dump the fully qualified lowercase
        // function and method signatures instead of analyzing files.
        'dump_signatures_file' => null,

        // If set to true, we'll dump the list of files to parse
        // to stdout instead of parsing and analyzing files.
        'dump_parsed_file_list' => false,

        // Include a progress bar in the output
        'progress_bar' => false,

        // The probability of actually emitting any progress
        // bar update. Setting this to something very low
        // is good for reducing network IO and filling up
        // your terminal's buffer when running phan on a
        // remote host.
        // Set this to 0 to use *only* progress_bar_sample_interval.
        'progress_bar_sample_rate' => 0.000,

        // If this much time (in seconds) has passed since the last update,
        // then update the progress bar (Ignores progress_bar_sample_rate).
        // Set this to INF to only use progress_bar_sample_rate.
        'progress_bar_sample_interval' => 0.1,

        // The number of processes to fork off during the analysis
        // phase.
        'processes' => 1,

        // Set to true to emit profiling data on how long various
        // parts of Phan took to run. You likely don't care to do
        // this.
        'profiler_enabled' => false,

        // Add any issue types (such as 'PhanUndeclaredMethod')
        // to this black-list to inhibit them from being reported.
        'suppress_issue_types' => [
            // 'PhanUndeclaredMethod',
        ],

        // If empty, no filter against issues types will be applied.
        // If this white-list is non-empty, only issues within the list
        // will be emitted by Phan.
        'whitelist_issue_types' => [
            // 'PhanAccessClassConstantInternal',
            // 'PhanAccessClassConstantPrivate',
            // 'PhanAccessClassConstantProtected',
            // 'PhanAccessClassInternal',
            // 'PhanAccessConstantInternal',
            // 'PhanAccessMethodInternal',
            // 'PhanAccessMethodPrivate',
            // 'PhanAccessMethodPrivateWithCallMagicMethod',
            // 'PhanAccessMethodProtected',
            // 'PhanAccessMethodProtectedWithCallMagicMethod',
            // 'PhanAccessNonStaticToStatic',
            // 'PhanAccessOwnConstructor',
            // 'PhanAccessPropertyInternal',
            // 'PhanAccessPropertyPrivate',
            // 'PhanAccessPropertyProtected',
            // 'PhanAccessPropertyStaticAsNonStatic',
            // 'PhanAccessSignatureMismatch',
            // 'PhanAccessSignatureMismatchInternal',
            // 'PhanAccessStaticToNonStatic',
            // 'PhanClassContainsAbstractMethod',
            // 'PhanClassContainsAbstractMethodInternal',
            // 'PhanCommentParamOnEmptyParamList',
            // 'PhanCommentParamWithoutRealParam',
            // 'PhanCompatibleExpressionPHP7',
            // 'PhanCompatiblePHP7',
            // 'PhanContextNotObject',
            // 'PhanDeprecatedClass',
            // 'PhanDeprecatedFunction',
            // 'PhanDeprecatedFunctionInternal',
            // 'PhanDeprecatedInterface',
            // 'PhanDeprecatedProperty',
            // 'PhanDeprecatedTrait',
            // 'PhanEmptyFile',
            // 'PhanGenericConstructorTypes',
            // 'PhanGenericGlobalVariable',
            // 'PhanIncompatibleCompositionMethod',
            // 'PhanIncompatibleCompositionProp',
            // 'PhanInvalidCommentForDeclarationType',
            // 'PhanMismatchVariadicComment',
            // 'PhanMismatchVariadicParam',
            // 'PhanMisspelledAnnotation',
            // 'PhanNonClassMethodCall',
            // 'PhanNoopArray',
            // 'PhanNoopClosure',
            // 'PhanNoopConstant',
            // 'PhanNoopProperty',
            // 'PhanNoopVariable',
            // 'PhanParamRedefined',
            // 'PhanParamReqAfterOpt',
            // 'PhanParamSignatureMismatch',
            // 'PhanParamSignatureMismatchInternal',
            // 'PhanParamSignaturePHPDocMismatchHasNoParamType',
            // 'PhanParamSignaturePHPDocMismatchHasParamType',
            // 'PhanParamSignaturePHPDocMismatchParamIsNotReference',
            // 'PhanParamSignaturePHPDocMismatchParamIsReference',
            // 'PhanParamSignaturePHPDocMismatchParamNotVariadic',
            // 'PhanParamSignaturePHPDocMismatchParamType',
            // 'PhanParamSignaturePHPDocMismatchParamVariadic',
            // 'PhanParamSignaturePHPDocMismatchReturnType',
            // 'PhanParamSignaturePHPDocMismatchTooFewParameters',
            // 'PhanParamSignaturePHPDocMismatchTooManyRequiredParameters',
            // 'PhanParamSignatureRealMismatchHasNoParamType',
            // 'PhanParamSignatureRealMismatchHasNoParamTypeInternal',
            // 'PhanParamSignatureRealMismatchHasParamType',
            // 'PhanParamSignatureRealMismatchHasParamTypeInternal',
            // 'PhanParamSignatureRealMismatchParamIsNotReference',
            // 'PhanParamSignatureRealMismatchParamIsNotReferenceInternal',
            // 'PhanParamSignatureRealMismatchParamIsReference',
            // 'PhanParamSignatureRealMismatchParamIsReferenceInternal',
            // 'PhanParamSignatureRealMismatchParamNotVariadic',
            // 'PhanParamSignatureRealMismatchParamNotVariadicInternal',
            // 'PhanParamSignatureRealMismatchParamType',
            // 'PhanParamSignatureRealMismatchParamTypeInternal',
            // 'PhanParamSignatureRealMismatchParamVariadic',
            // 'PhanParamSignatureRealMismatchParamVariadicInternal',
            // 'PhanParamSignatureRealMismatchReturnType',
            // 'PhanParamSignatureRealMismatchReturnTypeInternal',
            // 'PhanParamSignatureRealMismatchTooFewParameters',
            // 'PhanParamSignatureRealMismatchTooFewParametersInternal',
            // 'PhanParamSignatureRealMismatchTooManyRequiredParameters',
            // 'PhanParamSignatureRealMismatchTooManyRequiredParametersInternal',
            // 'PhanParamSpecial1',
            // 'PhanParamSpecial2',
            // 'PhanParamSpecial3',
            // 'PhanParamSpecial4',
            // 'PhanParamTooFew',
            // 'PhanParamTooFewInternal',
            // 'PhanParamTooMany',
            // 'PhanParamTooManyInternal',
            // 'PhanParamTypeMismatch',
            // 'PhanParentlessClass',
            // 'PhanRedefineClass',
            // 'PhanRedefineClassAlias',
            // 'PhanRedefineClassInternal',
            // 'PhanRedefineFunction',
            // 'PhanRedefineFunctionInternal',
            // 'PhanRequiredTraitNotAdded',
            // 'PhanStaticCallToNonStatic',
            // 'PhanSyntaxError',
            // 'PhanTemplateTypeConstant',
            // 'PhanTemplateTypeStaticMethod',
            // 'PhanTemplateTypeStaticProperty',
            // 'PhanTraitParentReference',
            // 'PhanTypeArrayOperator',
            // 'PhanTypeArraySuspicious',
            // 'PhanTypeComparisonFromArray',
            // 'PhanTypeComparisonToArray',
            // 'PhanTypeConversionFromArray',
            // 'PhanTypeInstantiateAbstract',
            // 'PhanTypeInstantiateInterface',
            // 'PhanTypeInvalidClosureScope',
            // 'PhanTypeInvalidLeftOperand',
            // 'PhanTypeInvalidRightOperand',
            // 'PhanTypeMismatchArgument',
            // 'PhanTypeMismatchArgumentInternal',
            // 'PhanTypeMismatchDeclaredParam',
            // 'PhanTypeMismatchDeclaredReturn',
            // 'PhanTypeMismatchDefault',
            // 'PhanTypeMismatchForeach',
            // 'PhanTypeMismatchProperty',
            // 'PhanTypeMismatchReturn',
            // 'PhanTypeMissingReturn',
            // 'PhanTypeNonVarPassByRef',
            // 'PhanTypeParentConstructorCalled',
            // 'PhanTypeSuspiciousIndirectVariable',
            // 'PhanTypeVoidAssignment',
            // 'PhanUnanalyzable',
            // 'PhanUndeclaredAliasedMethodOfTrait',
            // 'PhanUndeclaredClass',
            // 'PhanUndeclaredClassAliasOriginal',
            // 'PhanUndeclaredClassCatch',
            // 'PhanUndeclaredClassConstant',
            // 'PhanUndeclaredClassInstanceof',
            // 'PhanUndeclaredClassMethod',
            // 'PhanUndeclaredClassReference',
            // 'PhanUndeclaredClosureScope',
            // 'PhanUndeclaredConstant',
            // 'PhanUndeclaredExtendedClass',
            // 'PhanUndeclaredFunction',
            // 'PhanUndeclaredInterface',
            // 'PhanUndeclaredMethod',
            // 'PhanUndeclaredProperty',
            // 'PhanUndeclaredStaticMethod',
            // 'PhanUndeclaredStaticProperty',
            // 'PhanUndeclaredTrait',
            // 'PhanUndeclaredTypeParameter',
            // 'PhanUndeclaredTypeProperty',
            // 'PhanUndeclaredTypeReturnType',
            // 'PhanUndeclaredVariable',
            // 'PhanUndeclaredVariableDim',
            // 'PhanUnextractableAnnotation',
            // 'PhanUnextractableAnnotationPart',
            // 'PhanUnreferencedClass',
            // 'PhanUnreferencedConstant',
            // 'PhanUnreferencedPublicClassConstant',
            // 'PhanUnreferencedProtectedClassConstant',
            // 'PhanUnreferencedPrivateClassConstant',
            // 'PhanUnreferencedPublicMethod',
            // 'PhanUnreferencedProtectedMethod',
            // 'PhanUnreferencedPrivateMethod',
            // 'PhanUnreferencedPublicMethod',
            // 'PhanUnreferencedPublicProperty',
            // 'PhanUnreferencedProtectedProperty',
            // 'PhanUnreferencedPublicProperty',
            // 'PhanVariableUseClause',
        ],

        // Override if runkit.superglobal ini directive is used.
        // A custom list of additional superglobals and their types, for projects using runkit.
        // (Corresponding keys are declared in runkit.superglobal ini directive)
        // global_type_map should be set for entries.
        // E.g ['_FOO'];
        'runkit_superglobals' => [],

        // Override to hardcode existence and types of (non-builtin) globals in the global scope.
        // Class names should be prefixed with '\\'.
        // (E.g. ['_FOO' => '\\FooClass', 'page' => '\\PageClass', 'userId' => 'int'])
        'globals_type_map' => [],

        // Emit issue messages with markdown formatting
        'markdown_issue_messages' => false,

        // Emit colorized issue messages.
        // NOTE: it is strongly recommended to enable this via the --color CLI flag instead,
        // since this is incompatible with most output formatters.
        'color_issue_messages' => false,

        // Allow overriding color scheme in .phan/config.php for printing issues, for individual types.
        // See the keys of Phan\Output\Colorizing::styles for valid color names,
        // and the keys of Phan\Output\Colorizing::default_color_for_template for valid color names.
        // E.g. to change the color for the file(of an issue instance) to red, set this to ['FILE' => 'red']
        // E.g. to use the terminal's default color for the line(of an issue instance), set this to ['LINE' => 'none']
        'color_scheme' => [],

        // Enable or disable support for generic templated
        // class types.
        'generic_types_enabled' => true,

        // Assign files to be analyzed on random processes
        // in random order. You very likely don't want to
        // set this to true. This is meant for debugging
        // and fuzz testing purposes only.
        'randomize_file_order' => false,

        // Setting this to true makes the process assignment for file analysis
        // as predictable as possible, using consistent hashing.
        // Even if files are added or removed, or process counts change,
        // relatively few files will move to a different group.
        // (use when the number of files is much larger than the process count)
        // NOTE: If you rely on Phan parsing files/directories in the order
        // that they were provided in this config, don't use this)
        // See https://github.com/phan/phan/wiki/Different-Issue-Sets-On-Different-Numbers-of-CPUs
        'consistent_hashing_file_order' => false,

        // Set by --print-memory-usage-summary. Prints a memory usage summary to stderr after analysis.
        'print_memory_usage_summary' => false,

        // By default, Phan will log error messages to stdout if PHP is using options that slow the analysis.
        // (e.g. PHP is compiled with --enable-debug or when using XDebug)
        'skip_slow_php_options_warning' => false,

        // You can put paths to stubs of internal extensions in this config option.
        // If the corresponding extension is **not** loaded, then phan will use the stubs instead.
        // Phan will continue using its detailed type annotations,
        // but load the constants, classes, functions, and classes (and their Reflection types)
        // from these stub files (doubling as valid php files).
        // Use a different extension from php to avoid accidentally loading these.
        // The 'tools/make_stubs' script can be used to generate your own stubs (compatible with php 7.0+ right now)
        'autoload_internal_extension_signatures' => [
            // 'xdebug' => '.phan/internal_stubs/xdebug.phan_php',
        ],

        // Set this to false to emit PhanUndeclaredFunction issues for internal functions that Phan has signatures for,
        // but aren't available in the codebase, or the internal functions used to run phan
        // (may lead to false positives if an extension isn't loaded)
        // If this is true(default), then Phan will not warn.
        'ignore_undeclared_functions_with_known_signatures' => true,

        // If a file to be analyzed can't be parsed,
        // then use a slower PHP substitute for php-ast to try to parse the files.
        // This setting is ignored if a file is excluded from analysis.
        // NOTE: it is strongly recommended to enable this via the --use-fallback-parser CLI flag instead,
        // since this may result in strange error messages for invalid files (e.g. if parsed but not analyzed).
        'use_fallback_parser' => false,

        // Path to a unix socket for a daemon to listen to files to analyze. Use command line option instead.
        'daemonize_socket' => false,

        // TCP port(from 1024 to 65535) for a daemon to listen to files to analyze. Use command line option instead.
        'daemonize_tcp_port' => false,

        // If this is an array, it configures the way clients will communicate with the Phan language server.
        // Possibilities: Exactly one of
        // ['stdin' => true],
        // ['tcp-server' => string (address this server should listen on)],
        // ['tcp' => string (address client is listening on)
        'language_server_config' => false,

        // Valid values: null, 'info'. Used when developing or debugging a language server client of Phan.
        'language_server_debug_level' => null,

        // Can be set to false to disable the plugins Phan uses to infer more accurate return types of array_map, array_filter, etc.
        // Phan is slightly faster when these are disabled.
        'enable_internal_return_type_plugins' => true,

        // A list of plugin files to execute
        // (Pass these in as relative paths (e.g. 'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php').
        // The upcoming 0.10.2 release will allow passing 'AlwaysReturnPlugin' if referring to a plugin that is bundled with Phan)
        'plugins' => [
        ],
    ];

    /**
     * Disallow the constructor to force a singleton
     */
    private function __construct()
    {
    }

    /**
     * @return string
     * Get the root directory of the project that we're
     * scanning
     */
    public static function getProjectRootDirectory() : string
    {
        return self::$project_root_directory ?? getcwd();
    }

    /**
     * @param string $project_root_directory
     * Set the root directory of the project that we're
     * scanning
     *
     * @return void
     */
    public static function setProjectRootDirectory(
        string $project_root_directory
    ) {
        self::$project_root_directory = $project_root_directory;
    }

    /**
     * @return Config
     * Get a Configuration singleton
     */
    public static function get() : Config
    {
        static $instance;

        if ($instance) {
            return $instance;
        }

        $instance = new Config();
        $instance->init();
        return $instance;
    }

    /**
     * @return void
     */
    private function init()
    {
        // Trigger magic setters
        foreach (self::$configuration as $name => $v) {
            self::setValue($name, $v);
        }
    }

    /**
     * @return array
     * A map of configuration keys and their values
     */
    public function toArray() : array
    {
        return self::$configuration;
    }

    // @codingStandardsIgnoreStart method naming is deliberate to make these getters easier to search.

    public static function get_null_casts_as_any_type() : bool
    {
        return self::$null_casts_as_any_type;
    }

    public static function get_null_casts_as_array() : bool
    {
        return self::$null_casts_as_array;
    }

    public static function get_array_casts_as_null() : bool
    {
        return self::$array_casts_as_null;
    }

    public static function get_track_references() : bool
    {
        return self::$track_references;
    }

    public static function get_dead_code_detection() : bool
    {
        return self::getValue('dead_code_detection');
    }

    public static function get_backward_compatibility_checks() : bool
    {
        return self::$backward_compatibility_checks;
    }

    public static function get_quick_mode() : bool
    {
        return self::$quick_mode;
    }
    // @codingStandardsIgnoreEnd

    /**
     * @return mixed
     * @deprecated
     */
    public function __get(string $name)
    {
        return self::getValue($name);
    }

    /**
     * @return mixed
     */
    public static function getValue(string $name)
    {
        return self::$configuration[$name];
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     * @deprecated
     */
    public function __set(string $name, $value)
    {
        self::setValue($name, $value);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public static function setValue(string $name, $value)
    {
        self::$configuration[$name] = $value;
        switch ($name) {
            case 'null_casts_as_any_type':
                self::$null_casts_as_any_type = $value;
                break;
            case 'null_casts_as_array':
                self::$null_casts_as_array = $value;
                break;
            case 'array_casts_as_null':
                self::$array_casts_as_null = $value;
                break;
            case 'dead_code_detection':
            case 'force_tracking_references':
                self::$track_references = self::getValue('dead_code_detection') || self::getValue('force_tracking_references');
                break;
            case 'backward_compatibility_checks':
                self::$backward_compatibility_checks = $value;
                break;
            case 'quick_mode':
                self::$quick_mode = $value;
                break;
        }
    }

    /**
     * @return string
     * The relative path appended to the project root directory.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function projectPath(string $relative_path)
    {
        // Make sure its actually relative
        if (\DIRECTORY_SEPARATOR === \substr($relative_path, 0, 1)) {
            return $relative_path;
        }
        // Check for absolute path in windows, e.g. C:\
        if (\DIRECTORY_SEPARATOR === "\\" &&
                \strlen($relative_path) > 3 &&
                \ctype_alpha($relative_path[0]) &&
                $relative_path[1] === ':' &&
                \strspn($relative_path, '/\\', 2, 1)) {
            return $relative_path;
        }

        return \implode(DIRECTORY_SEPARATOR, [
            Config::getProjectRootDirectory(),
            $relative_path
        ]);
    }
}

// Call init() to trigger the magic setters.
Config::get();
