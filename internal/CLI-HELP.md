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

  You may include multiple `--directory DIR` options.

 --exclude-file <file>
  A file that should not be parsed or analyzed (or read
  at all). This is useful for excluding hopelessly
  unanalyzable files.

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories that defines files
  that will be excluded from static analysis, but whose
  class and method information should be included.
  (can be repeated, ignored if --include-analysis-directory-list is used)

  Generally, you'll want to include the directories for
  third-party code (such as "vendor/") in this list.

 -I, --include-analysis-file-list <file_list>
  A comma-separated list of files that will be included in
  static analysis. All others won't be analyzed.
  (can be repeated)

  This is primarily intended for performing standalone
  incremental analysis.

 -d, --project-root-directory </path/to/project>
  Hunt for a directory named `.phan` in the provided directory
  and read configuration file `.phan/config.php` from that path.

 -r, --file-list-only
  A file containing a list of PHP files to be analyzed to the
  exclusion of any other directories or files passed in. This
  is unlikely to be useful.

 -k, --config-file
  A path to a config file to load (instead of the default of
  `.phan/config.php`).

 -m <mode>, --output-mode
  Output mode from 'text', 'json', 'csv', 'codeclimate', 'checkstyle', or 'pylint'

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

  [--init-level] affects the generated settings in `.phan/config.php`
    (e.g. null_casts_as_array).
    `--init-level` can be set to 1 (strictest) to 5 (least strict)
  [--init-analyze-dir] can be used as a relative path alongside directories
    that Phan infers from composer.json's "autoload" settings
  [--init-analyze-file] can be used as a relative path alongside files
    that Phan infers from composer.json's "bin" settings
  [--init-no-composer] can be used to tell Phan that the project
    is not a composer project.
    Phan will not check for composer.json or vendor/,
    and will not include those paths in the generated config.
  [--init-overwrite] will allow 'phan --init' to overwrite .phan/config.php.

 -C, --color
  Add colors to the outputted issues. Tested in Unix.
  This is recommended for only the default --output-mode ('text')

 -p, --progress-bar
  Show progress bar

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 --target-php-version {7.0,7.1,7.2,7.3,7.4,native}
  The PHP version that the codebase will be checked for compatibility against.
  For best results, the PHP binary used to run Phan should have the same PHP version.
  (Phan relies on Reflection for some param counts
   and checks for undefined classes/methods/functions)

 -i, --ignore-undeclared
  Ignore undeclared functions and classes

 -y, --minimum-severity <level in {0,5,10}>
  Minimum severity level (low=0, normal=5, critical=10) to report.
  Defaults to 0.

 -c, --parent-constructor-required
  Comma-separated list of classes that require
  parent::__construct() to be called

 -x, --dead-code-detection
  Emit issues for classes, methods, functions, constants and
  properties that are probably never referenced and can
  be removed. This implies `--unused-variable-detection`.

 --unused-variable-detection
  Emit issues for variables, parameters and closure use variables
  that are probably never referenced.
  This has a few known false positives, e.g. for loops or branches.

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

 --strict-return-checking
  Warn if any type in a returned value's union type
  cannot be cast to the declared return type.
  (Enables the config option `strict_return_checking`)

 -S, --strict-type-checking
  Equivalent to
  `--strict-method-checking --strict-param-checking --strict-property-checking --strict-return-checking`.

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

 --daemonize-tcp-host
  TCP hostname for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for host 127.0.0.1, or `0.0.0.0` for
  usage with Docker). `phan_client` can be used to communicate with the Phan Daemon.

 --daemonize-tcp-port <default|1024-65535>
  TCP port for Phan to listen for JSON requests on, in daemon mode.
  (e.g. 'default', which is an alias for port 4846.)
  `phan_client` can be used to communicate with the Phan Daemon.

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

 --find-signature 'paramUnionType1->paramUnionType2->returnUnionType'
  Find a signature in the analyzed codebase that is similar to the argument.
  See tool/phoogle for examples.

 --memory-limit <memory_limit>
  Sets the memory limit for analysis (per process).
  This is useful when developing or when you want guarantees on memory limits.
  K, M, and G are optional suffixes (Kilobytes, Megabytes, Gigabytes).

 --print-memory-usage-summary
  Prints a summary of memory usage and maximum memory usage.
  This is accurate when there is one analysis process.

 --markdown-issue-messages
  Emit issue messages with markdown formatting.

 --polyfill-parse-all-element-doc-comments
  Makes the polyfill aware of doc comments on class constants and declare statements
  even when imitating parsing a PHP 7.0 codebase.

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

 --language-server-enable-go-to-definition
  Enables support for "Go To Definition" and "Go To Type Definition" in the Phan Language Server.
  Disabled by default.

 --language-server-enable-hover
  Enables support for "Hover" in the Phan Language Server.
  Disabled by default.

 --language-server-enable-completion
  Enables support for "Completion" in the Phan Language Server.
  Disabled by default.

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

 --require-config-exists
  Exit immediately with an error code if `.phan/config.php` does not exist.

 --help-annotations
  Print details on annotations supported by Phan
```
