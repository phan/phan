<?php declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Language\UnionType;
use Phan\Plugin\Internal\MethodSearcherPlugin;
use Phan\Tests\BaseTest;

/**
 * Unit tests of Context and scopes
 */
final class MethodSearcherPluginTest extends BaseTest
{
    /** @var CodeBase|null The code base within which this unit test is operating */
    protected static $code_base = null;

    protected function setUp()
    {
        // Deliberately not calling parent::setUp()
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;
        if (self::$code_base === null) {
            self::$code_base = new CodeBase(
                $internal_class_name_list,
                $internal_interface_name_list,
                $internal_trait_name_list,
                CodeBase::getPHPInternalConstantNameList(),
                $internal_function_name_list
            );
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        // @phan-suppress-next-line PhanTypeMismatchProperty
        self::$code_base = null;
    }

    /**
     * @dataProvider getTypeMatchingBonusProvider
     */
    public function testGetTypeMatchingBonus(float $expected_score, string $actual, string $desired)
    {
        $actual_signature_type = UnionType::fromFullyQualifiedString($actual);
        $desired_signature_type = UnionType::fromFullyQualifiedString($desired);
        // @phan-suppress-next-line PhanAccessMethodInternal, PhanPossiblyNullTypeArgument
        $this->assertSame($expected_score, MethodSearcherPlugin::getTypeMatchingBonus(self::$code_base, $actual_signature_type, $desired_signature_type));
    }

    /**
     * @return array<int,array>
     */
    public function getTypeMatchingBonusProvider() : array
    {
        return [
            [0.0, 'int', 'mixed'],
            [0.0, 'int', ''],
            [0.0, '', 'int'],
            [0.5, 'int|string', 'int'],
            [1.0, 'int|string', 'int|string'],
            [5.0, '\stdClass', '\stdClass'],
        ];
    }

    /**
     * @param array<int,string> $actual
     * @param array<int,string> $desired
     * @dataProvider matchesParamTypesProvider
     */
    public function testMatchesParamTypes(float $expected_score, array $actual, array $desired)
    {
        $actual_signature_types = array_map('\Phan\Language\UnionType::fromFullyQualifiedString', $actual);
        $desired_signature_types = array_map('\Phan\Language\UnionType::fromFullyQualifiedString', $desired);
        // @phan-suppress-next-line PhanAccessMethodInternal, PhanPossiblyNullTypeArgument
        $this->assertSame($expected_score, MethodSearcherPlugin::matchesParamTypes(self::$code_base, $actual_signature_types, $desired_signature_types));
    }

    /**
     * @return array<int,array>
     */
    public function matchesParamTypesProvider() : array
    {
        return [
            [8.5, ['\stdClass'], ['\stdClass']],
            [6.0, ['\stdClass|false'], ['\stdClass']],
            [4.5, ['int'], ['int']],
            [4.0, ['int|false'], ['int']],
            [0.0, ['int'], []],
            [2.0, [], ['int']],
            [10.0, ['\stdClass', 'bool'], ['bool', '\stdClass']],
        ];
    }
}
