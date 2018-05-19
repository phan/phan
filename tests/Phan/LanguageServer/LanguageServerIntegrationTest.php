<?php declare(strict_types = 1);
namespace Phan\Tests\LanguageServer;

use Phan\Tests\BaseTest;

use Phan\Issue;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\Position;
use Phan\LanguageServer\Protocol\TextDocumentIdentifier;
use Phan\LanguageServer\ProtocolStreamReader;
use Phan\LanguageServer\Utils;
use InvalidArgumentException;
use stdClass;

/**
 * Integration Tests of functionality of the Language Server.
 *
 * Note: This test file is not enabled in CI because they may hang indefinitely.
 * (integration test timeouts weren't implemented or tested yet).
 */
class LanguageServerIntegrationTest extends BaseTest
{
    // Uncomment to enable debug logging within this test.
    // There are separate config settings to make the language server emit debug messages.
    const DEBUG_ENABLED = false;

    public static function getLSPFolder() : string {
        return dirname(dirname(__DIR__)) . '/misc/lsp';
    }

    public static function getLSPPath() : string {
        return self::getLSPFolder() . '/src/example.php';
    }

    // Incrementing message id for language client requests.
    // Each test case has its own instance property $this->messageId
    private $messageId = 0;

    /**
     * @return array{0:resource,1:resource,2:resource} [$proc, $proc_in, $proc_out]
     */
    private function createPhanDaemon(bool $pcntlEnabled)
    {
        if (getenv('PHAN_RUN_INTEGRATION_TEST') != '1') {
            $this->markTestSkipped('skipping integration tests - set PHAN_RUN_INTEGRATION_TEST=1 to allow');
        }
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open not available');
        }

        if ($pcntlEnabled && !function_exists('pcntl_fork')) {
            $this->markTestSkipped('requires pcntl extension');
        }
        $command = sprintf(
            '%s -d %s --quick --language-server-on-stdin --language-server-enable-go-to-definition %s',
            escapeshellarg(__DIR__ . '/../../../phan'),
            escapeshellarg(self::getLSPFolder()),
            ($pcntlEnabled ? '' : '--language-server-force-missing-pcntl')
        );
        $proc = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => STDERR,  // Pass stderr from this process directly to output stderr so it doesn't get buffered up or ignored
            ],
            $pipes
        );
        list($proc_in, $proc_out) = $pipes;
        $this->debugLog("Created a process\n");
        return [
            $proc,
            $proc_in,
            $proc_out,
        ];
    }

    /**
     * @dataProvider pcntlEnabledProvider
     */
    public function testInitialize(bool $pcntlEnabled)
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list ($proc, $proc_in, $proc_out) = $this->createPhanDaemon($pcntlEnabled);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            fclose($proc_in);
            // TODO: Make these pipes async if they aren't already
            $unread_contents = fread($proc_out, 10000);
            $this->assertSame('', $unread_contents);
            fclose($proc_out);
            proc_close($proc);
        }
    }

    /**
     * @dataProvider pcntlEnabledProvider
     */
    public function testGenerateDiagnostics(bool $pcntlEnabled)
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanDaemon($pcntlEnabled);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $new_file_contents = <<<'EOT'
<?php
function example(int $x) : int {
    echo strlen($x);
}
EOT;
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $new_file_contents);
            $diagnostics_response = $this->awaitResponse($proc_out);
            $this->assertSame('textDocument/publishDiagnostics', $diagnostics_response['method']);
            $uri = $diagnostics_response['params']['uri'];
            $this->assertSame($uri, $this->getDefaultFileURI());
            $diagnostics = $diagnostics_response['params']['diagnostics'];
            $this->assertCount(2, $diagnostics);
            // TODO: Pass IssueInstance to the helper instead?
            $this->assertSameDiagnostic($diagnostics[0], Issue::TypeMissingReturn, 1, 'Method \example is declared to return int but has no return value');
            $this->assertSameDiagnostic($diagnostics[1], Issue::TypeMismatchArgumentInternal, 2, 'Argument 1 (string) is int but \strlen() takes string');

            $good_file_contents = <<<'EOT'
<?php
function example(int $x) : int {
    return $x * 2;
}
EOT;
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $good_file_contents);
            $this->assertHasEmptyPublishDiagnosticsResponse($proc_out);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            fclose($proc_in);
            // TODO: Make these pipes async if they aren't already
            $unread_contents = fread($proc_out, 10000);
            $this->assertSame('', $unread_contents);
            fclose($proc_out);
            proc_close($proc);
        }
    }

    public function testDefinitionInSameFile()
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanDaemon(true);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $new_file_contents = <<<'EOT'
<?php  // line 0
class MyExample {
    const MyConst = 2;
}
echo MyExample::MyConst;  // line 4
EOT;
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $new_file_contents);
            $this->assertHasEmptyPublishDiagnosticsResponse($proc_out);

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $definition_response = $this->writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, new Position(4, 6));

            $this->assertSame([
                'result' => [
                    [
                        'uri' => $this->getDefaultFileURI(),
                        'range' => [
                            'start' => ['line' => 1, 'character' => 0],
                            'end'   => ['line' => 2, 'character' => 0],
                        ],
                    ],
                ],
                'id' => 2,
                'jsonrpc' => '2.0',
            ], $definition_response);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            fclose($proc_in);
            // TODO: Make these pipes async if they aren't already
            $unread_contents = fread($proc_out, 10000);
            $this->assertSame('', $unread_contents);
            fclose($proc_out);
            proc_close($proc);
        }
    }

    /**
     * @param int $expected_definition_line 0-based line number
     * @param ?int $expected_definition_line null for nothing
     *
     * @dataProvider definitionInOtherFileProvider
     */
    public function testDefinitionInOtherFile(string $new_file_contents, Position $position, string $expected_definition_uri, $expected_definition_line)
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanDaemon(true);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $new_file_contents);
            $this->assertHasEmptyPublishDiagnosticsResponse($proc_out);

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $definition_response = $this->writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, $position);

            if ($expected_definition_line !== null) {
                $expected_definitions = [
                    [
                        'uri' => $expected_definition_uri,
                        'range' => [
                            'start' => ['line' => $expected_definition_line, 'character' => 0],
                            'end'   => ['line' => $expected_definition_line + 1, 'character' => 0],
                        ],
                    ],
                ];
            } else {
                $expected_definitions = null;
            }

            $expected_definition_response = [
                'result' => $expected_definitions,
                'id' => 2,
                'jsonrpc' => '2.0',
            ];

            $cur_line = explode("\n", $new_file_contents)[$position->line] ?? '';

            $message = "Unexpected definition for {$position->line}:{$position->character} (0-based) on line " . json_encode($cur_line);
            $this->assertEquals($expected_definition_response, $definition_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_definition_response, $definition_response, $message);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            fclose($proc_in);
            // TODO: Make these pipes async if they aren't already
            $unread_contents = fread($proc_out, 10000);
            $this->assertSame('', $unread_contents);
            fclose($proc_out);
            proc_close($proc);
        }
    }

    public function definitionInOtherFileProvider() : array {
        // Refers to elements defined in ../../misc/lsp/src/definitions.php
        $example_file = <<<'EOT'
<?php  // line 0
function example() {
    echo MyClass::$my_static_property;
    echo MyClass::MyClassConst;
    echo MyClass::myMethod();
    my_global_function();  // line 5
    $v = new MyClass();
    $v->myInstanceMethod();
    $a = $v->other_class;
    echo MY_GLOBAL_CONST;
    'my_global_function'();  // line 10
    echo MyClass::class;
}
EOT;
        $definitions_file_uri = Utils::pathToUri(self::getLSPFolder() . '/src/definitions.php');
        return [
            [
                $example_file,
                new Position(2, 21),  // my_static_property
                $definitions_file_uri,
                11,
            ],
            [
                $example_file,
                new Position(3, 21),  // MyClassConst
                $definitions_file_uri,
                10,
            ],
            [
                $example_file,
                new Position(4, 21),  // myMethod
                $definitions_file_uri,
                13,
            ],
            [
                $example_file,
                new Position(5, 21),  // my_global_function
                $definitions_file_uri,
                2,
            ],
            [
                $example_file,
                new Position(5, 4),  // my_global_function
                $definitions_file_uri,
                2,
            ],
            [
                $example_file,
                new Position(5, 3),  // my_global_function
                $definitions_file_uri,
                null,
            ],
            [
                $example_file,
                new Position(6, 15),  // MyClass or the constructor
                $definitions_file_uri,
                9,
            ],
            [
                $example_file,
                new Position(7, 9),  // myInstanceMethod
                $definitions_file_uri,
                16,
            ],
            [
                $example_file,
                new Position(9, 10),  // MY_GLOBAL_CONST
                $definitions_file_uri,
                24,  // Place where the global constant was defined (Currently the class definition)
            ],
            [
                $example_file,
                new Position(10, 6),  // my_global_function (alternative syntax)
                $definitions_file_uri,
                2,
            ],
            [
                $example_file,
                new Position(11, 20),  // MyClass::class (Points to MyClass)
                $definitions_file_uri,
                9,
            ],
        ];
    }

    /**
     * @param resource $proc_out
     * @return void
     */
    private function assertHasEmptyPublishDiagnosticsResponse($proc_out)
    {
        $diagnostics_response = $this->awaitResponse($proc_out);
        $this->assertSame('textDocument/publishDiagnostics', $diagnostics_response['method']);
        $uri = $diagnostics_response['params']['uri'];
        $this->assertSame($uri, $this->getDefaultFileURI());
        $diagnostics = $diagnostics_response['params']['diagnostics'];
        $this->assertSame([], $diagnostics);
    }

    public function pcntlEnabledProvider() : array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * @return void
     */
    private function assertSameDiagnostic(array $diagnostic, string $issue_type, int $expected_lineno, string $message)
    {
        $issue = Issue::fromType($issue_type);

        $expected_message = sprintf(
            '%s %s %s',
            $issue->getCategoryName(),
            $issue->getType(),
            $message
        );
        $expected_diagnostic = [
            'range' => [
                'start' => [
                    'line' => $expected_lineno,
                    'character' => 0,
                ],
                'end' => [
                    'line' => $expected_lineno + 1,
                    'character' => 0,
                ],
            ],
            'severity' => LanguageServer::diagnosticSeverityFromPhanSeverity($issue->getSeverity()),
            'code' => $issue->getTypeId(),
            'source' => 'Phan',
            'message' => $expected_message,
        ];
        // assertEquals has a better diff view than assertSame, so run it first.
        $this->assertEquals($expected_diagnostic, $diagnostic);
        $this->assertSame($expected_diagnostic, $diagnostic);
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeInitializeRequestAndAwaitResponse($proc_in, $proc_out)
    {
        $params = [
            'capabilities' => new ClientCapabilities(),
            'rootPath' => '/ignored',
            'processId' => getmypid(),
        ];
        $this->writeMessage($proc_in, 'initialize', $params);
        $response = $this->awaitResponse($proc_out);
        $expected_response = [
            'result' => [
                'capabilities' => [
                    'textDocumentSync' => [
                        'openClose' => true,
                        'change' => 1,
                        'willSave' => null,
                        'willSaveWaitUntil' => null,
                        'save' => ['includeText' => true],
                    ],
                    'definitionProvider' => true,
                ]
            ],
            'id' => 1,
            'jsonrpc' => '2.0'
        ];
        $this->assertSame($expected_response, $response);
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return array the response
     * @throws InvalidArgumentException
     */
    private function writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, Position $position)
    {
        // Implementation detail: We simultaneously emit a notification with new diagnostics
        // and the response for the definition request at the same time, even if files didn't change.

        // NOTE: That could probably be refactored, but there's not much benefit to doing that.
        $params = [
            'textDocument' => new TextDocumentIdentifier($this->getDefaultFileURI()),
            'position' => $position,
        ];
        $this->writeMessage($proc_in, 'textDocument/definition', $params);
        $this->assertHasEmptyPublishDiagnosticsResponse($proc_out);

        $response = $this->awaitResponse($proc_out);

        return $response;
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeShutdownRequestAndAwaitResponse($proc_in, $proc_out)
    {
        $params = new stdClass();
        $this->writeMessage($proc_in, 'shutdown', $params);
        $response = $this->awaitResponse($proc_out);
        $expected_response = [
            'result' => null,
            'id' => $this->messageId,
            'jsonrpc' => '2.0'
        ];
        $this->assertSame($expected_response, $response);
    }

    /**
     * @param resource $proc_in
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeInitializedNotification($proc_in)
    {
        $params = [
            'capabilities' => new stdClass(),
            'rootPath' => '/ignored',
            'processId' => getmypid(),
        ];
        $this->writeNotification($proc_in, 'initialized', $params);
    }

    /**
     * @param resource $proc_in
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeExitNotification($proc_in)
    {
        $this->writeNotification($proc_in, 'exit', null);
    }

    /**
     * @param resource $proc_in
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeDidChangeNotificationToDefaultFile($proc_in, string $new_contents)
    {
        $params = [
            'textDocument' => ['uri' => $this->getDefaultFileURI()],
            'contentChanges' => [
                [
                    'text' => $new_contents,
                ]
            ],
        ];
        $this->writeNotification($proc_in, 'textDocument/didChange', $params);
    }

    private function getDefaultFileURI() : string
    {
        return Utils::pathToUri(self::getLSPPath());
    }

    /**
     * @param resource $proc_out
     * Based on ProtocolStreamReader::readMessages()
     * TODO: Add timeout logic, etc.
     * @suppress PhanPluginUnusedVariable $parsing_mode
     * @return array
     */
    private function awaitResponse($proc_out) : array
    {
        $buffer = '';
        $content_length = 0;
        $headers = [];
        '@phan-var array<string,string> $headers';
        $c = false;
        $parsing_mode = ProtocolStreamReader::PARSE_HEADERS;
        while (($c = fgetc($proc_out)) !== false && $c !== '') {
            $buffer .= $c;
            switch ($parsing_mode) {
                case ProtocolStreamReader::PARSE_HEADERS:
                    if ($buffer === "\r\n") {
                        $parsing_mode = ProtocolStreamReader::PARSE_BODY;
                        $content_length = (int)$headers['Content-Length'];
                        if (!$content_length) {
                            throw new InvalidArgumentException('Failed to read json. Response headers: ' . json_encode($headers));
                        }
                        $buffer = '';
                    } elseif (substr($buffer, -2) === "\r\n") {
                        $parts = explode(':', $buffer);
                        $headers[$parts[0]] = trim($parts[1]);
                        $buffer = '';
                    }
                    break;
                case ProtocolStreamReader::PARSE_BODY:
                    if (strlen($buffer) === $content_length) {
                        // If we fork, don't read any bytes in the input buffer from the worker process.
                        $result = json_decode($buffer, true);
                        if (!is_array($result)) {
                            throw new InvalidArgumentException("Invalid decoded buffer: value=$buffer");
                        }
                        return $result;
                    }
                    break;
            }
        }
        throw new InvalidArgumentException('Failed to read a full response: ' . json_encode($buffer));
        // TODO: parse headers and body the same way the language client does
    }

    /**
     * @param resource $proc_in
     * @param string $method
     * @param array|stdClass $params
     */
    private function writeMessage($proc_in, string $method, $params)
    {
        $body = [
            'jsonrpc' => '2.0',
            'id' => ++$this->messageId,
            'method' => $method,
            'params' => $params,
        ];
        $this->writeEncodedBody($proc_in, $body);
        $this->debugLog("Wrote a message method=$method\n");
    }


    /**
     * @param resource $proc_in
     * @param string $method
     * @param ?array|?\stdClass $params
     */
    private function writeNotification($proc_in, string $method, $params)
    {
        $body = [
            'method' => $method,
            'params' => $params,
        ];
        $this->writeEncodedBody($proc_in, $body);
        $this->debugLog("Wrote a $method notification\n");
    }

    private function debugLog(string $message)
    {
        if (self::DEBUG_ENABLED) {
            echo $message;
            flush();
            ob_flush();
        }
    }
    // TODO: Test the ability to create a Request

    /**
     * @param resource $proc_in
     * @param array<string,mixed> $body
     * @return void
     */
    private function writeEncodedBody($proc_in, array $body)
    {
        $body_raw = json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\r\n";
        $raw = sprintf(
            "Content-Length: %d\r\nContent-Type: application/vscode-jsonrpc; charset=utf-8\r\n\r\n%s",
            strlen($body_raw),
            $body_raw
        );
        fwrite($proc_in, $raw);
    }
}
