<?php

declare(strict_types=1);

namespace Phan\Tests\AST;

use Phan\AST\ASTHasher;
use Phan\Tests\BaseTest;
use function bin2hex;
use function hex2bin;
use const PHP_INT_SIZE;

/**
 * Tests of ASTHasher generating 16-byte binary hashes of nodes
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class ASTHasherTest extends BaseTest
{
    private function assertSameBinaryString(string $expected, string $actual): void {
        $this->assertSame(bin2hex($expected), bin2hex($actual), "expected the same binary data");
    }

    /**
     * @suppress PhanPossiblyFalseTypeArgument
     */
    public function testHash(): void
    {
        if (PHP_INT_SIZE == 8) {
            $expected = "\0\0\0\0\0\0\0\0\x01\x23\x45\x67\x89\xab\xcd\xef";
            $key = 0x0123456789abcdef;

            $this->assertSameBinaryString($expected, ASTHasher::hashKey($key));
            $this->assertSameBinaryString($expected, ASTHasher::hash($key));
        }
        $this->assertSameBinaryString("\0\0\0\0\0\0\0\2\0\0\0\0\0\0\0\0", ASTHasher::hash(null));
        $expected1 = hex2bin('3c6e0b8a9c15224a8228b9a98ca1531d');
        $this->assertSameBinaryString($expected1, ASTHasher::hashKey('key'));
        $this->assertSameBinaryString($expected1, ASTHasher::hash('key'));

        $expected2 = hex2bin('d41d8cd98f00b204e9800998ecf8427e');
        $this->assertSameBinaryString($expected2, ASTHasher::hashKey(''));
        $this->assertSameBinaryString($expected2, ASTHasher::hash(''));
    }
}
