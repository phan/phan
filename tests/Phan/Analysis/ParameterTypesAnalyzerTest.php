<?php declare(strict_types = 1);
namespace Phan\Tests\Analysis;

use Phan\Analysis\ParameterTypesAnalyzer;
use Phan\Config;
use Phan\Tests\BaseTest;
use Phan\Language\UnionType;

/**
 * Unit tests of helper methods of ParameterTypesAnalyzer
 */
final class ParameterTypesAnalyzerTest extends BaseTest
{
    public function setUp()
    {
        parent::setUp();
        Config::setValue('prefer_narrowed_phpdoc_return_type', true);
        Config::setValue('check_docblock_signature_return_type_match', true);
    }

    private function assertSameNarrowedType(
        string $expected_type_string,
        string $phpdoc_return_type_string,
        string $real_return_type_string
    ) {
        $expected_type = UnionType::fromFullyQualifiedString($expected_type_string);
        $phpdoc_return_type = UnionType::fromFullyQualifiedString($phpdoc_return_type_string);
        $real_return_type = UnionType::fromFullyQualifiedString($real_return_type_string);

        $actual_normalized_type = ParameterTypesAnalyzer::normalizeNarrowedParamType($phpdoc_return_type, $real_return_type);


        $msg = "Expected normalizeNarrowedParamType($phpdoc_return_type, $real_return_type) to be $expected_type_string";
        $this->assertSame($expected_type_string, (string)$actual_normalized_type, $msg);
        $this->assertTrue($actual_normalized_type->isEqualTo($expected_type), $msg);
    }

    private function assertNullNarrowedType(
        string $phpdoc_return_type_string,
        string $real_return_type_string
    ) {
        $phpdoc_return_type = UnionType::fromFullyQualifiedString($phpdoc_return_type_string);
        $real_return_type = UnionType::fromFullyQualifiedString($real_return_type_string);

        $actual_normalized_type = ParameterTypesAnalyzer::normalizeNarrowedParamType($phpdoc_return_type, $real_return_type);


        $msg = "Expected normalizeNarrowedParamType($phpdoc_return_type, $real_return_type) to be null";
        $this->assertNull($actual_normalized_type, $msg);
    }

    public function testNormalizeNarrowedParamType()
    {
        $this->assertSameNarrowedType('int', 'int', 'int');
        $this->assertSameNarrowedType('array<int,string>', 'array<int,string>', 'array');
        $this->assertSameNarrowedType('array<int,string>', 'array<int,string>', 'iterable');
        $this->assertSameNarrowedType('array{0:\stdClass}', 'array{0:\stdClass}', 'array');
        $this->assertSameNarrowedType('array{0:\stdClass}', 'array{0:\stdClass}', 'iterable');
        $this->assertNullNarrowedType('null', '');
    }
}
