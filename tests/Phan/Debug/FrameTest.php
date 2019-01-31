<?php declare(strict_types=1);

namespace Phan\Tests\Debug;

use Phan\CodeBase;
use Phan\Debug\Frame;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Tests\BaseTest;
use stdClass;

/**
 * Unit tests of static helper methods used for debugging in Debug
 */
final class FrameTest extends BaseTest
{

    /**
     * @param mixed $value
     */
    private function assertHasEncodedValue(string $expected, $value)
    {
        $this->assertSame($expected, Frame::encodeValue($value), 'unexpected result of encodeValue');
    }

    /**
     * @throws \Exception
     */
    public function testToString()
    {
        $this->assertHasEncodedValue('[0, "", false, null]', [0, '', false, null]);
        $this->assertHasEncodedValue('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]', range(1, 10));
        $this->assertHasEncodedValue('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, ... 5 more element(s)]', range(1, 15));
        $this->assertHasEncodedValue('{"key":"value://something"}', ['key' => 'value://something']);
        $this->assertHasEncodedValue('[]', []);
        $this->assertHasEncodedValue('Closure', function () {
        });
        $this->assertHasEncodedValue('stdClass({})', new stdClass());
        $this->assertHasEncodedValue('stdClass({"key":"value","2":"other"})', (object)['key' => 'value', '2' => 'other']);
        $this->assertHasEncodedValue('Phan\Language\FQSEN\FullyQualifiedClassName(\ast\Node)', FullyQualifiedClassName::fromFullyQualifiedString('ast\Node'));
        $this->assertHasEncodedValue('Phan\Language\Type(\stdClass)', Type::fromFullyQualifiedString('\stdClass'));
        $this->assertHasEncodedValue('Phan\Language\UnionType(array<int,string>|false)', UnionType::fromFullyQualifiedString('array<int,string>|false'));
        $code_base = new CodeBase([], [], [], [], []);
        $context = (new Context())->withFile('src/somefile.php')->withLineNumberStart(15);
        $this->assertHasEncodedValue('[Phan\CodeBase({}), Phan\Language\Context(src/somefile.php:15)]', [$code_base, $context]);
    }
}
