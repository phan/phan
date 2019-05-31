<?php declare(strict_types=1);

namespace Phan\Tests\LanguageServer;

use Phan\CodeBase;
use Phan\Config;
use Phan\LanguageServer\LanguageServer;
use Phan\LanguageServer\Protocol\ClientCapabilities;
use Phan\LanguageServer\Protocol\CompletionOptions;
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
    public function testInitializeMinimal() : void
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        Config::reset();
        Config::setValue('language_server_enable_completion', false);
        Config::setValue('language_server_enable_hover', false);
        Config::setValue('language_server_enable_go_to_definition', false);
        $mock_file_path_lister = /** @return array{} */ static function () : array {
            return [];
        };
        $code_base = new CodeBase([], [], [], [], []);
        $server = new LanguageServer(new MockProtocolStream(), new MockProtocolStream(), $code_base, $mock_file_path_lister);
        $result = $server->initialize(new ClientCapabilities(), __DIR__, \getmypid() ?: null)->wait();

        $sync_options = new TextDocumentSyncOptions();
        $sync_options->openClose = true;
        $sync_options->change = TextDocumentSyncKind::FULL;
        $sync_options->save = new SaveOptions();
        $sync_options->save->includeText = true;

        $server_capabilities = new ServerCapabilities();
        $server_capabilities->textDocumentSync = $sync_options;

        $this->assertEquals(new InitializeResult($server_capabilities), $result);
    }

    public function testInitializeDefault() : void
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        Config::reset();
        $mock_file_path_lister = /** @return array{} */ static function () : array {
            return [];
        };
        $code_base = new CodeBase([], [], [], [], []);
        $server = new LanguageServer(new MockProtocolStream(), new MockProtocolStream(), $code_base, $mock_file_path_lister);
        $result = $server->initialize(new ClientCapabilities(), __DIR__, \getmypid() ?: null)->wait();

        $sync_options = new TextDocumentSyncOptions();
        $sync_options->openClose = true;
        $sync_options->change = TextDocumentSyncKind::FULL;
        $sync_options->save = new SaveOptions();
        $sync_options->save->includeText = true;

        $completion_options = new CompletionOptions();
        $completion_options->resolveProvider = false;
        $completion_options->triggerCharacters = ['$', '>'];

        $server_capabilities = new ServerCapabilities();
        $server_capabilities->textDocumentSync = $sync_options;
        $server_capabilities->completionProvider = $completion_options;
        $server_capabilities->definitionProvider = true;
        $server_capabilities->typeDefinitionProvider = true;
        $server_capabilities->hoverProvider = true;

        $this->assertEquals(new InitializeResult($server_capabilities), $result);
    }
    // TODO: Test the ability to create a Request
}
