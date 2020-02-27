```
Usage: ./phan [options] [files...]
 -f, --file-list <filename>
  A file containing a list of PHP files to be analyzed

 -l, --directory <directory>
  A directory that should be parsed for class and
  method information. After excluding the directories
  defined in --exclude-directory-list, the remaining
  files will be statically analyzed for errors.

  Thus, both first-party and third-party code being used by
  your application should be included in this list.

  You may include multiple `--directory <directory>` options.

 --exclude-file <file>
  A file that should not be parsed or analyzed (or read
  at all). This is useful for excluding hopelessly
  unanalyzable files.

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories that defines files
  that will be excluded from static analysis, but whose
  class and method information should be included.
  (can be repeated, ignored if --include-analysis-file-list is used)

  Generally, you'll want to include the directories for
  third-party code (such as "vendor/") in this list.

 -I, --include-analysis-file-list <file_list>
  A comma-separated list of files that will be included in
  static analysis. All others won't be analyzed.
  (can be repeated)

  This is primarily intended for performing standalone
  incremental analysis.

 -d, --project-root-directory </path/to/project>
  The directory of the project to analyze.
  Phan expects this directory to contain the configuration file `.phan/config.php`.
  If not provided, the current working directory is analyzed.

 -r, --file-list-only <file>
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is unlikely to be useful.

 -k, --config-file <file>
  A path to a config file to load (instead of the default of
  `.phan/config.php`).

 -m, --output-mode <mode>
  Output mode from 'text', 'json', 'csv', 'codeclimate', 'checkstyle', 'pylint', or 'html'

 -o, --output <filename>
  Output filename

 --init
   [--init-level=3]
   [--init-analyze-dir=path/to/src]
   [--init-analyze-file=path/to/file.php]
   [--init-no-composer]

  Generates a `.phan/config.php` in the current directory
  based on the project's composer.json.
  The logic used to generate the config file is currently very simple.
  Some third party classes (e.g. in vendor/)
  will need to be manually added to 'directory_list' or excluded,
  and you may end up with a large number of issues to be manually suppressed.
  See https://github.com/phan/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base

  [--init-level <level>] affects the generated settings in `.phan/config.php`
    (e.g. null_casts_as_array).
    `--init-level` can be set to 1 (strictest) to 5 (least strict)
  [--init-analyze-dir <dir>] can be used as a relative path alongside directories
    that Phan infers from composer.json's "autoload" settings
  [--init-analyze-file <file>] can be used as a relative path alongside files
    that Phan infers from composer.json's "bin" settings
  [--init-no-composer] can be used to tell Phan that the project
    is not a composer project.
    Phan will not check for composer.json or vendor/,
    and will not include those paths in the generated config.
  [--init-overwrite] will allow 'phan --init' to overwrite .phan/config.php.

 -C, --color, --no-color
  Add colors to the outputted issues.
  This is recommended for only the default --output-mode ('text')

  [--color-scheme={default,code,light,eclipse_dark,vim}]
    This (or the environment variable PHAN_COLOR_SCHEME) can be used to set the color scheme for emitted issues.

 -p, --progress-bar, --no-progress-bar, --long-progress-bar
  Show progress bar. --no-progress-bar disables the progress bar.
  --long-progress-bar shows a progress bar that doesn't overwrite the current line.

 -D, --debug
  Print debugging output to stderr. Useful for looking into performance issues or crashes.

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 --target-php-version {7.0,7.1,7.2,7.3,7.4,8.0,native}
  The PHP version that the codebase will be checked for compatibility against.
  For best results, the PHP binary used to run Phan should have the same PHP version.
  (Phan relies on Reflection for some param counts
   and checks for undefined classes/methods/functions)

 -i, --ignore-undeclared
  Ignore undeclared functions and classes

 -y, --minimum-severity <level>
  Minimum severity level (low=0, normal=5, critical=10) to report.
  Defaults to 0.

 -c, --parent-constructor-required
  Comma-separated list of classes that require
  parent::__construct() to be called

 -x, --dead-code-detection
  Emit issues for classes, methods, functions, constants and
  properties that are probably never referenced and can
  be removed. This implies `--unused-variable-detection`.

 -u, --unused-variable-detection
  Emit issues for variables, parameters and closure use variables
  that are probably never referenced.
  This has a few known false positives, e.g. for loops or branches.

 -t, --redundant-condition-detection
  Emit issues for conditions such as `is_int(expr)` that are redundant or impossible.

  This has some known false positives for loops, variables set in loops,
  and global variables.

 -j, --processes <int>
  The number of parallel processes to run during the analysis
  phase. Defaults to 1.

 -z, --signature-compatibility
  Analyze signatures for methods that are overrides to ensure
  compatibility with what they're overriding.

 --disable-cache
  Don't cache any ASTs from the polyfill/fallback.

  ASTs from the native parser (php-ast) don't need to be cached.

  This is useful if Phan will be run only once and php-ast is unavailable (e.g. in Travis)

 --disable-plugins
  Don't run any plugins. Slightly faster.

 -P, --plugin <pluginName|path/to/Plugin.php>
  Add a plugin to run. This flag can be repeated.
  (Either pass the name of the plugin or a relative/absolute path to the plugin)

 --strict-method-checking
  Warn if any type in a method invocation's object is definitely not an object,
  or any type in an invoked expression is not a callable.
  (Enables the config option `strict_method_checking`)

 --strict-param-checking
  Warn if any type in an argument's union type cannot be cast to
  the parameter's expected union type.
  (Enables the config option `strict_param_checking`)

 --strict-property-checking
  Warn if any type in a property assignment's union type
  cannot be cast to a type in the property's declared union type.
  (Enables the config option `strict_property_checking`)

 --strict-object-checking
  Warn if any type of the object expression for a property access
  does not contain that property.
  (Enables the config option `strict_object_checking`)

 --strict-return-checking
  Warn if any type in a returned value's union type
  cannot be cast to the declared return type.
  (Enables the config option `strict_return_checking`)

 -S, --strict-type-checking
  Equivalent to
  `--strict-method-checking --strict-object-checking --strict-param-checking --strict-property-checking --strict-return-checking`.

 --use-fallback-parser
  If a file to be analyzed is syntactically invalid
  (i.e. "php --syntax-check path/to/file" would emit a syntax error),
  then retry, using a different, slower error tolerant parser to parse it.
  (And phan will then analyze what could be parsed).
  This flag is experimental and may result in unexpected exceptions or errors.
  This flag does not affect excluded files and directories.

 --allow-polyfill-parser
  If the `php-ast` extension isn't available or is an outdated version,
  then use a slower parser (based on tolerant-php-parser) instead.
  Note that https://github.com/Microsoft/tolerant-php-parser
  has some known bugs which may result in false positive parse errors.

 --force-polyfill-parser
  Use a slower parser (based on tolerant-php-parser) instead of the native parser,
  even if the native parser is available.
  Useful mainly for debugging.

 -s, --daemonize-socket </path/to/file.sock>
  Unix socket for Phan to listen for requests on, in daemon mode.

 --daemonize-tcp-host <hostname>
  TCP hostname for Phan to listen for JSON requests on, in daemon mode.
  (e.g. `default`, which is an alias for host `127.0.0.1`, or `0.0.0.0` for
  usage with Docker). `phan_client` can be used to communicate with the Phan Daemon.

 --daemonize-tcp-port <default|1024-65535>
  TCP port for Phan to listen for JSON requests on, in daemon mode.
  (e.g. `default`, which is an alias for port 4846.)
  `phan_client` can be used to communicate with the Phan Daemon.

 --save-baseline <path/to/baseline.php>
  Generates a baseline of pre-existing issues that can be used to suppress
  pre-existing issues in subsequent runs (with --load-baseline)

  This baseline depends on the environment, CLI and config settings used to run Phan
  (e.g. --dead-code-detection, plugins, etc.)

  Paths such as .phan/baseline.php, .phan/baseline_deadcode.php, etc. are recommended.

 -B, --load-baseline <path/to/baseline.php>
  Loads a baseline of pre-existing issues to suppress.

  (For best results, the baseline should be generated with the same/similar
  environment and settings as those used to run Phan)

 --analyze-twice
  Runs the analyze phase twice. Because Phan gathers additional type information for properties, return types, etc. during analysis,
  this may emit a more complete list of issues.

  This cannot be used with --processes <int>.

 -v, --version
  Print Phan's version number

 -h, --help
  This help information

 --extended-help
  This help information, plus less commonly used flags
  (E.g. for daemon mode)

Extended help:
 -a, --dump-ast
  Emit an AST for each file rather than analyze.

 --dump-parsed-file-list
  Emit a newline-separated list of files Phan would parse to stdout.
  This is useful to verify that options such as exclude_file_regex are
  properly set up, or to run other checks on the files Phan would parse.

 --dump-signatures-file <filename>
  Emit JSON serialized signatures to the given file.
  This uses a method signature format similar to FunctionSignatureMap.php.

 --automatic-fix
  Automatically fix any issues Phan is capable of fixing.
  NOTE: This is a work in progress and limited to a small subset of issues
  (e.g. unused imports on their own line)

 --find-signature <paramUnionType1->paramUnionType2->returnUnionType>
  Find a signature in the analyzed codebase that is similar to the argument.
  See `tool/phoogle` for examples.

 --memory-limit <memory_limit>
  Sets the memory limit for analysis (per process).
  This is useful when developing or when you want guarantees on memory limits.
  K, M, and G are optional suffixes (Kilobytes, Megabytes, Gigabytes).

 --print-memory-usage-summary
  Prints a summary of memory usage and maximum memory usage.
  This is accurate when there is one analysis process.

 --markdown-issue-messages
  Emit issue messages with markdown formatting.

 --absolute-path-issue-messages
  Emit issues with their absolute paths instead of relative paths.
  This does not affect files mentioned within the issue.

 --analyze-all-files
  Ignore the --exclude-directory-list <dir_list> flag and `exclude_analysis_directory_list` config settings and analyze all files that were parsed.
  This is slow, but useful when third-party files being parsed have incomplete type information.
  Also see --analyze-twice.

 --constant-variable-detection
  Emit issues for variables that could be replaced with literals or constants.
  (i.e. they are declared once (as a constant expression) and never modified).
  This is almost entirely false positives for most coding styles.
  Implies --unused-variable-detection

 --debug-emitted-issues={basic,verbose}
  Print backtraces of emitted issues which weren't suppressed to stderr.

 --debug-signal-handler
  Set up a signal handler that can handle interrupts, SIGUSR1, and SIGUSR2.
  This requires pcntl, and slows down Phan. When this option is enabled,

  Ctrl-C (kill -INT <pid>) can be used to make Phan stop and print a crash report.
  (This is useful for diagnosing why Phan or a plugin is slow or not responding)
  kill -USR1 <pid> can be used to print a backtrace and continue running.
  kill -USR2 <pid> can be used to print a backtrace, plus values of parameters, and continue running.

 --language-server-on-stdin
  Start the language server (For the Language Server protocol).
  This is a different protocol from --daemonize, clients for various IDEs already exist.

 --language-server-tcp-server <addr>
  Start the language server listening for TCP connections on <addr> (e.g. 127.0.0.1:<port>)

 --language-server-tcp-connect <addr>
  Start the language server and connect to the client listening on <addr> (e.g. 127.0.0.1:<port>)

 --language-server-analyze-only-on-save
  Prevent the client from sending change notifications (Only notify the language server when the user saves a document)
  This significantly reduces CPU usage, but clients won't get notifications about issues immediately.

 --language-server-disable-go-to-definition, --language-server-enable-go-to-definition
  Disables/Enables support for "Go To Definition" and "Go To Type Definition" in the Phan Language Server.
  Enabled by default.

 --language-server-disable-hover, --language-server-enable-hover
  Disables/Enables support for "Hover" in the Phan Language Server.
  Enabled by default.

 --language-server-disable-completion, --language-server-enable-completion
  Disables/Enables support for "Completion" in the Phan Language Server.
  Enabled by default.

 --language-server-completion-vscode
  Adds a workaround to make completion of variables and static properties
  that are compatible with language clients such as VS Code.

 --language-server-verbose
  Emit verbose logging messages related to the language server implementation to stderr.
  This is useful when developing or debugging language server clients.

 --language-server-disable-output-filter
  Emit all issues detected from the language server (e.g. invalid phpdoc in parsed files),
  not just issues in files currently open in the editor/IDE.
  This can be very verbose and has more false positives.

  This is useful when developing or debugging language server clients.

 --language-server-allow-missing-pcntl
  No-op (This is the default behavior).
  Allow the fallback that doesn't use pcntl (New and experimental) to be used if the pcntl extension is not installed.
  This is useful for running the language server on Windows.

 --language-server-hide-category
  Remove the Phan issue category from diagnostic messages.
  Makes issue messages slightly shorter.

 --language-server-force-missing-pcntl
  Force Phan to use the fallback for when pcntl is absent (New and experimental). Useful for debugging that fallback.

 --language-server-require-pcntl
  Don't start the language server if PCNTL isn't installed (don't use the fallback). Useful for debugging.

 --language-server-min-diagnostics-delay-ms <0..1000>
  Sets a minimum delay between publishing diagnostics (i.e. Phan issues) to the language client.
  This can be increased to work around race conditions in clients processing Phan issues (e.g. if your editor/IDE shows outdated diagnostics)
  Defaults to 0. (no delay)

 --require-config-exists
  Exit immediately with an error code if `.phan/config.php` does not exist.

 --help-annotations
  Print details on annotations supported by Phan.
```
