<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\Parser;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\PluginV3;
use Phan\PluginV3\AfterAnalyzeFileCapability;
use Phan\PluginV3\BeforeAnalyzeFileCapability;
use Phan\PluginV3\FinalizeProcessCapability;

/**
 * This plugin invokes the equivalent of `php --no-php-ini --syntax-check $analyzed_file_path`.
 *
 * php-ast reports syntax errors, but does not report all **semantic** errors that `php --syntax-check` would detect.
 *
 * Note that loading PHP modules would slow down analysis, so this plugin adds `--no-php-ini`.
 *
 * NOTE: This may not work in languages other than english.
 * NOTE: .phan/config.php can contain a config to override the PHP binary/binaries used
 *      This can replace the default binary (PHP_BINARY) with an array of absolute path or program names(in $PATH)
 *       E.g. have 'plugin_config' => ['php_native_syntax_check_binaries' => ['php72', 'php70', 'php56']]
 * Note: This may cause Phan to take over twice as long. This is recommended for use with `--processes N`.
 *
 * Known issues:
 * - short_open_tags may make php --syntax-check --no-php-ini behave differently from php --syntax-check, e.g. for '<?phpinvalid;'
 *
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class InvokePHPNativeSyntaxCheckPlugin extends PluginV3 implements
    AfterAnalyzeFileCapability,
    BeforeAnalyzeFileCapability,
    FinalizeProcessCapability
{
    private const LINE_NUMBER_REGEX = "@ on line ([1-9][0-9]*)$@D";
    private const STDIN_FILENAME_REGEX = "@ in (Standard input code|-)@";

    /**
     * @var list<InvokeExecutionPromise>
     * A list of invoked processes that this plugin created.
     * This plugin creates 0 or more processes(up to a maximum number can run at a time)
     * and then waits for the execution of those processes to finish.
     */
    private $processes = [];

    /**
     * @param CodeBase $code_base @phan-unused-param
     * The code base in which the node exists
     *
     * @param Context $context
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents @phan-unused-param
     * @param Node $node the node @phan-unused-param
     * @override
     */
    public function beforeAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    ): void {
        $php_binaries = (Config::getValue('plugin_config')['php_native_syntax_check_binaries'] ?? null) ?: [PHP_BINARY];

        foreach ($php_binaries as $binary) {
            $this->processes[] = new InvokeExecutionPromise($binary, $file_contents, $context);
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context @phan-unused-param
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents @phan-unused-param
     * @param Node $node the node @phan-unused-param
     * @override
     * @throws Error if a process fails to shut down
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    ): void {
        $configured_max_incomplete_processes = (int)(Config::getValue('plugin_config')['php_native_syntax_check_max_processes'] ?? 1) - 1;
        $max_incomplete_processes = max(0, $configured_max_incomplete_processes);
        $this->awaitIncompleteProcesses($code_base, $max_incomplete_processes);
    }

    /**
     * @throws Error if a syntax check process fails to shut down
     */
    private function awaitIncompleteProcesses(CodeBase $code_base, int $max_incomplete_processes): void
    {
        foreach ($this->processes as $i => $process) {
            if (!$process->read()) {
                continue;
            }
            unset($this->processes[$i]);
            self::handleError($code_base, $process);
        }
        $max_incomplete_processes = max(0, $max_incomplete_processes);
        while (count($this->processes) > $max_incomplete_processes) {
            $process = array_pop($this->processes);
            if (!$process) {
                throw new AssertionError("Process list should be non-empty");
            }
            $process->blockingRead();
            self::handleError($code_base, $process);
        }
    }

    /**
     * @override
     * @throws Error if a syntax check process fails to shut down.
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        $this->awaitIncompleteProcesses($code_base, 0);
    }

    private static function handleError(CodeBase $code_base, InvokeExecutionPromise $process): void
    {
        $check_error_message = $process->getError();
        if (!is_string($check_error_message)) {
            return;
        }
        $context = $process->getContext();
        $binary = $process->getBinary();
        $lineno = 1;
        if (preg_match(self::LINE_NUMBER_REGEX, $check_error_message, $matches)) {
            $lineno = (int)$matches[1];
            $check_error_message = trim(preg_replace(self::LINE_NUMBER_REGEX, '', $check_error_message));
        }
        $check_error_message = preg_replace(self::STDIN_FILENAME_REGEX, '', $check_error_message);

        self::emitIssue(
            $code_base,
            clone($context)->withLineNumberStart($lineno),
            'PhanNativePHPSyntaxCheckPlugin',
            'Saw error or notice for {FILE} --syntax-check: {DETAILS}',
            [
                $binary === PHP_BINARY ? 'php' : $binary,
                json_encode($check_error_message),

            ],
            Issue::SEVERITY_CRITICAL
        );
    }
}

/**
 * This wraps a `php --syntax-check` process,
 * and contains methods to start the process and await the result
 * (and check for failures)
 */
class InvokeExecutionPromise
{
    /** @var string path to the php binary invoked */
    private $binary;

    /** @var bool is the process finished executing */
    private $done = false;

    /** @var resource the result of `proc_open()` */
    private $process;

    /** @var array{0:resource,1:resource,2:resource} stdin, stdout, stderr */
    private $pipes;

    /** @var ?string an error message */
    private $error = null;

    /** @var string the raw bytes from stdout with serialized data */
    private $raw_stdout = '';

    /** @var Context has the file name being analyzed */
    private $context;

    /** @var ?string the temporary path, if needed for Windows. */
    private $tmp_path;

    public function __construct(string $binary, string $file_contents, Context $context)
    {
        $this->context = clone($context);
        $new_file_contents = Parser::removeShebang($file_contents);
        // TODO: Use symfony process
        // Note: We might have invalid utf-8, ensure that the streams are opened in binary mode.
        // I'm not sure if this is necessary.
        if (DIRECTORY_SEPARATOR === "\\") {
            $cmd = escapeshellarg($binary) . ' --syntax-check --no-php-ini';
            $abs_path = $this->getAbsPathForFileContents($new_file_contents, $file_contents !== $new_file_contents);
            if (!is_string($abs_path)) {
                // The helper function has set the error and done flags
                return;
            }

            // Possibly https://bugs.php.net/bug.php?id=51800
            // NOTE: Work around this by writing from the original file. This may not work as expected in LSP mode
            $abs_path = str_replace("/", "\\", $abs_path);

            $cmd .= ' < ' . escapeshellarg($abs_path);

            $descriptorspec = [
                1 => ['pipe', 'wb'],
            ];
            $this->binary = $binary;
            // https://superuser.com/questions/1213094/how-to-escape-in-cmd-exe-c-parameters/1213100#1213100
            //
            // > Otherwise, old behavior is to see if the first character is
            // > a quote character and if so, strip the leading character and
            // > remove the last quote character on the command line, preserving
            // > any text after the last quote character.
            //
            // e.g. `""C:\php 7.4.3\php.exe" --syntax-check --no-php-ini < "C:\some project\test.php""`
            // gets unescaped as `"C:\php 7.4.3\php.exe" --syntax-check --no-php-ini < "C:\some project\test.php"`
            if (PHP_VERSION_ID < 80000) {
                // In PHP 8.0.0, proc_open started always escaping arguments with additional quotes, so doing it twice would be a bug.
                $cmd = "\"$cmd\"";
            }
            $process = proc_open("$cmd", $descriptorspec, $pipes);
            if (!is_resource($process)) {
                $this->done = true;
                $this->error = "Failed to run proc_open in " . __METHOD__;
                return;
            }
            $this->process = $process;
        } else {
            $cmd = [$binary, '--syntax-check', '--no-php-ini'];
            if (PHP_VERSION_ID < 70400) {
                $cmd = implode(' ', array_map('escapeshellarg', $cmd));
            }
            $descriptorspec = [
                ['pipe', 'rb'],
                ['pipe', 'wb'],
            ];
            $this->binary = $binary;
            // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
            $process = proc_open($cmd, $descriptorspec, $pipes);
            if (!is_resource($process)) {
                $this->done = true;
                $this->error = "Failed to run proc_open in " . __METHOD__;
                return;
            }
            $this->process = $process;

            self::streamPutContents($pipes[0], $new_file_contents);
        }
        $this->pipes = $pipes;

        if (!stream_set_blocking($pipes[1], false)) {
            $this->error = "unable to set read stdout to non-blocking";
        }
    }

    private function getAbsPathForFileContents(string $new_file_contents, bool $force_tmp_file): ?string
    {
        $file_name = $this->context->getFile();
        if ($force_tmp_file || CLI::isDaemonOrLanguageServer()) {
            // This is inefficient, but
            // - Windows has problems with using stdio/stdout at the same time
            // - During regular analysis, we won't need to create temporary files.
            $tmp_path = tempnam(sys_get_temp_dir(), 'phan');
            if (!is_string($tmp_path)) {
                $this->done = true;
                $this->error = "Could not create temporary path for $file_name";
                return null;
            }
            file_put_contents($tmp_path, $new_file_contents);
            $this->tmp_path = $tmp_path;
            return $tmp_path;
        }
        $abs_path = Config::projectPath($file_name);
        if (!file_exists($abs_path)) {
            $this->done = true;
            $this->error = "File does not exist";
            return null;
        }
        return $abs_path;
    }

    /**
     * @param resource $stream stream to write $file_contents to before fclose()
     * @param string $file_contents
     * @return void
     * See https://bugs.php.net/bug.php?id=39598
     */
    private static function streamPutContents($stream, string $file_contents): void
    {
        try {
            while (strlen($file_contents) > 0) {
                $bytes_written = fwrite($stream, $file_contents);
                if ($bytes_written === false) {
                    error_log('failed to write in ' . __METHOD__);
                    return;
                }
                if ($bytes_written === 0) {
                    $read_streams = [];
                    $write_streams = [$stream];
                    $except_streams = [];
                    // Wait for the stream to be available for write with a timeout of 1 second.
                    stream_select($read_streams, $write_streams, $except_streams, 1);
                    if (!$write_streams) {
                        usleep(1000);  // Probably unnecessary, but leaving it in anyway
                        // This is blocked?
                        continue;
                    }
                    // $stream is ready to be written to?
                    $bytes_written = fwrite($stream, $file_contents);
                    if (!$bytes_written) {
                        error_log('failed to write in ' . __METHOD__ . ' but the stream should be ready');
                        return;
                    }
                }
                if ($bytes_written > 0) {
                    $file_contents = \substr($file_contents, $bytes_written);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return bool false if an error was encountered when trying to read more output from the syntax check process.
     */
    public function read(): bool
    {
        if ($this->done) {
            return true;
        }
        $stdout = $this->pipes[1];
        while (!feof($stdout)) {
            $bytes = fread($stdout, 4096);
            if ($bytes === false) {
                break;
            }
            if (strlen($bytes) === 0) {
                break;
            }
            $this->raw_stdout .= $bytes;
        }
        if (!feof($stdout)) {
            return false;
        }
        fclose($stdout);

        $this->done = true;

        $exit_code = proc_close($this->process);
        if ($exit_code === 0) {
            $this->error = null;
            return true;
        }
        $output = str_replace("\r", "", trim($this->raw_stdout));
        $first_line = explode("\n", $output)[0];
        $this->error = $first_line;
        return true;
    }

    /**
     * @throws Error if reading failed
     */
    public function blockingRead(): void
    {
        if ($this->done) {
            return;
        }
        if (!stream_set_blocking($this->pipes[1], true)) {
            throw new Error("Unable to make stdout blocking");
        }
        if (!$this->read()) {
            throw new Error("Failed to read");
        }
    }

    /**
     * @throws RangeException if this was called before the process finished
     */
    public function getError(): ?string
    {
        if (!$this->done) {
            throw new RangeException("Called " . __METHOD__ . " too early");
        }
        return $this->error;
    }

    /**
     * Returns the context containing the name of the file being syntax checked
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return string the path to the PHP interpreter binary. (e.g. `/usr/bin/php`)
     */
    public function getBinary(): string
    {
        return $this->binary;
    }

    public function __wakeup()
    {
        $this->tmp_path = null;
        throw new RuntimeException("Cannot unserialize");
    }

    public function __destruct()
    {
        // We created a temporary path for Windows
        if (is_string($this->tmp_path)) {
            unlink($this->tmp_path);
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new InvokePHPNativeSyntaxCheckPlugin();
