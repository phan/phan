<?php declare(strict_types=1);

namespace Phan\Tests\Library;

use Phan\Library\StringUtil;
use Phan\Tests\BaseTest;

/**
 * Unit tests of StringUtil
 */
final class StringUtilTest extends BaseTest
{
    public function testJsonEncode()
    {
        $this->assertSame("{}", StringUtil::jsonEncode(new \stdClass()));
    }

    public function testAsSingleLineUtf8()
    {
        $this->assertSame("a�b�", StringUtil::asSingleLineUtf8("a\nb\n"));
        $this->assertSame("��", StringUtil::asSingleLineUtf8("\x80\x81"));
        $this->assertSame("", StringUtil::asSingleLineUtf8(""));
        $this->assertSame("0", StringUtil::asSingleLineUtf8("0"));
    }

    public function testEncodeValue()
    {
        $this->assertSame("'0'", StringUtil::encodeValue("0"));
        $this->assertSame("''", StringUtil::encodeValue(""));
        $this->assertSame('"a\nb"', StringUtil::encodeValue("a\nb"));
        $this->assertSame('"\0"', StringUtil::encodeValue("\x00"));
    }

    public function testEncodeValueList()
    {
        $this->assertSame('"\0","a\nb",\'x\'', StringUtil::encodeValueList(',', ["\0", "a\nb", "x"]));
        $this->assertSame('', StringUtil::encodeValueList(',', []));
    }
}
