<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\CLI;

final class CLITest extends BaseTest
{
    /**
     * @dataProvider getFlagSuggestionStringProvider
     * @suppress PhanAccessMethodInternal
     */
    public function testGetFlagSuggestionString(string $flag, string $expected_message)
    {
        $this->assertSame($expected_message, CLI::getFlagSuggestionString($flag));
    }

    public function getFlagSuggestionStringProvider() : array
    {
        $wrap_suggestion = function (string $text) : string {
            return " (did you mean $text?)";
        };
        return [
            ['H', $wrap_suggestion('-h')],
            ['he', $wrap_suggestion('-e or -h')],
            ['vv', $wrap_suggestion('-v')],
            ['HELP', $wrap_suggestion('--help')],
            ['ALLOW-POLYFILL-PARSER', $wrap_suggestion('--allow-polyfill-parser')],
            ['allce-polyfill-parser', $wrap_suggestion('--allow-polyfill-parser or --force-polyfill-parser')],
            ['daemonizetcphost', $wrap_suggestion('--daemonize-tcp-host')],
            ['daemonizetcpport', $wrap_suggestion('--daemonize-tcp-port')],
            ['strict-prop-checking', $wrap_suggestion('--strict-param-checking or --strict-property-checking')],
            ['process', $wrap_suggestion('--processes')],
            ['', ''],
            ['invalid-flag', ''],
        ];
    }
}
