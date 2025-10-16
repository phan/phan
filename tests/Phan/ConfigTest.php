<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;

/**
 * Unit tests of Phan's analysis creating the expected element representations on snippets of code.
 */
final class ConfigTest extends TestBase
{
    public function testDefaultsValid(): void
    {
        $this->assertSame([], Config::getConfigErrors(Config::DEFAULT_CONFIGURATION), 'default configuration should be valid');
    }

    public function testWarnsInvalid(): void
    {
        $config = \array_merge(
            Config::DEFAULT_CONFIGURATION,
            [
                'plugins' => 'SomePlugin',
                'target_php_version' => ['8.4'],
                'file_list' => [2],
            ]
        );
        $expected_errors = [
            "Invalid config value for 'file_list': Expected a list of strings: index 0 is type 'integer'",
            "Invalid config value for 'plugins': Expected a list of strings, but got type 'string'",
            "Invalid config value for 'target_php_version': Expected a scalar, but got type 'array'",
        ];
        $this->assertSame($expected_errors, Config::getConfigErrors($config), 'Should warn for invalid settings');
    }

    /**
     * @dataProvider warnsEnableCompletionProvider
     */
    public function testWarnsEnableCompletion(mixed $value, string ...$expected_errors): void
    {
        $config = ['language_server_enable_completion' => $value];
        $this->assertSame($expected_errors, Config::getConfigErrors($config));
    }


    /**
     * @return list<list>
     */
    public static function warnsEnableCompletionProvider(): array
    {
        return [
            [false],
            [true],
            [Config::COMPLETION_VSCODE],
            [[], "Invalid config value for 'language_server_enable_completion': Expected a scalar, but got type 'array'"],
        ];
    }

    public function testScalarImplicitPartial(): void
    {
        Config::setValue('scalar_implicit_partial', ['null' => ['int', 'string', 'false'], 'int' => ['string', 'null']]);
        $this->assertSame([
            'null' => ['int', 'string', 'false'],
            'int' => ['string', 'null', 'non-empty-string'],
            'non-zero-int' => ['string', 'non-empty-string'],
        ], Config::getValue('scalar_implicit_partial'), 'should add implied allowed casts');
        Config::setValue('scalar_implicit_partial', []);
        $this->assertSame([], Config::getValue('scalar_implicit_partial'));
    }
}
