<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use AdvancedJsonRpc;
use Closure;
use Phan\Phan;
use Phan\CodeBase;
use Phan\Config;
use Phan\Daemon\ExitException;
use Phan\Daemon\Request;
use Phan\Daemon\Transport\CapturerResponder;
use Phan\Daemon\Transport\StreamResponder;
use Phan\Issue;
use Phan\Language\FileRef;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\Diagnostic;
use Phan\LanguageServer\Protocol\DiagnosticSeverity;
use Phan\LanguageServer\Protocol\InitializeResult;
use Phan\LanguageServer\Protocol\Message;
use Phan\LanguageServer\Protocol\Position;
use Phan\LanguageServer\Protocol\Range;
use Phan\LanguageServer\Protocol\SaveOptions;
use Phan\LanguageServer\Protocol\ServerCapabilities;
use Phan\LanguageServer\Protocol\TextDocumentSyncKind;
use Phan\LanguageServer\Protocol\TextDocumentSyncOptions;
use Phan\LanguageServer\Server\TextDocument;
use Phan\LanguageServer\ProtocolReader;
use Phan\LanguageServer\ProtocolWriter;
use Phan\LanguageServer\ProtocolStreamReader;
use Phan\LanguageServer\ProtocolStreamWriter;
use Sabre\Event\Loop;
use Sabre\Event\Promise;

use function Sabre\Event\coroutine;
use Throwable;

/**
 * Based on https://github.com/felixfbecker/php-language-server/blob/master/bin/php-language-server.php
 * and https://github.com/felixfbecker/php-language-server/blob/master/src/LanguageServer.php (for language server protocol implementation)
 *
 * This is similar to Phan daemon mode, but it's possible for it to receive concurrent events, or more than one type of event.
 * (in addition to file notifications, etc.)
 *
 * What will do in the most common case (checking for errors in a file):
 *
 * 0. Phan completes the parse phase, on the initial version of the codebase
 * 1. Phan starts a Sabre event loop and listens for requests and responses: see http://sabre.io/event/loop/
 *    It registers addReadStream and addWriteStream (See ProtocolStreamReader and ProtocolStreamWriter)
 * 2. Phan receives a notification that a file changed/was added/was removed
 * 3. If the files are within the phan project (contains .phan directory),
 *    then phan removes the old parse state of removed/changed files, then adds the new parse state of changed/added files.
 * 4. The main phan process (managing the event loop) fork, the fork shuts down the event loop without receiving any more events or touching the stream(will this work?)
 * 5. The forked process runs the analysis.
 * 6. The forked process reports the analysis result to the main process via IPC (similar to what \Phan\ForkPool does), along with the id of the request
 * 7. Phan notifies the client of the new issues ("diagnostic" in the open Language Server Protocol)
 *    See https://github.com/Microsoft/language-server-protocol#language-server-protocol
 *
 * TODO: support textDocument rename
 */
class LanguageServer extends AdvancedJsonRpc\Dispatcher
{
    /**
     * Handles workspace/* method calls
     *
     * @var Server\Workspace
     */
    public $workspace;

    /**
     * @var ProtocolReader
     */
    protected $protocolReader;

    /**
     * @var ProtocolWriter
     */
    protected $protocolWriter;

    /**
     * @var LanguageClient
     */
    protected $client;

    /**
     * Handles textDocument/* method calls
     * (e.g. whenever a text document is opened, saved, or closed)
     *
     * @var TextDocument
     */
    public $textDocument;

    /**
     * @var Request|null
     */
    protected $most_recent_request;

    /**
     * @var CodeBase
     */
    protected $code_base;

    /**
     * @var Closure
     */
    protected $file_path_lister;

    /**
     * @var FileMapping
     */
    protected $file_mapping;

    /**
     * @var bool
     */
    private $is_accepting_new_requests = true;

    /**
     * @var array<string,string> maps Paths to URIs, for URIs which have pending analysis requests.
     * Requests are buffered because the language server may otherwise send requests faster than Phan can respond to them.
     * (`$reader->on('readMessageGroup')` notifies Phan that a group of 1 or more messages was read)
     */
    protected $analyze_request_set = [];

    public function __construct(ProtocolReader $reader, ProtocolWriter $writer, CodeBase $code_base, Closure $file_path_lister)
    {
        parent::__construct($this, '/');
        $this->protocolReader = $reader;
        $this->file_mapping = new FileMapping();
        $reader->on('close', function () {
            if (!$this->is_accepting_new_requests) {
                // This is the forked process, which forced the ProtocolReader to close. Don't exit().
                // Instead, carry on and analyze the input files.
                return;
            }
            $this->shutdown();
            $this->exit();
        });
        /** @suppress PhanUndeclaredClassMethod https://github.com/fruux/sabre-event/pull/52 */
        $reader->on('message', function (Message $msg) {
            /** @suppress PhanUndeclaredProperty Request->body->id is a request with an id */
            coroutine(function () use ($msg) {
                // Ignore responses, this is the handler for requests and notifications
                if (AdvancedJsonRpc\Response::isResponse($msg->body)) {
                    return;
                }
                Logger::logInfo('Received message in coroutine: ' . (string)$msg->body);
                $result = null;
                $error = null;
                try {
                    // Invoke the method handler to get a result
                    $result = yield $this->dispatch($msg->body);
                } catch (AdvancedJsonRpc\Error $e) {
                    Logger::logInfo('Saw error: ' . $e->getMessage());
                    // If a ResponseError is thrown, send it back in the Response
                    $error = $e;
                } catch (Throwable $e) {
                    Logger::logInfo('Saw throwable: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                    // If an unexpected error occurred, send back an INTERNAL_ERROR error response
                    $error = new AdvancedJsonRpc\Error(
                        (string)$e,
                        AdvancedJsonRpc\ErrorCode::INTERNAL_ERROR,
                        null,
                        $e
                    );
                }
                // Only send a Response for a Request
                // Notifications do not send Responses
                if (AdvancedJsonRpc\Request::isRequest($msg->body)) {
                    if ($error !== null) {
                        $responseBody = new AdvancedJsonRpc\ErrorResponse($msg->body->id, $error);
                    } else {
                        $responseBody = new AdvancedJsonRpc\SuccessResponse($msg->body->id, $result);
                    }
                    $this->protocolWriter->write(new Message($responseBody));
                }
            })->otherwise('\\Phan\\LanguageServer\\Utils::crash');
        });

        $reader->on('readMessageGroup', function () {
            $this->finalizeAnalyzingURIs();
        });

        $this->protocolWriter = $writer;
        // We create a client to send diagnostics, etc. to the IDE
        $this->client = new LanguageClient($reader, $writer);
        // We create a workspace to receive change notifications.
        $this->workspace = new Server\Workspace($this->client, $this, $this->file_mapping);

        // Phan specific code
        $this->code_base = $code_base;
        $this->file_path_lister = $file_path_lister;
    }

    /**
     * This creates an analyzing daemon, to be used by IDEs.
     * Format:
     *
     * - Read over TCP socket with JSONRPC 2
     * - Respond over TCP socket with JSONRPC 2
     *
     * @param CodeBase $code_base (Must have undo tracker enabled)
     *
     * @param \Closure $file_path_lister
     * Returns string[] - A list of files to scan. This may be different from the previous contents.
     *
     * @param array $options (leave empty for stdout)
     *
     * @return Request|null - A writeable request, which has been fully read from.
     * Callers should close after they are finished writing.
     *
     * @suppress PhanUndeclaredConstant (pcntl unavailable on Windows)
     */
    public static function run(CodeBase $code_base, \Closure $file_path_lister, array $options)
    {
        \assert($code_base->isUndoTrackingEnabled());

        $make_language_server = function (ProtocolStreamReader $in, ProtocolStreamWriter $out) use ($code_base, $file_path_lister) : LanguageServer {
            return new LanguageServer(
                $in,
                $out,
                $code_base,
                $file_path_lister
            );
        };
        // example requests over TCP
        // Assumes that clients send and close the their requests quickly, then wait for a response.

        // {"method":"analyze","files":["/path/to/file1.php","/path/to/file2.php"]}

        // FIXME add re-parsing files to actions taken during loop
        /*
        $socket_server = self::createDaemonStreamSocketServer();
        // TODO: Limit the maximum number of active processes to a small number(4?)
        // TODO: accept SIGCHLD when child terminates, somehow?
        try {
            $gotSignal = false;
            pcntl_signal(SIGCHLD, function(...$args) use(&$gotSignal) {
                $gotSignal = true;
                Request::childSignalHandler(...$args);
            });
            while (true) {
                $gotSignal = false;  // reset this.
                // We get an error from stream_socket_accept. After the RuntimeException is thrown, pcntl_signal is called.
                $previousErrorHandler = set_error_handler(function ($severity, $message, $file, $line) use (&$previousErrorHandler) {
                    self::debugf("In new error handler '$message'");
                    if (!preg_match('/stream_socket_accept/i', $message)) {
                        return $previousErrorHandler($severity, $message, $file, $line);
                    }
                    throw new \RuntimeException("Got signal");
                });

                $conn = false;
                try {
                    $conn = stream_socket_accept($socket_server, -1);
                } catch(\RuntimeException $e) {
                    self::debugf("Got signal");
                    pcntl_signal_dispatch();
                    self::debugf("done processing signals");
                    if ($gotSignal) {
                        continue;  // Ignore notices from stream_socket_accept if it's due to being interrupted by a child process terminating.
                    }
                } finally {
                    restore_error_handler();
                }

                if (!\is_resource($conn)) {
                    // If we didn't get a connection, and it wasn't due to a signal from a child process, then stop the daemon.
                    break;
                }
                $request = Request::accept($code_base, $file_path_lister, $conn);
                if ($request instanceof Request) {
                    return $request;  // We forked off a worker process successfully, and this is the worker process
                }
            }
            error_log("Stopped accepting connections");
        } finally {
            restore_error_handler();
        }
        return null;
         */
        if (!empty($options['tcp'])) {
            // Connect to a TCP server
            $address = $options['tcp'];
            $socket = stream_socket_client('tcp://' . $address, $errno, $errstr);
            if ($socket === false) {
                fwrite(STDERR, "Could not connect to language client. Error $errno\n$errstr");
                exit(1);
            }
            stream_set_blocking($socket, false);
            $ls = $make_language_server(new ProtocolStreamReader($socket), new ProtocolStreamWriter($socket));
            Logger::logInfo("Connected to $address to receive requests");
            Loop\run();
            Logger::logInfo("Finished connecting to $address to receive requests");
            $most_recent_request = $ls->most_recent_request;
            $ls->most_recent_request = null;
            return $most_recent_request;
        } elseif (!empty($options['tcp-server'])) {
            // Run a TCP Server
            $address = $options['tcp-server'];
            $tcpServer = stream_socket_server('tcp://' . $address, $errno, $errstr);
            if ($tcpServer === false) {
                fwrite(STDERR, "Could not listen on $address. Error $errno\n$errstr");
                exit(1);
            }
            fwrite(STDOUT, "Server listening on $address\n");
            if (!extension_loaded('pcntl')) {
                fwrite(STDERR, "PCNTL is not available. Only a single connection will be accepted\n");
            }
            while ($socket = stream_socket_accept($tcpServer, -1)) {
                fwrite(STDOUT, "Connection accepted\n");
                stream_set_blocking($socket, false);
                /**if (false && extension_loaded('pcntl')) {  // FIXME re-enable, this was disabled to simplify testing
                    // TODO: This will work, but does it make sense?

                    // If PCNTL is available, fork a child process for the connection
                    // An exit notification will only terminate the child process
                    $pid = pcntl_fork();
                    if ($pid === -1) {
                        fwrite(STDERR, "Could not fork\n");
                        exit(1);
                    } else if ($pid === 0) {
                        // Child process
                        $reader = new ProtocolStreamReader($socket);
                        $writer = new ProtocolStreamWriter($socket);
                        $reader->on('close', function () {
                            fwrite(STDOUT, "Connection closed\n");
                        });
                        $ls = $make_language_server($reader, $writer);
                        Logger::logInfo("Worker started accepting requests on $address");
                        Loop\run();
                        Logger::logInfo("Worker finished accepting requests on $address");
                        $most_recent_request = $ls->most_recent_request;
                        $ls->most_recent_request = null;
                        return $most_recent_request;;
                    }
                } else {*/
                    // To avoid edge cases, we only accept one connection.
                    // If PCNTL is not available, we only accept one connection.
                    // An exit notification will terminate the server
                    $ls = $make_language_server(
                        new ProtocolStreamReader($socket),
                        new ProtocolStreamWriter($socket)
                    );
                    Logger::logInfo("Started listening on tcp");
                    Loop\run();
                    Logger::logInfo("Finished listening on tcp");
                    $most_recent_request = $ls->most_recent_request;
                    $ls->most_recent_request = null;
                    return $most_recent_request;
                ;
                /* } */
            }
        } else {
            assert($options['stdin'] === true);
            // Use STDIO
            stream_set_blocking(STDIN, false);
            $ls = $make_language_server(
                new ProtocolStreamReader(STDIN),
                new ProtocolStreamWriter(STDOUT)
            );
            Logger::logInfo("Started listening on stdin");
            Loop\run();
            Logger::logInfo("Finished listening on stdin");
            $most_recent_request = $ls->most_recent_request;
            $ls->most_recent_request = null;
            return $most_recent_request;
        }
    }

    /**
     * Asynchronously analyze the given URI.
     * @return void
     */
    public function analyzeURIAsync(string $uri)
    {
        Logger::logInfo("Called analyzeURIAsync, uri=$uri");
        $path_to_analyze = Utils::uriToPath($uri);
        $relative_path_to_analyze = FileRef::getProjectRelativePathForPath($path_to_analyze);
        Logger::logInfo("Maybe going to analyze this file: $path_to_analyze");
        $this->analyze_request_set[$path_to_analyze] = $uri;
        // Don't call file_path_lister immediately -
        // That has to walk the directories in .phan/config.php to see if the requested path is included and not excluded.
    }

    /**
     * Gets URIs (and corresponding paths) which the language server client needs Phan to re-analyze.
     * This excludes any files that aren't in files and directories of .phan/config.php
     *
     * @return array<string,string> maps relative path to the file URI.
     */
    private function getFilteredURIsToAnalyze() : array
    {
        $uris_to_analyze = $this->analyze_request_set;
        if (\count($uris_to_analyze) === 0) {
            return [];
        }
        $this->analyze_request_set = [];

        // Always recompute the file list from the directory list : see src/phan.php
        $file_path_list = ($this->file_path_lister)(true);
        $filtered_uris_to_analyze = [];
        foreach ($uris_to_analyze as $path_to_analyze => $uri) {
            $relative_path_to_analyze = FileRef::getProjectRelativePathForPath($path_to_analyze);
            if (!\in_array($uri, $file_path_list) && !\in_array($relative_path_to_analyze, $file_path_list)) {
                Logger::logInfo("Path '$relative_path_to_analyze' (URI '$uri') not in parse list, skipping");
                continue;
            }
            $filtered_uris_to_analyze[$relative_path_to_analyze] = $uri;
        }
        return $filtered_uris_to_analyze;
    }

    /**
     * @return void
     */
    private function finalizeAnalyzingURIs()
    {
        $uris_to_analyze = $this->getFilteredURIsToAnalyze();
        if (\count($uris_to_analyze) === 0) {
            return;
        }

        // Add anything that's open in the IDE to the uris to analyze.
        // In the future, this behavior may be configurable.
        foreach ($this->file_mapping->getOverrides() as $path => $_) {
            if (!isset($uris_to_analyze[$path])) {
                $uris_to_analyze[$path] = $this->file_mapping->getURIForPath($path);
            }
        }

        if (Config::getValue('language_server_use_pcntl_fallback')) {
            $this->finishAnalyzingURIsWithoutPcntl($uris_to_analyze);
            return;
        }

        // TODO: check if $path_to_analyze can be analyzed first.
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!$sockets) {
            error_log("unable to create stream socket pair");
            exit(EXIT_FAILURE);
        }

        $this->most_recent_request = null;

        $pid = pcntl_fork();
        if ($pid < 0) {
            error_log(posix_strerror(posix_get_last_error()));
            exit(EXIT_FAILURE);
        }

        // Parent
        // FIXME: make this async as well, and rate limit it.
        if ($pid > 0) {
            $read_stream = self::streamForParent($sockets);
            $concatenated = '';
            while (!feof($read_stream)) {
                $buffer = fread($read_stream, 1024);
                if (strlen($buffer) > 0) {
                    $concatenated .= $buffer;
                }
            }
            $json_contents = json_decode($concatenated, true);
            if (!\is_array($json_contents)) {
                Logger::logInfo("Fetched non-json: " . $concatenated);
                return;
            }
            $this->handleJSONResponseFromWorker($uris_to_analyze, $json_contents);
            return;
        }

        $child_stream = self::streamForChild($sockets);
        $paths_to_analyze = array_keys($uris_to_analyze);
        $this->most_recent_request = Request::makeLanguageServerAnalysisRequest(
            new StreamResponder($child_stream, false),
            $paths_to_analyze,
            $this->code_base,
            $this->file_path_lister,
            $this->file_mapping,
            true  // We are the fork. Call exit() instead of throwing ExitException
        );
        // FIXME update the parsed file lists before and after (e.g. add to analyzeURI). See Daemon\Request::accept()
        //    TODO: refactor accept() to make it easier to work with.
        // TODO: add unit tests

        $this->protocolReader->stopAcceptingNewRequests();
        $this->is_accepting_new_requests = false;
        Loop\stop();  // abort the loop (without closing streams?)
    }

    /**
     * @param array<string,string> $uris_to_analyze
     * @return void
     *
     * @suppress PhanAccessMethodInternal
     */
    private function finishAnalyzingURIsWithoutPcntl(array $uris_to_analyze)
    {
        $paths_to_analyze = array_keys($uris_to_analyze);
        // When there is no pcntl:
        // Create a fake request object.
        // Instead of stopping the loop, keep going with the loop and keep accepting the requests
        $responder = new CapturerResponder([]);
        $code_base = $this->code_base;

        $analysis_request = Request::makeLanguageServerAnalysisRequest(
            $responder,
            $paths_to_analyze,
            $code_base,
            $this->file_path_lister,
            $this->file_mapping,
            false  // We aren't forking. Throw ExitException instead of calling exit()
        );

        $analyze_file_path_list = $analysis_request->filterFilesToAnalyze($this->code_base->getParsedFilePathList());
        if (count($analyze_file_path_list) === 0) {
            // Nothing to do, don't start analysis
            return;
        }

        // Do this before we stop tracking undo operations.
        $temporary_file_mapping = $analysis_request->getTemporaryFileMapping();

        $restore_point = $code_base->createRestorePoint();

        // Stop tracking undo operations, now that the parse phase is done.
        // This is re-enabled in restoreFromRestorePoint
        $code_base->disableUndoTracking();

        Phan::setPrinter($analysis_request->getPrinter());

        try {
            Phan::finishAnalyzingRemainingStatements($this->code_base, $analysis_request, $analyze_file_path_list, $temporary_file_mapping);
        } catch (ExitException $e) {
            // This is normal, do nothing
        }

        $response_data = $responder->getResponseData();
        if (!$response_data) {
            // Something is probably broken if we don't get response data
            // But just in case we can recover, restore this.
            $code_base->restoreFromRestorePoint($restore_point);
            throw new \RuntimeException("Failed to get a response from a worker");
        }

        // Send a response with diagnostics to the language server client.
        // It should be slightly faster to send a response
        // if the language server sends data before restoring the state of the codebase.
        // (Transforming the JSON response does not depend on the $code_base object)
        $this->handleJSONResponseFromWorker($uris_to_analyze, $response_data);

        $code_base->restoreFromRestorePoint($restore_point);

        Logger::logInfo("Response from non-pcntl server: " . json_encode($response_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string,string> $uris_to_analyze
     * @param array{issues:array} $response_data
     * @return void
     */
    private function handleJSONResponseFromWorker(array $uris_to_analyze, array $response_data) {
        if (!\array_key_exists('issues', $response_data)) {
            Logger::logInfo("Failed to fetch 'issues' from JSON:" . json_encode($response_data));
            return;
        }
        $diagnostics = [];
        // Normalize the uri so that it will be the same as URIs phan would send for diagnostics.
        // E.g. "file:///path/path%.php" will be normalized to "file:///path/path%25.php"
        foreach ($uris_to_analyze as $uri) {
            $normalized_requested_uri = Utils::pathToUri(Utils::uriToPath($uri));
            $diagnostics[$normalized_requested_uri] = [];  // send an empty diagnostic list on failure.
        }

        foreach ($response_data['issues'] ?? [] as $issue) {
            list($issue_uri, $diagnostic) = self::generateDiagnostic($issue);
            if ($diagnostic instanceof Diagnostic) {
                $diagnostics[$issue_uri][] = $diagnostic;
            }
        }
        foreach ($diagnostics as $diagnostics_uri => $diagnostics_list) {
            $this->client->textDocument->publishDiagnostics($diagnostics_uri, $diagnostics_list);
        }
    }

    /**
     * @param array $issue
     * @return null[]|string[]|Diagnostic[] - On success, returns [string $uri, Diagnostic $diagnostic]
     */
    private static function generateDiagnostic($issue) : array
    {
        if ($issue['type'] !== 'issue') {
            return [null, null];
        }
        //$check_name = $issue['check_name'];
        $description = $issue['description'];
        $severity = $issue['severity'];
        $path = Config::projectPath($issue['location']['path']);
        $issue_uri = Utils::pathToUri($path);
        $start_line = $issue['location']['lines']['begin'];
        $start_line = max($start_line, 1);
        // If we ever supported end_line:
        // $end_line = $issue['location']['lines']['end'] ?? $start_line;
        // $end_line = max($end_line, 1);
        // Language server has 0 based lines and columns, phan has 1-based lines and columns.
        $range = new Range(new Position($start_line - 1, 0), new Position($start_line, 0));
        switch ($severity) {
            case Issue::SEVERITY_LOW:
                $diagnostic_severity = DiagnosticSeverity::INFORMATION;
                break;
            case Issue::SEVERITY_NORMAL:
                $diagnostic_severity = DiagnosticSeverity::WARNING;
                break;
            case Issue::SEVERITY_CRITICAL:
            default:
                $diagnostic_severity = DiagnosticSeverity::ERROR;
                break;
        }
        // TODO: copy issue code in 'json' format
        return [$issue_uri, new Diagnostic($description, $range, $issue['type_id'], $diagnostic_severity, 'Phan')];
    }

    /**
     * Prepare the socket pair to be used in a parent process and
     * return the stream the parent will use to read results.
     *
     * @param resource[] $sockets the socket pair for IPC
     * @return resource
     */
    private static function streamForParent(array $sockets)
    {
        list($for_read, $for_write) = $sockets;

        // The parent will not use the write channel, so it
        // must be closed to prevent deadlock.
        fclose($for_write);

        // stream_select will be used to read multiple streams, so these
        // must be set to non-blocking mode.
        if (!stream_set_blocking($for_read, false)) {
            error_log('unable to set read stream to non-blocking');
            exit(EXIT_FAILURE);
        }

        return $for_read;
    }

    /**
     * Prepare the socket pair to be used in a child process and return
     * the stream the child will use to write results.
     *
     * @param resource[] $sockets the socket pair for IPC.
     * @return resource
     */
    private static function streamForChild(array $sockets)
    {
        list($for_read, $for_write) = $sockets;

        // The while will not use the read channel, so it must
        // be closed to prevent deadlock.
        fclose($for_read);
        return $for_write;
    }


    /**
     * The initialize request is sent as the first request from the client to the server.
     *
     * @param ClientCapabilities $capabilities The capabilities provided by the client (editor)
     * @param string|null $rootPath The rootPath of the workspace. Is null if no folder is open.
     * @param int|null $processId The process Id of the parent process that started the server. Is null if the process has not been started by another process. If the parent process is not alive then the server should exit (see exit notification) its process.
     * @return Promise <InitializeResult>
     */
    public function initialize(ClientCapabilities $capabilities, string $rootPath = null, int $processId = null): Promise
    {
        return coroutine(function () : \Generator {
            // Eventually, this might block on something. Leave it as a generator.
            if (false) {
                yield;
            }

            // There would be an asynchronous indexing step, but the startup already did the indexing.
            if ($this->textDocument === null) {
                $this->textDocument = new TextDocument(
                    $this->client,
                    $this,
                    $this->file_mapping
                );
            }

            $serverCapabilities = new ServerCapabilities();

            // FULL: Ask the client to return always return full documents (because we need to rebuild the AST from scratch)
            // NONE: Don't sync until the user explitly saves a document.
            $serverCapabilities->textDocumentSync = $this->makeTextDocumentSyncOptions();

            // TODO: Support "Find all symbols"?
            //$serverCapabilities->documentSymbolProvider = true;
            // TODO: Support "Find all symbols in workspace"?
            //$serverCapabilities->workspaceSymbolProvider = true;
            // XXX do this next?
            // TODO: Support "Go to definition" (reasonably practical, should be able to infer types in many cases)
            // $serverCapabilities->definitionProvider = false;
            // TODO: (probably impractical, slow) Support "Find all references"? (We don't track this, except when checking for dead code elimination possibilities.
            // $serverCapabilities->referencesProvider = false;
            // Can't support "Hover" without phpdoc for internal functions, such as those from phpstorm
            // Also redundant if php.
            // $serverCapabilities->hoverProvider = false;
            // XXX support completion next?
            // Requires php-parser-to-php-ast (or tolerant php-parser)
            // Support "Completion"
            // $serverCapabilities->completionProvider = new CompletionOptions;
            // $serverCapabilities->completionProvider->resolveProvider = false;
            // $serverCapabilities->completionProvider->triggerCharacters = ['$', '>'];
            // Can't support global references at the moment, I think.
            //$serverCapabilities->xworkspaceReferencesProvider = true;
            //$serverCapabilities->xdefinitionProvider = true;
            //$serverCapabilities->xdependenciesProvider = true;

            return new InitializeResult($serverCapabilities);
        });
    }

    private function makeTextDocumentSyncOptions() : TextDocumentSyncOptions
    {
        $textDocumentSyncOptions = new TextDocumentSyncOptions();
        $textDocumentSyncOptions->openClose = true;
        $textDocumentSyncOptions->change = Config::getValue('language_server_analyze_only_on_save') ? TextDocumentSyncKind::NONE : TextDocumentSyncKind::FULL;

        $saveOptions = new SaveOptions();
        $saveOptions->includeText = true;
        $textDocumentSyncOptions->save = $saveOptions;
        return $textDocumentSyncOptions;
    }

    public function initialized()
    {
        Logger::logInfo("Called initialized on language server, currently a no-op");
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
     * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
     * asks the server to exit.
     *
     * @return void
     */
    public function shutdown()
    {
        // TODO: Does phan need to do anything else except respond?
        Logger::logInfo("Called shutdown on language server");
    }

    /**
     * A notification to ask the server to exit its process.
     *
     * @return void
     */
    public function exit()
    {
        // This is handled by the main process. No forks are aftive.
        Logger::logInfo("Called exit on language server");
        exit(0);
    }
}
