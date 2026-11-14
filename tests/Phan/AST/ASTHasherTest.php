<?php

declare(strict_types=1);

namespace Phan\Tests\AST;

use ast\Node;
use Phan\AST\ASTHasher;
use Phan\Config;
use Phan\Tests\TestBase;

use function bin2hex;
use function hex2bin;

use const PHP_INT_SIZE;

/**
 * Tests of ASTHasher generating 16-byte binary hashes of nodes
 */
final class ASTHasherTest extends TestBase
{
    private function assertSameBinaryString(string $expected, string $actual): void
    {
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

            $this->assertSameBinaryString($expected, ASTHasher::hash($key));

            $key = -1;
            $expected = "\0\0\0\0\0\0\0\0\xff\xff\xff\xff\xff\xff\xff\xff";

            $this->assertSameBinaryString($expected, ASTHasher::hash($key));
        } else {
            $expected = "\0\0\0\0\0\0\0\0\0\0\0\0\x01\x23\x45\x67";
            $key = 0x01234567;

            $this->assertSameBinaryString($expected, ASTHasher::hash($key));

            $key = -1;
            $expected = "\0\0\0\0\0\0\0\0\0\0\0\0\xff\xff\xff\xff";

            $this->assertSameBinaryString($expected, ASTHasher::hash($key));
        }
        $this->assertSameBinaryString("\0\0\0\0\0\0\0\2\0\0\0\0\0\0\0\0", ASTHasher::hash(null));
        $hash_algo = \in_array('xxh128', \hash_algos(), true) ? 'xxh128' : 'md5';
        $expected1 = hex2bin(hash($hash_algo, 'key'));
        $this->assertSameBinaryString($expected1, ASTHasher::hash('key'));

        $expected2 = hex2bin(hash($hash_algo, ''));
        $this->assertSameBinaryString($expected2, ASTHasher::hash(''));

        $expected2 = hex2bin('0000000000000001000000000000f83f');
        $this->assertSameBinaryString($expected2, ASTHasher::hash(1.5));
    }

    public function testHashOfNode(): void
    {
        $node = \ast\parse_code('<?php $a + 1;', Config::AST_VERSION)->children[0];
        $this->assertInstanceOf(Node::class, $node);
        $same = \ast\parse_code("<?php\n\n\$a   +   1;", Config::AST_VERSION)->children[0];
        $this->assertInstanceOf(Node::class, $same);
        $other = \ast\parse_code('<?php $a + 2;', Config::AST_VERSION)->children[0];
        $this->assertInstanceOf(Node::class, $other);

        $hash = ASTHasher::hash($node);
        $this->assertSame(16, \strlen($hash));
        $this->assertSameBinaryString($hash, ASTHasher::hash($node));
        // Line numbers and spacing are ignored
        $this->assertSameBinaryString($hash, ASTHasher::hash($same));
        $this->assertNotSame(bin2hex($hash), bin2hex(ASTHasher::hash($other)));
    }

    /**
     * The hash is not stored on the node: the first dynamic property of an object makes PHP allocate a property table for it.
     */
    public function testHashAddsNoDynamicProperty(): void
    {
        $node = \ast\parse_code('<?php $a + 1;', Config::AST_VERSION)->children[0];
        $this->assertInstanceOf(Node::class, $node);
        $properties_before = \get_object_vars($node);
        ASTHasher::hash($node);
        $this->assertSame(\array_keys($properties_before), \array_keys(\get_object_vars($node)));
    }

    /**
     * A clone that is modified afterwards (e.g. by ASTSimplifier) must not keep the hash of the original node.
     */
    public function testHashOfModifiedClone(): void
    {
        $node = \ast\parse_code('<?php $a + 1;', Config::AST_VERSION)->children[0];
        $this->assertInstanceOf(Node::class, $node);
        $hash = ASTHasher::hash($node);

        $clone = clone($node);
        $this->assertSameBinaryString($hash, ASTHasher::hash(clone($node)));
        $clone->children['right'] = 2;
        $this->assertNotSame(bin2hex($hash), bin2hex(ASTHasher::hash($clone)));
        $this->assertSameBinaryString($hash, ASTHasher::hash($node));
    }
}
