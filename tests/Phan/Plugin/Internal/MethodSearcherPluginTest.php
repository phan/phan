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

    public function setCodeBase(CodeBase $code_base = null) : void
    {
        // @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty
        $this->code_base = $code_base;
    }

    /**
     * @dataProvider getTypeMatchingBonusProvider
     */
    public function testGetTypeMatchingBonus(float $expected_score, string $actual, string $desired) : void
    {
        $actual_signature_type = UnionType::fromFullyQualifiedPHPDocString($actual);
        $desired_signature_type = UnionType::fromFullyQualifiedPHPDocString($desired);
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->assertSame($expected_score, MethodSearcherPlugin::getTypeMatchingBonus($this->code_base, $actual_signature_type, $desired_signature_type));
    }

    /**
     * @return list<list>
     */
    public function getTypeMatchingBonusProvider() : array
    {
        return [
            [0.0, 'int', 'mixed'],
            [0.0, 'int', ''],
            [0.0, '', 'int'],
            [0.6, 'int|string', 'int'],
            // exact type matches have the highest score
            [5.1, 'int|string', 'int|string'],
            [5.1, '\stdClass', '\stdClass'],
        ];
    }

    /**
     * @param list<string> $actual
     * @param list<string> $desired
     * @dataProvider matchesParamTypesProvider
     */
    public function testMatchesParamTypes(float $expected_score, array $actual, array $desired) : void
    {
        $actual_signature_types = \array_map('\Phan\Language\UnionType::fromFullyQualifiedPHPDocString', $actual);
        $desired_signature_types = \array_map('\Phan\Language\UnionType::fromFullyQualifiedPHPDocString', $desired);
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->assertSame($expected_score, MethodSearcherPlugin::matchesParamTypes($this->code_base, $actual_signature_types, $desired_signature_types));
    }

    /**
     * @return list<list>
     */
    public function matchesParamTypesProvider() : array
    {
        return [
            [8.6, ['\stdClass'], ['\stdClass']],
            [8.5, ['?\stdClass'], ['\stdClass']],
            [8.5, ['\stdClass'], ['?\stdClass']],
            [4.1, ['\stdClass'], ['object']],
            [6.1, ['\stdClass|false'], ['\stdClass']],
            [6.0, ['?\stdClass|?false'], ['\stdClass']],
            [8.6, ['int'], ['int']],
            [4.5, ['?int'], ['int']],
            [4.1, ['int|false'], ['int']],
            [0.0, ['int'], []],
            [2.0, [], ['int']],
            [14.2, ['\stdClass', 'bool'], ['bool', '\stdClass']],
        ];
    }
}
