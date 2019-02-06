<?php declare(strict_types=1);

namespace Phan\Tests\LanguageServer;

use Phan\LanguageServer\Utils;
use Phan\Tests\BaseTest;

/**
 * Unit tests of FileCache
 */
final class UtilsTest extends BaseTest
{
    public function testUriToPath()
    {
        $this->assertSame('/foo/bar/baz.php', Utils::uriToPath('file:///foo/bar/baz.php'));
        $this->assertSame('/foo/bar/baz:2.php', Utils::uriToPath('file:///foo/bar/baz:2.php'));
        $this->assertSame('/foo/bar/baz bat.php', Utils::uriToPath('file:///foo/bar/baz%20bat.php'));
    }

    public function testNormalizePathFromWindowsURI()
    {
        $this->assertSame('C:\foo\bar\baz.php', Utils::normalizePathFromWindowsURI('C:/foo/bar/baz.php'));
        $this->assertSame('C:\foo\bar\baz:2.php', Utils::normalizePathFromWindowsURI('/C:/foo/bar/baz:2.php'));
    }
}
