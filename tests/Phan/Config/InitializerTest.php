<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use Phan\Config\Initializer;

/**
 * Unit tests of Phan's analysis creating the expected element representations on snippets of code.
 */
final class ConfigInitializerTest extends BaseTest
{
    public function testInitializesValid() : void
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
    public function testDetermineTargetPHPVersion(?string $expected_version, string $php_string) : void
    {
        $actual = Initializer::determineTargetPHPVersion(['require' => ['php' => $php_string]])[0];
        $this->assertSame($expected_version, $actual);
    }

    /**
     * Phan determines the minimum version based on https://getcomposer.org/doc/articles/versions.md
     * @return array<int,array>
     */
    public function determineTargetPHPVersionProvider() : array
    {
        return [
            [null, 'nonsense'],
            ['7.0', '>= 5.6'],  // deliberately choose 7.0
            ['7.0', '^7.0.0'],
            ['7.1', '^7.1.14'],
            ['7.2', '~7.2.0'],
            ['7.2', '>= 7.2.0 < 7.4'],
            ['7.1', '>= 7.1.1 < 7.3'],
            ['7.3', '^7.3.0'],
        ];
    }

    /**
     * @param array<int,string> $expected_dirs
     * @param array<int,string> $expected_files
     * @param array<int,string> $dirs
     * @param array<int,string> $files
     * @dataProvider filterDirectoryAndFileListProvider
     */
    public function testFilterDirectoryAndFileList(array $expected_dirs, array $expected_files, array $dirs, array $files) : void
    {
        $this->assertSame([$expected_dirs, $expected_files], Initializer::filterDirectoryAndFileList($dirs, $files));
    }

    /**
     * @return array<int,array<int,array<int,string>>>
     */
    public function filterDirectoryAndFileListProvider() : array
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
        ];
    }
}
