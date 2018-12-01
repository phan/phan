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

    /**
     * Based on BaseTest
     * TODO: Investigate instantiating CodeBase in a cheaper way (lazily?)
     * @suppress PhanReadOnlyProtectedProperty read by phpunit framework
     */
    // phpcs:ignore
    protected $backupStaticAttributesBlacklist = [
        'Phan\AST\PhanAnnotationAdder' => [
            'closures_for_kind',
        ],
        'Phan\Language\Type' => [
            'canonical_object_map',
            'internal_fn_cache',
        ],
        'Phan\Language\Type\LiteralIntType' => [
            'nullable_int_type',
            'non_nullable_int_type',
        ],
        'Phan\Language\Type\LiteralStringType' => [
            'nullable_string_type',
            'non_nullable_string_type',
        ],
        'Phan\Language\UnionType' => [
            'empty_instance',
        ],
        // Back this up because it takes 306 ms.
        'Phan\Tests\Language\UnionTypeTest' => [
            'code_base',
        ],
    ];

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
