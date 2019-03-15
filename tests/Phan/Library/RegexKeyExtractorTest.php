<?php declare(strict_types=1);

namespace Phan\Tests\Library;

use Phan\Library\RegexKeyExtractor;
use Phan\Tests\BaseTest;

/**
 * Tests of RegexKeyExtractor
 */
final class RegexKeyExtractorTest extends BaseTest
{
    /**
     * Test that $expected_keys are extracted from $regex
     *
     * @param string $regex a regular expression for preg_match
     * @param array<int,int|string> $expected_keys
     * @return void
     * @dataProvider getKeysProvider
     */
    public function testGetKeys(string $regex, array $expected_keys)
    {
        $expected = self::toArraySet($expected_keys);
        $actual = RegexKeyExtractor::getKeys($regex);
        $this->assertSame($expected, $actual, "wrong patterns parsed for $regex");
    }

    /**
     * @return array<int,array{0:string,1:array<int,int|string>}>
     */
    public function getKeysProvider()  : array
    {
        return [
            ['//',          [0]],
            ['/\(a\)/',     [0]],
            ['/\(a\)/i',    [0]],
            ['()',          [0]],
            ['/(a)/',       [0, 1]],
            ['((a(b?)))',   [0, 1, 2]],
        ];
    }

    /**
     * @param array<int,int|string> $list
     * @return array<int|string,true>
     */
    private static function toArraySet(array $list) : array
    {
        $set = [];
        foreach ($list as $key) {
            $set[$key] = true;
        }
        return $set;
    }
}
