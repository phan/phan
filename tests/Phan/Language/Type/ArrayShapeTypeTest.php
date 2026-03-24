<?php

declare(strict_types=1);

namespace Phan\Tests\Language\Type;

use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\StaticType;
use Phan\Tests\TestBase;

/**
 * Unit tests of ArrayShapeType
 */
final class ArrayShapeTypeTest extends TestBase
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

    /**
     * Tests that withStaticResolvedInContext does not recurse indefinitely
     * on deeply nested array shapes containing `static` types.
     *
     * This reproduces a real-world issue where mutually recursive methods
     * returning array shapes caused type lengths to grow to 950K+ characters
     * and withStaticResolvedInContext took 130+ seconds per call.
     * @throws \Phan\Exception\FQSENException
     */
    public function testWithStaticResolvedInContextDepthGuard(): void
    {
        $context = (new Context())->withScope(
            new ClassScope(
                new GlobalScope(),
                FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass'),
                0
            )
        );

        // Build a deeply nested array shape where each level has a `static` field:
        // array{nested: array{nested: ..., s: static}, s: static}
        // 20 levels deep, well beyond the MAX_RESOLVE_DEPTH of 10
        $static_type = StaticType::instance(false)->asPHPDocUnionType();
        $inner = ArrayShapeType::fromFieldTypes(['s' => $static_type], false);
        for ($i = 0; $i < 19; $i++) {
            $inner = ArrayShapeType::fromFieldTypes(
                ['nested' => $inner->asPHPDocUnionType(), 's' => $static_type],
                false
            );
        }

        // This should complete quickly due to the depth guard, not hang
        $resolved = $inner->withStaticResolvedInContext($context);
        $this->assertInstanceOf(ArrayShapeType::class, $resolved);

        // The outer levels (depth < 10) should have `static` resolved to `\TestClass`,
        // but the innermost levels (depth >= 10) should retain `static`
        $resolved_str = $resolved->__toString();
        $this->assertStringContainsString('TestClass', $resolved_str);
        $this->assertStringContainsString('static', $resolved_str);
    }
}
