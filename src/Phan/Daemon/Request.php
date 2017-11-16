<?php declare(strict_types=1);
namespace Phan\Daemon;

use Closure;
use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Daemon;
use Phan\Language\FileRef;
use Phan\Language\Type;
use Phan\LanguageServer\FileMapping;
use Phan\Library\FileCache;
use Phan\Output\IssuePrinterInterface;
use Phan\Output\PrinterFactory;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Represents the state of a client request to a daemon, and contains methods for sending formatted responses.
 */
class Request
{
    const METHOD_ANALYZE_FILES = 'analyze_files';  // has shorthand analyze_file with param 'file'

    const PARAM_METHOD = 'method';
    const PARAM_FILES  = 'files';
    const PARAM_FORMAT = 'format';
    const PARAM_TEMPORARY_FILE_MAPPING_CONTENTS = 'temporary_file_mapping_contents';

    // success codes
    const STATUS_OK = 'ok';  // unrecognized output format
    const STATUS_NO_FILES = 'no_files';  // none of the requested files were in this project's config directories

    // failure codes
    const STATUS_INVALID_FORMAT = 'invalid_format';  // unrecognized requested output "format"
    const STATUS_ERROR_UNKNOWN = 'error_unknown';
    const STATUS_INVALID_FILES = 'invalid_files';  // expected a valid string for 'files'/'file'
    const STATUS_INVALID_METHOD = 'invalid_method';  // expected 'method' to be analyze_files or
    const STATUS_INVALID_REQUEST = 'invalid_request';  // expected a valid string for 'files'/'file'

    /** @var resource|null - Null after the response is sent. */
    private $response_connection;

    /** @var array */
    private $config;

    /** @var BufferedOutput */
    private $buffered_output;

    /** @var string */
    private $method;

    /** @var string[]|null */
    private $files = null;

    private static $child_pids = [];

    private static $exited_pid_status = [];

    /**
     * @param resource $response_connection
     * @param array $config
     */
    private function __construct($response_connection, array $config)
    {
        $this->response_connection = $response_connection;
        $this->config = $config;
        $this->buffered_output = new BufferedOutput();
        $this->method = $config[self::PARAM_METHOD];
        if ($this->method === self::METHOD_ANALYZE_FILES) {
            $this->files = $config[self::PARAM_FILES];
        }
    }

    /**
     * @param resource $response_connection a socket to write a response on
     * @param string[] $filenames absolute path of file(s) to analyze
     * @param CodeBase $code_base (for refreshing parse state)
     * @param Closure $file_path_lister (for refreshing parse state)
     * @param FileMapping $file_mapping object tracking the overrides made by a client.
     */
    public static function makeLanguageServerAnalysisRequest(
        $response_connection,
        array $filenames,
        CodeBase $code_base,
        Closure $file_path_lister,
        FileMapping $file_mapping
    ) : Request {
        FileCache::clear();
        $file_mapping_contents = self::normalizeFileMappingContents($file_mapping->getOverrides(), $error_message);
        // Use the temporary contents if they're available
        Request::reloadFilePathListForDaemon($code_base, $file_path_lister, $file_mapping_contents);
        if ($error_message !== null) {
            Daemon::debugf($error_message);
        };
        $result = new self(
            $response_connection,
            [
                self::PARAM_FORMAT => 'json',
                self::PARAM_METHOD => self::METHOD_ANALYZE_FILES,
                self::PARAM_FILES => $filenames,
                self::PARAM_TEMPORARY_FILE_MAPPING_CONTENTS => $file_mapping_contents,
            ]
        );
        return $result;
    }

    public function getPrinter() : IssuePrinterInterface
    {
        // TODO: check $this->config['format']
        $factory = new PrinterFactory();
        $format = $this->config['format'] ?? 'json';
        if (!in_array($format, $factory->getTypes())) {
            $this->sendJSONResponse([
                "status" => self::STATUS_INVALID_FORMAT,
            ]);
            exit(0);
        }
        return $factory->getPrinter($format, $this->buffered_output);
    }

    /**
     * Respond with issues in the requested format
     * @return void
     */
    public function respondWithIssues(int $issueCount)
    {
        $raw_issues = $this->buffered_output->fetch();
        if (($this->config[self::PARAM_FORMAT] ?? null) === 'json') {
            $issues = json_decode($raw_issues, true);
            if (!\is_array($issues)) {
                $issues = "(Failed to decode) " . json_last_error_msg() . ': ' . $raw_issues;
            }
        } else {
            $issues = $raw_issues;
        }
        $this->sendJSONResponse([
            "status" => self::STATUS_OK,
            "issue_count" => $issueCount,
            "issues" => $issues,
        ]);
    }

    /**
     * @return void
     */
    public function respondWithNoFilesToAnalyze()
    {
        // The mentioned file wasn't in .phan/config.php's list of files to analyze.
        // TODO: Send the client that list of files.
        $this->sendJSONResponse([
            "status" => self::STATUS_NO_FILES,
        ]);
    }


    /**
     * @param string[] $analyze_file_path_list
     * @return string[]
     */
    public function filterFilesToAnalyze(array $analyze_file_path_list) : array
    {
        if (\is_null($this->files)) {
            Daemon::debugf("No files to filter in filterFilesToAnalyze");
            return $analyze_file_path_list;
        }

        $analyze_file_path_set = array_flip($analyze_file_path_list);
        $filteredFiles = [];
        foreach ($this->files as $file) {
            // Must be relative to project, allow absolute paths to be passed in.
            $file = FileRef::getProjectRelativePathForPath($file);

            if (\array_key_exists($file, $analyze_file_path_set)) {
                $filteredFiles[] = $file;
            } else {
                // TODO: Reload file list once before processing request?
                // TODO: Make this override blacklists of folders in src/Phan/Phan
                Daemon::debugf("Failed to find requested file '%s' in parsed file list", $file, json_encode($analyze_file_path_list));
            }
        }
        Daemon::debugf("Returning file set: %s", json_encode($filteredFiles));
        return $filteredFiles;
    }

    /**
     * TODO: convert absolute path to relative paths.
     * @return string[] - Maps original relative file paths to contents.
     */
    public function getTemporaryFileMapping() : array
    {
        $mapping = $this->config[self::PARAM_TEMPORARY_FILE_MAPPING_CONTENTS] ?? [];
        Daemon::debugf("Have the following files in mapping: %s", json_encode(array_keys($mapping)));
        return $mapping;
    }

    /**
     * Send a response and close the connection, for the given socket's protocol.
     * Currently supports only JSON.
     * TODO: HTTP protocol.
     *
     * @param array $response
     * @return void
     */
    public function sendJSONResponse(array $response)
    {
        self::sendJSONResponseOverSocket($this->response_connection, $response);
        $this->response_connection = null;
    }

    /**
     * @param resource $response_connection
     * @param array $response
     * @return void
     */
    public static function sendJSONResponseOverSocket($response_connection, array $response)
    {
        if (!$response_connection) {
            Daemon::debugf("Already sent response");
            return;
        }
        fwrite($response_connection, json_encode($response) . "\n");
        // disable further receptions and transmissions
        // Note: This is likely a giant hack,
        // and pcntl and sockets may break in the future if used together. (multiple processes owning a single resource).
        // Not sure how to do that safely.
        stream_socket_shutdown($response_connection, STREAM_SHUT_RDWR);
        fclose($response_connection);
    }

    public function __destruct()
    {
        if (isset($this->response_connection)) {
            $this->sendJSONResponse([
                'status' => self::STATUS_ERROR_UNKNOWN,
                'message' => 'failed to send a response - Possibly encountered an exception. See daemon output.',
            ]);
        }
    }

    /**
     * @param int $signo
     * @param int|null $status
     * @param int|null $pid
     * @return void
     */
    public static function childSignalHandler($signo, $status = null, $pid = null)
    {
        if ($signo !== SIGCHLD) {
            return;
        }
        if (!$pid) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        Daemon::debugf("Got signal pid=%s", json_encode($pid));

        while ($pid > 0) {
            if (\array_key_exists($pid, self::$child_pids)) {
                $exit_code = pcntl_wexitstatus($status);
                if ($exit_code != 0) {
                    error_log(sprintf("child process %d exited with status %d\n", $pid, $exit_code));
                } else {
                    Daemon::debugf("child process %d completed successfully", $pid);
                }
                unset(self::$child_pids[$pid]);
            } elseif ($pid > 0) {
                self::$exited_pid_status[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
    }

    /**
     * @param string[] $file_mapping_contents
     * @param ?string &$error_message @phan-output-reference
     */
    public static function normalizeFileMappingContents($file_mapping_contents, &$error_message) : array
    {
        $error_message = null;
        if (!\is_array($file_mapping_contents)) {
            $error_message = 'Invalid value of temporary_file_mapping_contents';
        }
        $new_file_mapping_contents = [];
        foreach ($file_mapping_contents ?? [] as $file => $contents) {
            if (!\is_string($file)) {
                $error_message = 'Passed non-string in list of files to map';
                return [];
            } elseif (!\is_string($contents)) {
                $error_message = 'Passed non-string in as new file contents';
                return [];
            }
            $new_file_mapping_contents[FileRef::getProjectRelativePathForPath($file)] = $contents;
        }
        return $new_file_mapping_contents;
    }
    /**
     * @param CodeBase $code_base
     * @param \Closure $file_path_lister
     * @param resource $response_connection
     * @return ?Request - non-null if this is a worker process with work to do. null if request failed or this is the master.
     */
    public static function accept(CodeBase $code_base, \Closure $file_path_lister, $response_connection)
    {
        FileCache::clear();
        Daemon::debugf("Got a connection");  // debugging code
        // Efficient for large strings, e.g. long file lists.
        $data = [];
        while (!feof($response_connection)) {
            $data[] = fgets($response_connection);
        }
        $request_bytes = implode('', $data);
        $request = json_decode($request_bytes, true);

        if (!\is_array($request)) {
            Daemon::debugf("Received invalid request, expected JSON: %s", json_encode($request_bytes));
            self::sendJSONResponseOverSocket($response_connection, [
                'status'  => self::STATUS_INVALID_REQUEST,
                'message' => 'malformed JSON',
            ]);
            return null;
        }
        $new_file_mapping_contents = [];
        $method = $request['method'] ?? null;
        switch ($method) {
            case 'analyze_all':
                // Analyze the default list of files. No expected params.
                break;
            case 'analyze_file':
                $method = 'analyze_files';
                $request = [
                self::PARAM_METHOD => $method,
                self::PARAM_FILES => [$request['file']],
                self::PARAM_FORMAT => $request[self::PARAM_FORMAT] ?? 'json',
                ];
                // Fall through, this is an alias of analyze_files
            case 'analyze_files':
                // Analyze the list of strings provided in "files"
                // TODO: Actually do that.
                $files = $request[self::PARAM_FILES] ?? null;
                $request[self::PARAM_FORMAT] = $request[self::PARAM_FORMAT] ?? 'json';
                $error_message = null;
                if (\is_array($files) && count($files)) {
                    foreach ($files as $file) {
                        if (!\is_string($file)) {
                            $error_message = 'Passed non-string in list of files';
                            break;
                        }
                    }
                } else {
                    $error_message = 'Must pass a non-empty array of file paths for field files';
                }
                if (\is_null($error_message)) {
                    $file_mapping_contents = $request[self::PARAM_TEMPORARY_FILE_MAPPING_CONTENTS] ?? [];
                    $new_file_mapping_contents = self::normalizeFileMappingContents($file_mapping_contents, $error_message);
                    $request[self::PARAM_TEMPORARY_FILE_MAPPING_CONTENTS] = $new_file_mapping_contents;
                }
                if ($error_message !== null) {
                    Daemon::debugf($error_message);
                    self::sendJSONResponseOverSocket($response_connection, [
                    'status'  => self::STATUS_INVALID_FILES,
                    'message' => $error_message,
                    ]);
                    return null;
                }
                break;
        // TODO(optional): add APIs to resolve types of variables/properties/etc (e.g. accept byte offset or line/column offset)
            default:
                $message = sprintf("expected method to be analyze_all or analyze_files, got %s", json_encode($method));
                Daemon::debugf($message);
                self::sendJSONResponseOverSocket($response_connection, [
                'status'  => self::STATUS_INVALID_METHOD,
                'message' => $message,
                ]);
                return null;
        }

        self::reloadFilePathListForDaemon($code_base, $file_path_lister, $new_file_mapping_contents);

        $fork_result = pcntl_fork();
        if ($fork_result < 0) {
            error_log("The daemon failed to fork. Going to terminate");
        } elseif ($fork_result == 0) {
            Daemon::debugf("This is the fork");
            self::$child_pids = [];
            // TODO: Re-parse the file list.
            $request_obj = new self($response_connection, $request);
            $temporary_file_mapping = $request_obj->getTemporaryFileMapping();
            if (count($temporary_file_mapping) > 0) {
                self::applyTemporaryFileMappingForParsePhase($code_base, $temporary_file_mapping);
            }
            return $request_obj;
        } else {
            $pid = $fork_result;
            $status = self::$exited_pid_status[$pid] ?? null;
            if (isset($status)) {
                Daemon::debugf("child process %d already exited", $pid);
                self::childSignalHandler(SIGCHLD, $status, $pid);
                unset(self::$exited_pid_status[$pid]);
            } else {
                self::$child_pids[$pid] = true;
            }

            // TODO: Parse the new file list **before forking**, not after forking.
            // TODO: Use http://php.net/manual/en/book.inotify.php if available, watch all directories if available.
            // Daemon continues to execute.
            self::$child_pids[] = $fork_result;
            Daemon::debugf("Created a child pid %d", $fork_result);
        }
        return null;
    }

    /**
     * Reloads the file path list.
     * @return void
     */
    public static function reloadFilePathListForDaemon(CodeBase $code_base, \Closure $file_path_lister, array $file_mapping_contents)
    {
        $old_count = $code_base->getParsedFilePathCount();

        $file_list = $file_path_lister();

        if (Config::getValue('consistent_hashing_file_order')) {
            // Parse the files in lexicographic order.
            // If there are duplicate class/function definitions,
            // this ensures they are added to the maps in the same order.
            sort($file_list, SORT_STRING);
        }

        $changed_or_added_files = $code_base->updateFileList($file_list, $file_mapping_contents);
        // Daemon::debugf("Parsing modified files: New files = %s", json_encode($changed_or_added_files));
        if (count($changed_or_added_files) > 0 || $code_base->getParsedFilePathCount() !== $old_count) {
            // Only clear memoizations if it is determined at least one file to parse was added/removed/modified.
            // - file path count changes if files were deleted or added
            // - changed_or_added_files has an entry for every added/modified file.
            // (would be 0 if a client analyzes one file, then analyzes a different file)
            Type::clearAllMemoizations();
        }
        // A progress bar doesn't make sense in a daemon which can theoretically process multiple requests at once.
        foreach ($changed_or_added_files as $file_path) {
            // Kick out anything we read from the former version
            // of this file
            $code_base->flushDependenciesForFile($file_path);

            // If the file is gone, no need to continue
            if (($real = realpath($file_path)) === false || !file_exists($real)) {
                Daemon::debugf("file $file_path does not exist");
                continue;
            }
            Daemon::debugf("Parsing %s yet again", $file_path);
            try {
                // Parse the file
                Analysis::parseFile($code_base, $file_path, false, $file_mapping_contents[$file_path] ?? null);
            } catch (\Throwable $throwable) {
                error_log(sprintf("Analysis::parseFile threw %s for %s: %s\n%s", get_class($throwable), $file_path, $throwable->getMessage(), $throwable->getTraceAsString()));
            }
        }
        Daemon::debugf("Done parsing modified files");
    }

    /**
     * Substitutes files. We assume that the original file path exists already, and reject it if it doesn't.
     * (i.e. it was returned by $file_path_lister in the past)
     *
     * @return void
     */
    private static function applyTemporaryFileMappingForParsePhase(CodeBase $code_base, array $temporary_file_mapping_contents)
    {
        if (count($temporary_file_mapping_contents) === 0) {
            return;
        }

        // too verbose
        Daemon::debugf("Parsing temporary file mapping contents: New contents = %s", json_encode($temporary_file_mapping_contents));

        $changes_to_add = [];
        foreach ($temporary_file_mapping_contents as $file_name => $contents) {
            if ($code_base->beforeReplaceFileContents($file_name)) {
                $changes_to_add[$file_name] = $contents;
            }
        }
        Daemon::debugf("Done setting temporary file contents: Will replace contents of the following files: %s", json_encode(array_keys($changes_to_add)));
        if (count($changes_to_add) === 0) {
            return;
        }
        Type::clearAllMemoizations();

        foreach ($changes_to_add as $file_path => $new_contents) {
            // Kick out anything we read from the former version
            // of this file
            $code_base->flushDependenciesForFile($file_path);

            // If the file is gone, no need to continue
            if (($real = realpath($file_path)) === false || !file_exists($real)) {
                Daemon::debugf("file $file_path no longer exists on disk, but we tried to replace it?");
                continue;
            }
            Daemon::debugf("Parsing temporary file instead of %s", $file_path);
            try {
                // Parse the file
                Analysis::parseFile($code_base, $file_path, false, $new_contents);
            } catch (\Throwable $throwable) {
                error_log(sprintf("Analysis::parseFile threw %s for %s: %s\n%s", get_class($throwable), $file_path, $throwable->getMessage(), $throwable->getTraceAsString()));
            }
        }
    }
}
