<?php declare(strict_types=1);

namespace Phan\Tests\LanguageServer;

use InvalidArgumentException;
use Phan\Issue;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\CompletionItemKind;
use Phan\LanguageServer\Protocol\CompletionTriggerKind;
use Phan\LanguageServer\Protocol\MarkupContent;
use Phan\LanguageServer\Protocol\Position;
use Phan\LanguageServer\Protocol\TextDocumentIdentifier;
use Phan\LanguageServer\ProtocolStreamReader;
use Phan\LanguageServer\Utils;
use Phan\Tests\BaseTest;
use RuntimeException;
use stdClass;

/**
 * Integration Tests of functionality of the Language Server.
 *
 * Note: This test file is not enabled in CI because they may hang indefinitely.
 * (integration test timeouts weren't implemented or tested yet).
 *
 * @phan-file-suppress PhanThrowTypeAbsent it's a test
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
final class LanguageServerIntegrationTest extends BaseTest
{
    // Uncomment to enable debug logging within this test.
    // There are separate config settings to make the language server emit debug messages.
    const DEBUG_ENABLED = false;

    /**
     * Returns the path of the folder used for these integration tests
     */
    public static function getLSPFolder() : string
    {
        return dirname(dirname(__DIR__)) . '/misc/lsp';
    }

    /**
     * Returns the path of the file being analyzed.
     * This has elements that the language server will return Positions of in some of the tests.
     *
     * The contents of this file will be "edited" (without changing the file on disk) by the mocked client.
     */
    public static function getLSPPath() : string
    {
        return self::getLSPFolder() . '/src/example.php';
    }

    /**
     * Incrementing message id for language client requests.
     * Each test case has its own instance property $this->messageId
     * @var int
     */
    private $messageId = 0;

    /**
     * @param array{vscode_compatible_completions?:bool} $option_array
     * @return array{0:resource,1:resource,2:resource} [$proc, $proc_in, $proc_out]
     */
    private function createPhanLanguageServer(bool $pcntlEnabled, bool $prefer_stdio = true, array $option_array = [])
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
        $is_windows = DIRECTORY_SEPARATOR === "\\";
        if ($is_windows) {
            // Work around 'The filename, directory name, or volume label syntax is incorrect.', include the path to the PHP binary used to run this test.
            // Might not work with file names including spaces?
            // @see InvokePHPNativeSyntaxCheckPlugin

            $escaped_command = PHP_BINARY . " " . escapeshellarg(__DIR__ . '/../../../src/phan.php');
            // XXX create an OOP language client abstraction for this test, with shutdown() methods
            $use_stdio = false;
        } else {
            $escaped_command = escapeshellarg(__DIR__ . '/../../../phan');
            // Most of the tests for unix/linux will use stdio - A tiny number will use TCP
            // to properly test that TCP is working.
            $use_stdio = $prefer_stdio;
        }
        if ($use_stdio) {
            $options = '--language-server-on-stdin';
        } else {
            $address = '127.0.0.1:14846';
            $options = '--language-server-tcp-connect ' . $address;

            $tcpServer = stream_socket_server('tcp://' . $address, $errno, $errstr);
            if ($tcpServer === false) {
                $this->fail("Could not listen on $address. Error $errno\n$errstr");
            }
        }
        if ($option_array['vscode_compatible_completions'] ?? false) {
            $options = "$options --language-server-completion-vscode";
        }
        $command = sprintf(
            '%s -d %s --quick --use-fallback-parser %s --language-server-enable-hover --language-server-enable-completion --language-server-enable-go-to-definition %s',
            $escaped_command,
            escapeshellarg(self::getLSPFolder()),
            $options,
            ($pcntlEnabled ? '' : '--language-server-force-missing-pcntl')
        );
        if ($use_stdio) {
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
        } else {
            $proc = proc_open(
                $command,
                [
                    1 => STDERR,
                    2 => STDERR,  // Pass stderr from this process directly to output stderr so it doesn't get buffered up or ignored
                ],
                $pipes
            );
            if (!$proc) {
                throw new RuntimeException("Failed to create a proc");
            }
            '@phan-var-force resource $tcpServer';
            $socket = stream_socket_accept($tcpServer, 5);
            if (!$socket) {
                proc_close($proc);
                throw new RuntimeException("Failed to receive a connection from language server in 5 seconds");
            }
            // Don't set this to async - the rest of this test assumes synchronous streams.
            // stream_set_blocking($socket, false);
            $proc_in = $socket;
            $proc_out = $socket;
        }
        $this->debugLog("Created a process\n");
        return [
            $proc,
            $proc_in,
            $proc_out,
        ];
    }

    public function initializeProvider() : array
    {
        $results = [
            [false, true],
            [true, true],
        ];
        if (DIRECTORY_SEPARATOR !== "\\") {
            $results[] = [true, false];
        }

        return $results;
    }

    /**
     * @dataProvider initializeProvider
     */
    public function testInitialize(bool $pcntlEnabled, bool $prefer_stdio)
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntlEnabled, $prefer_stdio);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    /**
     * @param resource $proc result of proc_open
     * @param resource $proc_in input stream
     * @param resource $proc_out output stream
     */
    private function performCleanLanguageServerShutdown($proc, $proc_in, $proc_out)
    {
        try {
            // TODO: Make these pipes async if they aren't already
            if ($proc_in === $proc_out) {
                // This is synchronous TCP
                $unread_contents = fread($proc_out, 10000);
                $this->assertSame('', $unread_contents);
                fclose($proc_in);
            } else {
                // this is stdio
                fclose($proc_in);
                $unread_contents = fread($proc_out, 10000);
                $this->assertSame('', $unread_contents);
                fclose($proc_out);
            }
        } finally {
            proc_close($proc);
        }
    }

    /**
     * @dataProvider pcntlEnabledProvider
     */
    public function testGenerateDiagnostics(bool $pcntlEnabled)
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntlEnabled);
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
            $this->assertHasEmptyPublishDiagnosticsNotification($proc_out);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    public function testDefinitionInSameFile()
    {
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer(true);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $new_file_contents = <<<'EOT'
<?php namespace { // line 0
class MyExample {
    public function __construct() {}
    const MyConst = 2;
}
echo MyExample::MyConst;  // line 5
$x = new MyExample();
echo MyExample::class;
class MyExampleWithoutConstructor { }
$y = new MyExampleWithoutConstructor();
// Some comment referring to \MyExample  at line 10
function my_other_global_function() {}
my_other_global_function();
// Some comment referring to \\\my_other_global_function() - Current implementation only works when followed by a node
// Should not crash if there are too many backslashes
// line 15 - Can refer to constant MY_GLOBAL_CONSTANT
const MY_GLOBAL_CONSTANT = [2,3];
$z = MY_GLOBAL_CONSTANT;



}
// line 20
namespace Ns {
}
EOT;
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $new_file_contents);
            $this->assertHasEmptyPublishDiagnosticsNotification($proc_out);

            $id = 2;
            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $assert_has_definition = function (Position $position, int $line) use ($proc_in, $proc_out, &$id) {
                $definition_response = $this->writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, $position);
                $this->assertSame([
                    'result' => [
                        [
                            'uri' => $this->getDefaultFileURI(),
                            'range' => [
                                'start' => ['line' => $line,     'character' => 0],
                                'end'   => ['line' => $line + 1, 'character' => 0],
                            ],
                        ],
                    ],
                    'id' => $id++,
                    'jsonrpc' => '2.0',
                ], $definition_response, "Unexpected result at $position");
            };

            $assert_has_definition(new Position(5, 6), 1);
            $assert_has_definition(new Position(5, 15), 3);
            // new MyExample() gives location of MyExample::__construct at "new"
            $assert_has_definition(new Position(6, 5), 2);
            // new MyExample() gives location of MyExample::__construct at "MyExample"
            $assert_has_definition(new Position(6, 17), 2);
            // Foo::class gives location of "class Foo"
            $assert_has_definition(new Position(7, 17), 1);
            // new MyExampleWithoutConstructor() gives the location of "class MyExampleWithoutConstructor"
            $assert_has_definition(new Position(9, 9), 8);
            // Referring to a class in a comment works.
            $assert_has_definition(new Position(10, 31), 1);
            // A function call can be located
            $assert_has_definition(new Position(12, 0), 11);
            // A function name in a comment can be located
            $assert_has_definition(new Position(13, 32), 11);
            // A global constant name can be located (in comments and code)
            $assert_has_definition(new Position(15, 50), 16);
            $assert_has_definition(new Position(17, 5), 16);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    /**
     * Tests the completion provider for the given $position with pcntl enabled or disabled
     */
    public function runTestCompletionWithPcntlSetting(
        Position $position,
        array $expected_completions,
        bool $for_vscode,
        string $file_contents,
        bool $pcntl_enabled
    ) {
        $this->messageId = 0;
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntl_enabled, true, ['vscode_compatible_completions' => $for_vscode]);
        try {
            /*
            // This block can be uncommented when developing tests for completions
            $line_contents = explode("\n", $file_contents)[$position->line];
            $completion_cursor = substr($line_contents, 0, $position->character) . '<>' . substr($line_contents, $position->character);
            fwrite(STDERR, "Checking at $completion_cursor\n");
             */

            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeDidChangeNotificationToDefaultFile($proc_in, $file_contents);
            $this->assertHasNonEmptyPublishDiagnosticsNotification($proc_out);

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            // TODO: Should I shift this back a character in the request?
            $completion_response = $this->writeCompletionRequestAndAwaitResponse($proc_in, $proc_out, $position);

            $expected_completion_response = [
                'result' => [
                    'isIncomplete' => false,
                    'items' => $expected_completions,
                ],
                'id' => 2,
                'jsonrpc' => '2.0',
            ];
            $this->assertEquals($expected_completion_response, $completion_response, "Failed completions at $position->line:$position->character");
            $this->assertSame($expected_completion_response, $completion_response);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    private function runTestCompletionWithAndWithoutPcntl(Position $position, array $expected_completions, bool $for_vscode, string $file_contents)
    {
        if (function_exists('pcntl_fork')) {
            $this->runTestCompletionWithPcntlSetting($position, $expected_completions, $for_vscode, $file_contents, true);
        }
        $this->runTestCompletionWithPcntlSetting($position, $expected_completions, $for_vscode, $file_contents, false);
    }

    /**
     * @dataProvider completionBasicProvider
     */
    public function testCompletionBasic(Position $position, array $expected_completions, bool $for_vscode = false)
    {
        $this->runTestCompletionWithAndWithoutPcntl($position, $expected_completions, $for_vscode, self::COMPLETION_BASIC_FILE_CONTENTS);
    }

    // Here, we use a prefix of M9 to avoid suggesting MYSQLI_...
    const COMPLETION_BASIC_FILE_CONTENTS = <<<'EOT'
<?php namespace { // line 0
class M9Example {
    public static $myVar = 2;
    public $myInstanceVar = 3;
    public static function my_static_function () {}
    const my_class_const = ['literalString'];  // line 5
}



echo M9Example::$  // line 10
echo M9Example::$my
echo M9Example::
;

function M9GlobalFunction() : array {  // line 15
    return [];
}
const M9GlobalConst = 42;
define('M9OtherGlobalConst', 43);
echo M9  // line 20
echo "test\n";
echo InnerNS\M

}  // end global namespace
// line 25


namespace InnerNS {

// line 30
const M9AnotherConst = 33;
class M9InnerClass {}
/** @return array<int,int>  */
function M9InnerFunction() { return [2]; }
// line 35
}
namespace Other {
function M9InnerFunction($first_arg, \M9Example $second_arg) {
    // here, we look for completions
    if (rand(0, 1) > 0) {  // line 40
        echo \M9Example::
        return $second_arg;
    } else {
        echo $second_arg->
        return $first_arg;
    }
}
}
EOT;

    /**
     * @param string $property_label
     * @param ?string $property_insert_text
     * @param ?string $insert_text_for_substr
     * @param bool $for_vscode
     * @return array<int,array{0:Position,1:array,2:bool}>
     */
    private function createCompletionBasicTestCases(string $property_label, $property_insert_text, $insert_text_for_substr, bool $for_vscode) : array
    {
        // A static property
        $property_completion_item = [
            'label' => $property_label,
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'int',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => $property_insert_text,
        ];
        $my_class_constant_item = [
            'label' => 'my_class_const',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => "array{0:'literalString'}",
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_class_class_item = [
            'label' => 'class',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => "'M9Example'",
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_static_function_item = [
            'label' => 'my_static_function',
            'kind' => CompletionItemKind::METHOD,
            'detail' => 'mixed',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_instance_property_item = [
            'label' => 'myInstanceVar',
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'int',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_class_item = [
            'label' => 'M9Example',
            'kind' => CompletionItemKind::CLASS_,
            'detail' => '\M9Example',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_global_constant_item = [
            'label' => 'M9GlobalConst',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => '42',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_other_global_constant_item = [
            'label' => 'M9OtherGlobalConst',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => '43',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $my_global_function_item = [
            'label' => 'M9GlobalFunction',
            'kind' => CompletionItemKind::FUNCTION,
            'detail' => 'array',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        // These completions are returned to the language client in alphabetical order
        $static_property_completions = [
            $property_completion_item,
        ];
        $static_property_completions_substr = [
            array_merge($property_completion_item, ['insertText' => $insert_text_for_substr]),
        ];
        $all_static_completions = [
            $my_class_class_item,
            $my_class_constant_item,
            $my_static_function_item,
            $property_completion_item,
        ];
        $all_instance_completions = [
            $my_static_function_item,
            $my_instance_property_item,
        ];
        $all_constant_completions = [
            $my_class_item,
            $my_global_constant_item,
            $my_global_function_item,
            $my_other_global_constant_item,
        ];

        return [
            [new Position(10, 17), $static_property_completions, $for_vscode],
            [new Position(11, 19), $static_property_completions_substr, $for_vscode],
            [new Position(12, 16), $all_static_completions, $for_vscode],
            [new Position(20, 7), $all_constant_completions, $for_vscode],
            [new Position(41, 25), $all_static_completions, $for_vscode],
            [new Position(44, 26), $all_instance_completions, $for_vscode],
        ];
    }
    /**
     * @return array<int,array{0:Position,1:array,2:bool}>
     */
    public function completionBasicProvider() : array
    {
        return array_merge(
            $this->createCompletionBasicTestCases('myVar', 'myVar', 'Var', false),
            $this->createCompletionBasicTestCases('$myVar', null, null, true)
        );
    }

    /**
     * @dataProvider completionVariableProvider
     */
    public function testCompletionVariable(Position $position, array $expected_completions, bool $for_vscode = false)
    {
        $this->runTestCompletionWithAndWithoutPcntl($position, $expected_completions, $for_vscode, self::COMPLETION_VARIABLE_FILE_CONTENTS);
    }

    // Here, we use a prefix of M9 to avoid suggesting MYSQLI_...
    const COMPLETION_VARIABLE_FILE_CONTENTS = <<<'EOT'
<?php  // line 0

namespace LSP {

/**
 * @property int $myMagicProperty  line 5
 * @phan-forbid-undeclared-magic-properties (should not affect suggestions)
 */
class M9Class {
    public static $myStaticProp = 2;
    public $myPublicVar = 3;  // line 10
    /** @var string another variable */
    public $otherPublicVar;
    public $otherPublicInt = 0;
    protected $myProtected = 3;
    private $myPrivate = 3;  // line 15


    public function myInstanceMethod() {}
    public static function my_static_method() : array { return $_SERVER; }
    // line 20

    protected function myProtectedMethod() {}
    private $myPrivateInstanceVar = 3;  // line 5
    public static function my_other_static_method () : void {}
    const my_class_const = ['literalString'];  // line 25

    public function __get(string $x) {
        return strlen($x);
    }
    // line 30

    public static function main() {
        $myLocalVar = new self();
        echo $myLocalVar->
        // line 35
        $mUnrelated = 3; $myVar = 4;
        echo $my
        echo $_S

        // line 40
    }
}


// line 45
$j = new M9Class;
echo $j->otherP
echo $j->my

}  // end namespace LSP
EOT;

    /**
     * @param string $variablePrefix expected prefix for labels of variables
     * @return array<int,array{0:Position,1:array,2:bool}>
     */
    private function createCompletionVariableTestCases(string $variablePrefix, bool $for_vscode) : array
    {
        $otherPublicVarPropertyItem = [
            'label' => 'otherPublicVar',
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'string',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $otherPublicPropertyItem = [
            'label' => 'otherPublicInt',
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'int',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myMagicPropertyItem = [
            'label' => 'myMagicProperty',
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'int',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myPublicVarItem = [
            'label' => 'myPublicVar',
            'kind' => CompletionItemKind::PROPERTY,
            'detail' => 'int',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myInstanceMethodItem = [
            'label' => 'myInstanceMethod',
            'kind' => CompletionItemKind::METHOD,
            'detail' => 'mixed',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myStaticMethodItem = [
            'label' => 'my_static_method',
            'kind' => CompletionItemKind::METHOD,
            'detail' => 'array',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myOtherStaticMethodItem = [
            'label' => 'my_other_static_method',
            'kind' => CompletionItemKind::METHOD,
            'detail' => 'void',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $publicM9OtherCompletions = [
            $otherPublicPropertyItem,
            $otherPublicVarPropertyItem,
        ];
        $publicM9MyCompletions = [
            $myOtherStaticMethodItem,
            $myStaticMethodItem,
            $myInstanceMethodItem,
            $myMagicPropertyItem,
            $myPublicVarItem,
        ];

        $myLocalVarItem = [
            'label' => $variablePrefix . 'myLocalVar',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => '\LSP\M9Class',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $myVarItem = [
            'label' => $variablePrefix . 'myVar',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => '4',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $localVariableCompletions = [
            $myLocalVarItem,
            $myVarItem,
        ];
        $serverSuperglobal = [
            'label' => $variablePrefix . '_SERVER',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => 'array<string,mixed>',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $sessionSuperglobal = [
            'label' => $variablePrefix . '_SESSION',
            'kind' => CompletionItemKind::VARIABLE,
            'detail' => 'array<string,mixed>',
            'documentation' => null,
            'sortText' => null,
            'filterText' => null,
            'insertText' => null,
        ];
        $superGlobalVariableCompletions = [
            $serverSuperglobal,
            $sessionSuperglobal,
        ];

        return [
            [new Position(37, 16), $localVariableCompletions, $for_vscode],
            [new Position(38, 16), $superGlobalVariableCompletions, $for_vscode],
            [new Position(47, 15), $publicM9OtherCompletions, $for_vscode],
            [new Position(48, 11), $publicM9MyCompletions, $for_vscode],
        ];
    }

    /**
     * @return array<int,array{0:Position,1:array,2:bool}>
     */
    public function completionVariableProvider() : array
    {
        return array_merge(
            $this->createCompletionVariableTestCases('', false),
            $this->createCompletionVariableTestCases('$', true)
        );
    }

    /**
     * @param ?int $expected_definition_line 0-based line number (null for nothing)
     *
     * @dataProvider definitionInOtherFileProvider
     */
    public function testDefinitionInOtherFile(string $new_file_contents, Position $position, string $expected_definition_uri, $expected_definition_line, string $requested_uri = null)
    {
        if (function_exists('pcntl_fork')) {
            $this->runTestDefinitionInOtherFileWithPcntlSetting($new_file_contents, $position, $expected_definition_uri, $expected_definition_line, $requested_uri, true);
        }
        $this->runTestDefinitionInOtherFileWithPcntlSetting($new_file_contents, $position, $expected_definition_uri, $expected_definition_line, $requested_uri, false);
    }

    /**
     * @param ?int $expected_definition_line null for nothing
     *
     * @dataProvider typeDefinitionInOtherFileProvider
     */
    public function testTypeDefinitionInOtherFile(string $new_file_contents, Position $position, string $expected_definition_uri, $expected_definition_line, string $requested_uri = null)
    {
        if (function_exists('pcntl_fork')) {
            $this->runTestTypeDefinitionInOtherFileWithPcntlSetting($new_file_contents, $position, $expected_definition_uri, $expected_definition_line, $requested_uri, true);
        }
        $this->runTestTypeDefinitionInOtherFileWithPcntlSetting($new_file_contents, $position, $expected_definition_uri, $expected_definition_line, $requested_uri, false);
    }

    /**
     * @dataProvider hoverInOtherFileProvider
     * @param ?string $expected_hover_markup
     */
    public function testHoverInOtherFile(string $new_file_contents, Position $position, $expected_hover_markup, string $requested_uri = null, bool $require_php71_or_newer = false)
    {
        if (PHP_VERSION_ID < 70100 && $require_php71_or_newer) {
            $this->markTestSkipped('This test requires php 7.1');
        }
        if (function_exists('pcntl_fork')) {
            $this->runTestHoverInOtherFileWithPcntlSetting(
                $new_file_contents,
                $position,
                $expected_hover_markup,
                $requested_uri,
                true
            );
        }
        $this->runTestHoverInOtherFileWithPcntlSetting($new_file_contents, $position, $expected_hover_markup, $requested_uri, false);
    }

    /**
     * @return array<int,array{0:string,1:Position,2:?string,3?:?string,4?:bool}>
     */
    public function hoverInOtherFileProvider() : array
    {
        // Refers to elements defined in ../../misc/lsp/src/definitions.php
        $example_file_contents = <<<'EOT'
<?php // line 0

function example(MyClass $arg) {
    echo \MY_GLOBAL_CONST;
    echo \MyNS\SubNS\MY_NAMESPACED_CONST;
    $arg->myMethod();  // line 5
    $arg->myInstanceMethod();
    global_function_with_comment(0, '');
    $c = new ExampleClass();
    $c->counter += 1;
    var_export(ExampleClass::HTTP_500);  // line 10
    var_export($c);
    var_export($c->descriptionlessProp); var_export(ExampleClass::$typelessProp);
}
/**
 * @param string|false $strVal line 15
 * @param array<string,stdClass> $arrVal
 */
function example2($strVal, array $arrVal) {
    var_export($strVal);
    var_export($arrVal);  // line 20
    $strVal = (string)$strVal;
    echo strlen($strVal);
    $n = ast\parse_code($strVal, 50);
}
function test(ExampleClass $c) {  // line 25
    var_export($c->propWithDefault);
}
EOT;
        return [
            // Failure tests
            [
                $example_file_contents,
                new Position(2, 20),  // MyClass (Points to MyClass)
                <<<'EOT'
```php
class MyClass
```

A description of MyClass
EOT
            ],
            [
                $example_file_contents,
                new Position(2, 1),  // Points to nothing
                null,
            ],
            // Global constant without a description
            [
                $example_file_contents,
                new Position(3, 12),  // MY_GLOBAL_CONST
                <<<'EOT'
```php
const MY_GLOBAL_CONST = 2
```
EOT
            ],
            [
                $example_file_contents,
                new Position(4, 22),  // MY_NAMESPACED_CONST
                <<<'EOT'
```php
const MY_NAMESPACED_CONST = 2
```

This constant is equal to 1+1
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(5, 12),  // MY_NAMESPACED_CONST
                <<<'EOT'
```php
public static function myMethod() : \MyOtherClass
```

`@return MyOtherClass` details
EOT
            ],
            [
                $example_file_contents,
                new Position(6, 12),  // MY_NAMESPACED_CONST
                <<<'EOT'
```php
public function myInstanceMethod()
```

myInstanceMethod echoes a string
EOT
            ],
            [
                $example_file_contents,
                new Position(7, 4),  // MY_NAMESPACED_CONST
                <<<'EOT'
```php
function global_function_with_comment(int $x, ?string $y) : void
```

This has a mix of comments and annotations, annotations are included in hover

- Markup in comments is preserved,
  and leading whitespace is as well.
EOT
            ],
            [
                $example_file_contents,
                new Position(9, 10),  // ExampleClass->counter
                <<<'EOT'
```php
public $counter
```

`@var int` this tracks a count
EOT
            ],
            [
                $example_file_contents,
                new Position(10, 30),  // ExampleClass->counter
                <<<'EOT'
```php
const HTTP_500 = 500
```

`@var int` value of an HTTP response code
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(11, 16),  // $c
                <<<'EOT'
```php
class ExampleClass
```

description of ExampleClass
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(12, 24),  // ExampleClass->descriptionlessProp
                <<<'EOT'
```php
public $descriptionlessProp
```

`@var array<string, \stdClass>`
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(12, 70),  // ExampleClass->typelessProp
                <<<'EOT'
```php
public static $typelessProp
```

This has no type
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(19, 15),  // $strVal
                '`false|string`',
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(20, 20),  // $arrVal
                '`array<string,\stdClass>`',
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(22, 10),  // strlen
                <<<'EOT'
```php
function strlen(string $string) : int
```
EOT
                ,
                null,
                true
            ],
            // Currently, the namespace is left out from the hover text
            [
                $example_file_contents,
                new Position(23, 14),  // ast\parse_code
                <<<'EOT'
```php
namespace ast;
function parse_code(string $code, int $version, string $filename = default) : \ast\Node
```
EOT
                ,
                null,
                true
            ],
            [
                $example_file_contents,
                new Position(26, 20),  // ExampleClass->propWithDefault
                <<<'EOT'
```php
public $propWithDefault
```

`@var array{0:2,1:3}` This has a default
EOT
                ,
                null,
                true
            ],
        ];
    }

    /**
     * @param ?string $requested_uri
     */
    private static function shouldExpectDiagnosticNotificationForURI($requested_uri) : bool
    {
        if ($requested_uri && basename(dirname($requested_uri)) !== 'src') {
            return false;
        }
        return true;
    }

    /**
     * @param ?int $expected_definition_line
     * @param ?string $requested_uri
     */
    public function runTestDefinitionInOtherFileWithPcntlSetting(
        string $new_file_contents,
        Position $position,
        string $expected_definition_uri,
        $expected_definition_line,
        $requested_uri,
        bool $pcntl_enabled
    ) {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();

        $this->messageId = 0;
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntl_enabled);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeDidChangeNotificationToFile($proc_in, $requested_uri, $new_file_contents);
            if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
                $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
            }

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $perform_definition_request = /** @return array */ function () use ($proc_in, $proc_out, $position, $requested_uri) {
                return $this->writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, $position, $requested_uri);
            };
            $definition_response = $perform_definition_request();

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

            $message = "Unexpected definition for {$position->line}:{$position->character} (0-based) on line \"" . $cur_line . '"' . ' at "' . substr($cur_line, $position->character, 10) . '"';
            $this->assertEquals($expected_definition_response, $definition_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_definition_response, $definition_response, $message);

            // This operation should be idempotent.
            // If it's repeated, it should give the same response
            // (and it shouldn't crash the server)
            $expected_definition_response['id'] = 3;

            $definition_response = $perform_definition_request();
            $this->assertEquals($expected_definition_response, $definition_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_definition_response, $definition_response, $message);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Unexpected exception in " . __METHOD__ . ": " . $e->getMessage());
            throw $e;
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    /**
     * @param ?int $expected_definition_line
     * @param ?string $requested_uri
     */
    public function runTestTypeDefinitionInOtherFileWithPcntlSetting(
        string $new_file_contents,
        Position $position,
        string $expected_definition_uri,
        $expected_definition_line,
        $requested_uri,
        bool $pcntl_enabled
    ) {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();

        $this->messageId = 0;
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntl_enabled);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeDidChangeNotificationToFile($proc_in, $requested_uri, $new_file_contents);
            if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
                $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
            }

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $perform_definition_request = /** @return array */ function () use ($proc_in, $proc_out, $position, $requested_uri) {
                return $this->writeTypeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, $position, $requested_uri);
            };
            $definition_response = $perform_definition_request();

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

            $message = sprintf(
                "Unexpected type definition for %d:%d (0-based) on line %s at \"%s\"",
                $position->line,
                $position->character,
                (string)json_encode($cur_line),
                (string)substr($cur_line, $position->character, 10)
            );
            $this->assertEquals($expected_definition_response, $definition_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_definition_response, $definition_response, $message);

            // This operation should be idempotent.
            // If it's repeated, it should give the same response
            // (and it shouldn't crash the server)
            $expected_definition_response['id'] = 3;

            $definition_response = $perform_definition_request();
            $this->assertEquals($expected_definition_response, $definition_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_definition_response, $definition_response, $message);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Unexpected exception in " . __METHOD__ . ": " . $e->getMessage());
            throw $e;
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    /**
     * @param ?string $expected_hover_string
     * @param ?string $requested_uri
     */
    public function runTestHoverInOtherFileWithPcntlSetting(
        string $new_file_contents,
        Position $position,
        $expected_hover_string,
        $requested_uri,
        bool $pcntl_enabled
    ) {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();

        $this->messageId = 0;
        // TODO: Move this into an OOP abstraction, add time limits, etc.
        list($proc, $proc_in, $proc_out) = $this->createPhanLanguageServer($pcntl_enabled);
        try {
            $this->writeInitializeRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeInitializedNotification($proc_in);
            $this->writeDidChangeNotificationToFile($proc_in, $requested_uri, $new_file_contents);
            if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
                $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
            }

            // Request the definition of the class "MyExample" with the cursor in the middle of that word
            // NOTE: Line numbers are 0-based for Position
            $perform_hover_request = /** @return array */ function () use ($proc_in, $proc_out, $position, $requested_uri) {
                return $this->writeHoverRequestAndAwaitResponse($proc_in, $proc_out, $position, $requested_uri);
            };
            $hover_response = $perform_hover_request();

            if ($expected_hover_string) {
                $expected_hover_result = [
                    'contents' => [
                        'kind' => MarkupContent::MARKDOWN,
                        'value' => $expected_hover_string,
                    ],
                    'range' => null,
                ];
            } else {
                $expected_hover_result = null;
            }
            $expected_hover_response = [
                'result' => $expected_hover_result,
                'id' => 2,
                'jsonrpc' => '2.0',
            ];

            $cur_line = explode("\n", $new_file_contents)[$position->line] ?? '';

            $message = sprintf(
                "Unexpected hover response for %d:%d (0-based) on line %s at \"%s\"",
                $position->line,
                $position->character,
                (string)json_encode($cur_line),
                (string)substr($cur_line, $position->character, 10)
            );
            $this->assertEquals($expected_hover_response, $hover_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_hover_response, $hover_response, $message);

            // This operation should be idempotent.
            // If it's repeated, it should give the same response
            // (and it shouldn't crash the server)
            $expected_hover_response['id'] = 3;

            $hover_response = $perform_hover_request();
            $this->assertEquals($expected_hover_response, $hover_response, $message);  // slightly better diff view than assertSame
            $this->assertSame($expected_hover_response, $hover_response, $message);

            $this->writeShutdownRequestAndAwaitResponse($proc_in, $proc_out);
            $this->writeExitNotification($proc_in);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Unexpected exception in " . __METHOD__ . ": " . $e->getMessage());
            throw $e;
        } finally {
            $this->performCleanLanguageServerShutdown($proc, $proc_in, $proc_out);
        }
    }

    /**
     * @return array<int,array{0:string,1:Position,2:string,3:?int,4?:string}>
     */
    public function definitionInOtherFileProvider() : array
    {
        // Refers to elements defined in ../../misc/lsp/src/definitions.php
        $example_file_contents = <<<'EOT'
<?php use MyNS\SubNS; // line 0
function example(MyClass $param_clss) {
    echo MyClass::$my_static_property;
    echo MyClass::MyClassConst;
    var_export(MyClass::myMethod());
    my_global_function();  // line 5
    $v = new MyClass();
    $v->myInstanceMethod();
    $a = $v->other_class;
    echo MY_GLOBAL_CONST;
    'my_global_function'();  // line 10
    echo MyClass::class;
    echo \MyNS\SubNS\MyNamespacedClass::class;
    echo SubNS\MyNamespacedClass::class;
    echo SubNS\MY_NAMESPACED_CONST;
    echo count(SubNS\MyNamespacedClass::MyOtherClassConst);  // line 15
}
use MyNS\SubNS\MyNamespacedClass;
echo MyNamespacedClass::class;

/** MyNamespacedClass is a class, \MyNS\SubNS\MyNamespacedClass is a different class (line 20) */
function unused_example() {}
// This is a comment referring to \MyClass (must be before a ast\Node)
echo 'something';
EOT;
        $definitions_file_uri = Utils::pathToUri(self::getLSPFolder() . '/src/definitions.php');
        return [
            // Failure tests
            [
                $example_file_contents,
                new Position(11, 21),  // MyClass::class (Points to MyClass)
                $definitions_file_uri,
                null,
                Utils::pathToUri(self::getLSPFolder() . '/unanalyzed_directory/definitions.php'),
            ],
            // Success tests
            [
                $example_file_contents,
                new Position(17, 5),  // MyNamespacedClass
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(2, 21),  // my_static_property
                $definitions_file_uri,
                11,
            ],
            [
                $example_file_contents,
                new Position(3, 21),  // MyClassConst
                $definitions_file_uri,
                10,
            ],
            [
                $example_file_contents,
                new Position(4, 26),  // myMethod
                $definitions_file_uri,
                13,
            ],
            [
                $example_file_contents,
                new Position(5, 21),  // my_global_function
                $definitions_file_uri,
                2,
            ],
            [
                $example_file_contents,
                new Position(5, 4),  // my_global_function
                $definitions_file_uri,
                2,
            ],
            [
                $example_file_contents,
                new Position(5, 3),  // my_global_function
                $definitions_file_uri,
                null,
            ],
            [
                $example_file_contents,
                new Position(6, 15),  // MyClass or the constructor
                $definitions_file_uri,
                9,
            ],
            [
                $example_file_contents,
                new Position(7, 9),  // myInstanceMethod
                $definitions_file_uri,
                16,
            ],
            [
                $example_file_contents,
                new Position(9, 10),  // MY_GLOBAL_CONST
                $definitions_file_uri,
                24,  // Place where the global constant was defined (Currently the class definition)
            ],
            [
                $example_file_contents,
                new Position(10, 6),  // my_global_function (alternative syntax)
                $definitions_file_uri,
                2,
            ],
            [
                $example_file_contents,
                new Position(11, 20),  // MyClass::class (Points to MyClass)
                $definitions_file_uri,
                9,
            ],
            [
                $example_file_contents,
                new Position(12, 20),  // MyNS\SubNS\MyNamespacedClass (Points to a backslash)
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(12, 19),  // MyNS\SubNS\MyNamespacedClass (Points to a backslash)
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(13, 36),  // 'class' of SubNS\MyNamespacedClass::class
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(14, 16),  // MY_NAMESPACED_CONST
                $definitions_file_uri,
                29,
            ],
            [
                $example_file_contents,
                new Position(15, 45),  // MyOtherClassConst
                $definitions_file_uri,
                32,
            ],
            [
                $example_file_contents,
                new Position(1, 19),  // MyNamespacedClass as a signature param type
                $definitions_file_uri,
                9,
            ],
            [
                $example_file_contents,
                new Position(17, 5),  // MyNamespacedClass
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(20, 10),  // MyNamespacedClass in doc comment
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(20, 40),  // MySub in doc comment
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(22, 35),  // MyClass in line comment
                $definitions_file_uri,
                9,
            ],
        ];
    }

    /**
     * @return array<int,array{0:string,1:Position,2:string,3:?int,4?:string}>
     */
    public function typeDefinitionInOtherFileProvider() : array
    {
        // Refers to elements defined in ../../misc/lsp/src/definitions.php
        $example_file_contents = <<<'EOT'
<?php use MyNS\SubNS; // line 0
function example() {
    $my_closure = Closure::fromCallable('my_global_function');
    $copy = $my_closure;
    $instance = new SubNS\MyNamespacedClass();
    var_export($instance);  // line 5
    $result = MyClass::myMethod();
}
EOT;
        $definitions_file_uri = Utils::pathToUri(self::getLSPFolder() . '/src/definitions.php');
        return [
            [
                $example_file_contents,
                new Position(3, 14),  // $my_closure
                $definitions_file_uri,
                2,  // function my_global_function() is the type definition of my_closure
            ],
            [
                $example_file_contents,
                new Position(5, 20),  // variable with type MyNamespacedClass
                $definitions_file_uri,
                31,
            ],
            [
                $example_file_contents,
                new Position(6, 25),  // myMethod invocation has a type of MyOtherClass
                $definitions_file_uri,
                21,  // definition of MyOtherClass
            ],
            [
                $example_file_contents,
                new Position(0, 0),  // Points to inline html
                $definitions_file_uri,
                null,
            ],
        ];
    }

    /**
     * @param resource $proc_out
     * @return void
     */
    private function assertHasEmptyPublishDiagnosticsNotification($proc_out, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        $diagnostics_response = $this->awaitResponse($proc_out);
        $error_message = "Unexpected response: " . json_encode($diagnostics_response);
        $this->assertSame('textDocument/publishDiagnostics', $diagnostics_response['method'] ?? null, $error_message);
        $uri = $diagnostics_response['params']['uri'];
        $this->assertSame($uri, $requested_uri, $error_message);
        $diagnostics = $diagnostics_response['params']['diagnostics'];
        $this->assertSame([], $diagnostics, $error_message);
    }

    /**
     * @param resource $proc_out
     * @return void
     */
    private function assertHasNonEmptyPublishDiagnosticsNotification($proc_out, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        $diagnostics_response = $this->awaitResponse($proc_out);
        $this->assertSame('textDocument/publishDiagnostics', $diagnostics_response['method'] ?? null, "Unexpected response: " . json_encode($diagnostics_response));
        $uri = $diagnostics_response['params']['uri'];
        $this->assertSame($uri, $requested_uri);
        $diagnostics = $diagnostics_response['params']['diagnostics'];
        $this->assertNotSame([], $diagnostics);
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
            'code' => null, // Deliberately leaving out $issue->getTypeId()
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
                    'completionProvider' => [
                        'resolveProvider' => false,
                        'triggerCharacters' => ['$', '>'],
                    ],
                    'definitionProvider' => true,
                    'typeDefinitionProvider' => true,
                    'hoverProvider' => true,
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
    private function writeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, Position $position, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        // Implementation detail: We simultaneously emit a notification with new diagnostics
        // and the response for the definition request at the same time, even if files didn't change.

        // NOTE: That could probably be refactored, but there's not much benefit to doing that.
        $params = [
            'textDocument' => new TextDocumentIdentifier($requested_uri),
            'position' => $position,
        ];
        $this->writeMessage($proc_in, 'textDocument/definition', $params);
        if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
            $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
        }

        $response = $this->awaitResponse($proc_out);

        return $response;
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return array the response
     * @throws InvalidArgumentException
     */
    private function writeCompletionRequestAndAwaitResponse($proc_in, $proc_out, Position $position, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        // Implementation detail: We simultaneously emit a notification with new diagnostics
        // and the response for the definition request at the same time, even if files didn't change.

        // NOTE: That could probably be refactored, but there's not much benefit to doing that.
        $params = [
            'textDocument' => new TextDocumentIdentifier($requested_uri),
            'position' => $position,
            'context' => [
                'triggerKind' => CompletionTriggerKind::TRIGGER_CHARACTER,
                'triggerCharacter' => '$',
            ],
        ];
        $this->writeMessage($proc_in, 'textDocument/completion', $params);
        if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
            $this->assertHasNonEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
        }

        $response = $this->awaitResponse($proc_out);

        return $response;
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return array the response
     * @throws InvalidArgumentException
     */
    private function writeTypeDefinitionRequestAndAwaitResponse($proc_in, $proc_out, Position $position, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        // Implementation detail: We simultaneously emit a notification with new diagnostics
        // and the response for the definition request at the same time, even if files didn't change.

        // NOTE: That could probably be refactored, but there's not much benefit to doing that.
        $params = [
            'textDocument' => new TextDocumentIdentifier($requested_uri),
            'position' => $position,
        ];
        $this->writeMessage($proc_in, 'textDocument/typeDefinition', $params);
        if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
            $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
        }

        $response = $this->awaitResponse($proc_out);

        return $response;
    }

    /**
     * @param resource $proc_in
     * @param resource $proc_out
     * @return array the response
     * @throws InvalidArgumentException
     */
    private function writeHoverRequestAndAwaitResponse($proc_in, $proc_out, Position $position, string $requested_uri = null)
    {
        $requested_uri = $requested_uri ?? $this->getDefaultFileURI();
        // Implementation detail: We simultaneously emit a notification with new diagnostics
        // and the response for the definition request at the same time, even if files didn't change.

        // NOTE: That could probably be refactored, but there's not much benefit to doing that.
        $params = [
            'textDocument' => new TextDocumentIdentifier($requested_uri),
            'position' => $position,
        ];
        $this->writeMessage($proc_in, 'textDocument/hover', $params);
        if (self::shouldExpectDiagnosticNotificationForURI($requested_uri)) {
            $this->assertHasEmptyPublishDiagnosticsNotification($proc_out, $requested_uri);
        }

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
        $this->writeDidChangeNotificationToFile($proc_in, $this->getDefaultFileURI(), $new_contents);
    }

    /**
     * @param resource $proc_in
     * @return void
     * @throws InvalidArgumentException
     */
    private function writeDidChangeNotificationToFile($proc_in, string $requested_uri, string $new_contents)
    {
        $params = [
            'textDocument' => ['uri' => $requested_uri],
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
     * @return array
     */
    private function awaitResponse($proc_out) : array
    {
        $buffer = '';
        $content_length = 0;
        $headers = [];
        '@phan-var array<string,string> $headers';
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
        $body_raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\r\n";
        $raw = sprintf(
            "Content-Length: %d\r\nContent-Type: application/vscode-jsonrpc; charset=utf-8\r\n\r\n%s",
            strlen($body_raw),
            $body_raw
        );
        fwrite($proc_in, $raw);
    }
}
