<?php declare(strict_types = 1);
namespace Phan\Tests\LanguageServer;

use Phan\CodeBase;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\InitializeResult;
use Phan\LanguageServer\Protocol\SaveOptions;
use Phan\LanguageServer\Protocol\ServerCapabilities;
use Phan\LanguageServer\Protocol\TextDocumentSyncKind;
use Phan\LanguageServer\Protocol\TextDocumentSyncOptions;
use Phan\Tests\BaseTest;

/**
 * Test functionality of the Language Server
 */
final class LanguageServerTest extends BaseTest
{
    public function testInitialize()
    {
        $mock_file_path_lister = function () : array {
            return [];
        };
        $code_base = new CodeBase([], [], [], [], []);
        $server = new LanguageServer(new MockProtocolStream(), new MockProtocolStream(), $code_base, $mock_file_path_lister);
        $result = $server->initialize(new ClientCapabilities(), __DIR__, getmypid())->wait();

        $sync_options = new TextDocumentSyncOptions();
        $sync_options->openClose = true;
        $sync_options->change = TextDocumentSyncKind::FULL;
        $sync_options->save = new SaveOptions();
        $sync_options->save->includeText = true;

        $server_capabilities = new ServerCapabilities();
        $server_capabilities->textDocumentSync = $sync_options;

        $this->assertEquals(new InitializeResult($server_capabilities), $result);
    }

    // TODO: Test the ability to create a Request
}
