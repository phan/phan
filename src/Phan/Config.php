<?php declare(strict_types=1);
namespace Phan;

/**
 * Program configuration.
 * See `./phan -h` for command line usage, or take a
 * look at \Phan\CLI.php for more details on CLI usage.
 */
class Config
{

    /**
     * @var string|null
     * The root directory of the project. This is used to
     * store canonical path names and find project resources
     */
    private $project_root_directory = null;

    /**
     * Configuration options
     */
    private $configuration = [

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

        // Backwards Compatibility Checking. This is slow
        // and expensive, but you should consider running
        // it before upgrading your version of PHP to a
        // new version that has backward compatibility
        // breaks.
        'backward_compatibility_checks' => true,

        // A set of fully qualified class-names for which
        // a call to parent::__construct() is required.
        'parent_constructor_required' => [],

        // Run a quick version of checks that takes less
        // time at the cost of not running as thorough
        // an analysis. You should consider setting this
        // to true only when you wish you had more issues
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
        'analyze_signature_compatibility' => true,

        // The minimum severity level to report on. This can be
        // set to Issue::SEVERITY_LOW, Issue::SEVERITY_NORMAL or
        // Issue::SEVERITY_CRITICAL. Setting it to only
        // critical issues is a good place to start on a big
        // sloppy mature code base.
        'minimum_severity' => 0,

        // If true, missing properties will be created when
        // they are first seen. If false, we'll report an
        // error message if there is an attempt to write
        // to a class property that wasn't explicitly
        // defined.
        'allow_missing_properties' => false,

        // Allow null to be cast as any type and for any
        // type to be cast to null. Setting this to false
        // will cut down on false positives.
        'null_casts_as_any_type' => false,

        // If enabled, scalars (int, float, bool, string, null)
        // are treated as if they can cast to each other.
        'scalar_implicit_cast' => false,

        // If true, seemingly undeclared variables in the global
        // scope will be ignored. This is useful for projects
        // with complicated cross-file globals that you have no
        // hope of fixing.
        'ignore_undeclared_variables_in_global_scope' => false,

        // If true, check to make sure the return type declared
        // in the doc-block (if any) matches the return type
        // declared in the method signature. This process is
        // slow.
        'check_docblock_signature_return_type_match' => false,

        // Set to true in order to attempt to detect dead
        // (unreferenced) code. Keep in mind that the
        // results will only be a guess given that classes,
        // properties, constants and methods can be referenced
        // as variables (like `$class->$property` or
        // `$class->$method()`) in ways that we're unable
        // to make sense of.
        'dead_code_detection' => false,

        // If true, the dead code detection rig will
        // prefer false negatives (not report dead code) to
        // false positives (report dead code that is not
        // actually dead) which is to say that the graph of
        // references will create too many edges rather than
        // too few edges when guesses have to be made about
        // what references what.
        'dead_code_detection_prefer_false_negative' => true,

        // If true, then try to simplify AST into a form which improves Phan's type inference.
        // E.g. rewrites `if (!is_string($foo)) { return; } b($foo);`
        // into `if (is_string($foo)) {b($foo);} else {return;}`
        // This may conflict with 'dead_code_detection'.
        // This option also slows down analysis noticeably.
        'simplify_ast' => false,

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
        'progress_bar_sample_rate' => 0.005,

        // The number of processes to fork off during the analysis
        // phase.
        'processes' => 1,

        // The vesion of the AST (defined in php-ast)
        // we're using
        'ast_version' => 40,

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
            // 'PhanAccessMethodPrivate',
            // 'PhanAccessMethodProtected',
            // 'PhanAccessNonStaticToStatic',
            // 'PhanAccessPropertyPrivate',
            // 'PhanAccessPropertyProtected',
            // 'PhanAccessSignatureMismatch',
            // 'PhanAccessSignatureMismatchInternal',
            // 'PhanAccessStaticToNonStatic',
            // 'PhanCompatibleExpressionPHP7',
            // 'PhanCompatiblePHP7',
            // 'PhanContextNotObject',
            // 'PhanDeprecatedClass',
            // 'PhanDeprecatedInterface',
            // 'PhanDeprecatedTrait',
            // 'PhanDeprecatedFunction',
            // 'PhanDeprecatedProperty',
            // 'PhanEmptyFile',
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
            // 'PhanRedefineClassInternal',
            // 'PhanRedefineFunction',
            // 'PhanRedefineFunctionInternal',
            // 'PhanStaticCallToNonStatic',
            // 'PhanSyntaxError',
            // 'PhanTraitParentReference',
            // 'PhanTypeArrayOperator',
            // 'PhanTypeArraySuspicious',
            // 'PhanTypeComparisonFromArray',
            // 'PhanTypeComparisonToArray',
            // 'PhanTypeConversionFromArray',
            // 'PhanTypeInstantiateAbstract',
            // 'PhanTypeInstantiateInterface',
            // 'PhanTypeInvalidLeftOperand',
            // 'PhanTypeInvalidRightOperand',
            // 'PhanTypeMismatchArgument',
            // 'PhanTypeMismatchArgumentInternal',
            // 'PhanTypeMismatchDefault',
            // 'PhanTypeMismatchForeach',
            // 'PhanTypeMismatchProperty',
            // 'PhanTypeMismatchReturn',
            // 'PhanTypeMissingReturn',
            // 'PhanTypeNonVarPassByRef',
            // 'PhanTypeParentConstructorCalled',
            // 'PhanTypeVoidAssignment',
            // 'PhanUnanalyzable',
            // 'PhanUndeclaredClass',
            // 'PhanUndeclaredClassCatch',
            // 'PhanUndeclaredClassConstant',
            // 'PhanUndeclaredClassInstanceof',
            // 'PhanUndeclaredClassMethod',
            // 'PhanUndeclaredClassReference',
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
            // 'PhanUndeclaredVariable',
            // 'PhanUnreferencedClass',
            // 'PhanUnreferencedConstant',
            // 'PhanUnreferencedMethod',
            // 'PhanUnreferencedProperty',
            // 'PhanVariableUseClause',
        ],

        // Override if runkit.superglobal ini directive is used.
        // A custom list of additional superglobals and their types, for projects using runkit.
        // (Corresponding keys are declared in runkit.superglobal ini directive)
        // global_type_map should be set for entries.
        // E.g ['_FOO'];
        'runkit_superglobals' => [],

        // Override to hardcode existence and types of (non-builtin) globals in the global scope.
        // Class names must be prefixed with '\\'.
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
        // See https://github.com/etsy/phan/wiki/Different-Issue-Sets-On-Different-Numbers-of-CPUs
        'consistent_hashing_file_order' => false,

        // Path to a unix socket for a daemon to listen to files to analyze. Use command line option instead.
        'daemonize_socket' => false,

        // TCP port(from 1024 to 65535) for a daemon to listen to files to analyze. Use command line option instead.
        'daemonize_tcp_port' => false,

        // A list of plugin files to execute
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
    public function getProjectRootDirectory() : string
    {
        return $this->project_root_directory ?? getcwd();
    }

    /**
     * @param string $project_root_directory
     * Set the root directory of the project that we're
     * scanning
     *
     * @return void
     */
    public function setProjectRootDirectory(
        string $project_root_directory
    ) {
        $this->project_root_directory = $project_root_directory;
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
        return $instance;
    }

    /**
     * @return array
     * A map of configuration keys and their values
     */
    public function toArray() : array
    {
        return $this->configuration;
    }

    /** @return mixed */
    public function __get(string $name)
    {
        return $this->configuration[$name];
    }

    public function __set(string $name, $value)
    {
        $this->configuration[$name] = $value;
    }

    /**
     * @return string
     * The relative path appended to the project root directory.
     *
     * @suppress PhanUnreferencedMethod
     */
    public static function projectPath(string $relative_path)
    {
        // Make sure its actually relative
        if (DIRECTORY_SEPARATOR == substr($relative_path, 0, 1)) {
            return $relative_path;
        }

        return implode(DIRECTORY_SEPARATOR, [
            Config::get()->getProjectRootDirectory(),
            $relative_path
        ]);
    }
}
