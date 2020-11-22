<?php

declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\Config;
use Phan\Language\FileRef;
use Phan\Tests\BaseTest;

/**
 * Unit tests of FileRef
 */
final class FileRefTest extends BaseTest
{
    private function expectProjectRelativePath(string $expected_path, string $original_path): void
    {
        $this->assertSame(\str_replace('/', \DIRECTORY_SEPARATOR, $expected_path), FileRef::getProjectRelativePathForPath($original_path));
    }
    public function testGetProjectRelativePathForPath(): void
    {
        $root_dir = \dirname(__DIR__, 3);
        $basename = \basename($root_dir);  // The basename of the realpath is almost always 'phan'
        Config::setProjectRootDirectory($root_dir);
        $this->expectProjectRelativePath('src/Phan/CLI.php', 'src/Phan/CLI.php');
        $this->expectProjectRelativePath('src/Phan/CLI.php', $root_dir . '/src/Phan/CLI.php');
        $this->expectProjectRelativePath('src/Phan/CLI.php', "../$basename/src/Phan/CLI.php");
        // TODO: could normalize on Windows?
        $this->assertSame('../other/path.txt', FileRef::getProjectRelativePathForPath('../other/path.txt'));
        // Projects starting with the same folder prefix (phan) should be properly normalized
        $this->assertSame('../phantastic-project/path.txt', FileRef::getProjectRelativePathForPath('../phantastic-project/path.txt'));
        $this->assertSame(\dirname($root_dir) . '/other.php', FileRef::getProjectRelativePathForPath(\dirname($root_dir) . '/other.php'));
    }
}
