<?php declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Language\UnionType;
use Phan\Plugin\Internal\MethodSearcherPlugin;
use Phan\Tests\BaseTest;
use Phan\Tests\CodeBaseAwareTestInterface;

/**
 * Unit tests of Context and scopes
 */
final class MethodSearcherPluginTest extends BaseTest implements CodeBaseAwareTestInterface
{
    /** @var CodeBase The code base within which this unit test is operating */
    private $code_base = null;

    public function setCodeBase(CodeBase $code_base = null)
    {
        // @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty
        $this->code_base = $code_base;
    }

    /**
     * @dataProvider getTypeMatchingBonusProvider
     */
    public function testGetTypeMatchingBonus(float $expected_score, string $actual, string $desired)
    {
        $actual_signature_type = UnionType::fromFullyQualifiedString($actual);
        $desired_signature_type = UnionType::fromFullyQualifiedString($desired);
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->assertSame($expected_score, MethodSearcherPlugin::getTypeMatchingBonus($this->code_base, $actual_signature_type, $desired_signature_type));
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
        $actual_signature_types = \array_map('\Phan\Language\UnionType::fromFullyQualifiedString', $actual);
        $desired_signature_types = \array_map('\Phan\Language\UnionType::fromFullyQualifiedString', $desired);
        // @phan-suppress-next-line PhanAccessMethodInternal, PhanPossiblyNullTypeArgument
        $this->assertSame($expected_score, MethodSearcherPlugin::matchesParamTypes($this->code_base, $actual_signature_types, $desired_signature_types));
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
