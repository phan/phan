<?php declare(strict_types = 1);
namespace Phan\Tests\LanguageServer;

use Phan\CodeBase;
use Phan\Tests\BaseTest;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\InitializeResult;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\ServerCapabilities;
use Phan\LanguageServer\Protocol\TextDocumentSyncKind;

/**
 * Test functionality of the Language Server
 */
class LanguageServerTest extends BaseTest
{
    public function testInitialize()
    {
        $mock_file_path_lister = function () {
            return [];
        };
        $code_base = new CodeBase([], [], [], [], []);
        $server = new LanguageServer(new MockProtocolStream, new MockProtocolStream, $code_base, $mock_file_path_lister);
        $result = $server->initialize(new ClientCapabilities, __DIR__, getmypid())->wait();

        $server_capabilities = new ServerCapabilities();
        $server_capabilities->textDocumentSync = TextDocumentSyncKind::FULL;

        $this->assertEquals(new InitializeResult($server_capabilities), $result);
    }

    // TODO: Test the ability to create a Request
}
