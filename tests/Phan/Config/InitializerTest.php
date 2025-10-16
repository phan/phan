<?php

declare(strict_types=1);

namespace Phan\Tests\Config;

use Phan\Config;
use Phan\Config\Initializer;
use Phan\Tests\TestBase;

/**
 * Unit tests of Phan's analysis creating the expected element representations on snippets of code.
 */
final class InitializerTest extends TestBase
{
    public function testInitializesValid(): void
    {
        for ($init_level = 1; $init_level <= 5; $init_level++) {
            // @phan-suppress-next-line PhanAccessMethodInternal, PhanThrowTypeAbsentForCall
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

    /**
     * @param ?string $expected_version
     * @dataProvider determineTargetPHPVersionProvider
     */
    public function testDetermineTargetPHPVersion(?string $expected_version, string $php_string): void
    {
        $actual = Initializer::determineTargetPHPVersion(['require' => ['php' => $php_string]])[0];
        $this->assertSame($expected_version, $actual);
    }

    /**
     * Phan determines the minimum version based on https://getcomposer.org/doc/articles/versions.md
     * @return list<list>
     */
    public static function determineTargetPHPVersionProvider(): array
    {
        return [
            [null, 'nonsense'],
            ['8.1', '^8.1.14'],
            ['8.2', '~8.2.0'],
            ['8.2', '>= 8.2.0 < 8.4'],
            ['8.1', '>= 8.1.1 < 8.3'],
            ['8.3', '^8.3.0'],
        ];
    }

    /**
     * @param list<string> $expected_dirs
     * @param list<string> $expected_files
     * @param list<string> $dirs
     * @param list<string> $files
     * @dataProvider filterDirectoryAndFileListProvider
     */
    public function testFilterDirectoryAndFileList(array $expected_dirs, array $expected_files, array $dirs, array $files): void
    {
        $this->assertSame([$expected_dirs, $expected_files], Initializer::filterDirectoryAndFileList($dirs, $files));
    }

    /**
     * @return list<list<list<string>>>
     */
    public static function filterDirectoryAndFileListProvider(): array
    {
        return [
            [
                [],
                [],
                [],
                [],
            ],
            [
                ['vendor/a/b/c', 'vendor/x/y/src'],
                ['vendor/d/e/f/src/x.php', 'vendor/g/h/i.php'],
                ['vendor/a/b/c', 'vendor/x/y/src'],
                ['vendor/d/e/f/src/x.php', 'vendor/g/h/i.php'],
            ],
            [
                ['vendor/foo/bar'],
                [],
                ['vendor/foo/bar', 'vendor/foo/bar/baz'],
                []
            ],
            [
                ['vendor/foo/bar'],
                ['vendor/other/file/loader.php'],
                ['vendor/foo/bar', 'vendor/foo/bar/baz'],
                ['vendor/foo/bar/some/loader.php', 'vendor/other/file/loader.php']
            ],
            [
                ['.', '../Base', 'C:\dir'],
                ['/var/www/unrelated.php'],
                ['vendor/foo/bar', '.', '../Base', 'C:\dir'],
                ['lib/init.php', '/var/www/unrelated.php', 'C:\dir\some_file.php']
            ],
        ];
    }
}
