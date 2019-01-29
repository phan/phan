<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Config\Initializer;

/**
 * Unit tests of Phan's analysis creating the expected element representations on snippets of code.
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 */
final class ConfigTest extends BaseTest
{
    public function testDefaultsValid()
    {
        $this->assertSame([], Config::getConfigErrors(Config::DEFAULT_CONFIGURATION), 'default configuration should be valid');
    }

    public function testInitializesValid()
    {
        for ($init_level = 1; $init_level <= 5; $init_level++) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $settings = Initializer::createPhanSettingsForComposerSettings(
                [],
                null,
                [
                    'init-level' => $init_level,
                    'init-analyze-dir' => '.',
                ]
            )->settings;

            $this->assertSame([], Config::getConfigErrors($settings), "configuration overrides for --init-level $init_level should be valid");
        }
    }

    public function testWarnsInvalid()
    {
        $config = array_merge(
            Config::DEFAULT_CONFIGURATION,
            [
                'plugins' => 'SomePlugin',
                'target_php_version' => ['7.1'],
                'file_list' => [2],
            ]
        );
        $expectedErrors = [
            "Invalid config value for 'file_list': Expected a list of strings: index 0 is type '" . gettype(2) . "'",
            "Invalid config value for 'plugins': Expected a list of strings, but got type 'string'",
            "Invalid config value for 'target_php_version': Expected a scalar, but got type 'array'",
        ];
        $this->assertSame($expectedErrors, Config::getConfigErrors($config), 'Should warn for invalid settings');
    }
}
