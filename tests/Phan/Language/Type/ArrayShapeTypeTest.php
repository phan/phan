<?php

declare(strict_types=1);

namespace Phan\Tests\Language\Type;

use Phan\Language\Type\ArrayShapeType;
use Phan\Tests\BaseTest;

/**
 * Unit tests of ArrayShapeType
 */
final class ArrayShapeTypeTest extends BaseTest
{
    private function assertUnescapedKeyEquals(string $expected, string $unescaped): void
    {
        $this->assertSame($expected, ArrayShapeType::unescapeKey($unescaped), "unexpected value for $unescaped");
    }

    public function testUnescapedKey(): void
    {
        $this->assertUnescapedKeyEquals("", "");
        $this->assertUnescapedKeyEquals("\\", "\\\\");
        $this->assertUnescapedKeyEquals("\n", "\\n");
        $this->assertUnescapedKeyEquals("\n\\", "\\x0a\\\\");
        $this->assertUnescapedKeyEquals("", "");
        $this->assertUnescapedKeyEquals("~", "\\x7e");
        $this->assertUnescapedKeyEquals("\x1c", "\\x1c");
        $this->assertUnescapedKeyEquals("\n\t\r\\<", "\\n\\t\\r\\\\\x3c");
        $this->assertUnescapedKeyEquals("hello world", "hello\x20world");
    }

    private function assertEscapedKeyEquals(string $expected, string $unescaped): void
    {
        $this->assertSame($expected, ArrayShapeType::escapeKey($unescaped), "unexpected escaped key");
    }

    public function testEscapedKey(): void
    {
        $this->assertEscapedKeyEquals("", "");
        $this->assertEscapedKeyEquals("\\\\", "\\");
        $this->assertEscapedKeyEquals("\\n\\\\", "\x0a\\");
        $this->assertEscapedKeyEquals("\\x7e", "~");
        $this->assertEscapedKeyEquals("\\x1c", "\x1c");
        $this->assertEscapedKeyEquals("\\n\\t\\r\\\\\\x3a", "\n\t\r\\:");
    }
}
