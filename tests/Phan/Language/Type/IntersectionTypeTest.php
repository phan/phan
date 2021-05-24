<?php

declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\Language\Type\IntersectionType;
use Phan\Tests\BaseTest;
use ReflectionClass;
use ReflectionMethod;

/**
 * Checks that IntersectionType overrides methods of Type where appropriate
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 */
final class IntersectionTypeTest extends BaseTest
{
    private const SKIPPED_METHOD_NAMES = [
        // Magic methods
        '__wakeup',
        '__clone',
        'memoize',  // From a trait
        'asPHPDocUnionType',
        'asRealUnionType',
        // Callers should check
        'getName',
        'getNamespace',
        // Default implementation works as intended
        'isNullable',
        'isNullableLabeled',
        'asGenericArrayType',
        'asNonFalseyType',
        'asScalarType', // null
        'isInBoolFamily', // null
        'isArrayAccess', // uses asExpandedTypes
        'isArrayLike', // uses asExpandedTypes
        'isArrayOrArrayAccessSubType', // uses asExpandedTypes
        'isSubtypeOfAnyTypeInSet', // uses isSubtypeOf
        'isSubclassOf', // uses asExpandedTypes
        'canCastToAnyTypeInSet', // uses canCastToType
        'canCastToAnyTypeInSetWithoutConfig',
        'hasSameNamespaceAndName',  // callers should avoid calling this on intersection type
        'hasTemplateParameterTypes',  // callers should avoid calling this
        'isExclusivelyNarrowedFormOrEquivalentTo',  // uses asExpandedTypes
        'shouldBeReplacedBySpecificTypes',  // does this work as intended?

        // Callers should check if it has a single fqsen
        'asFQSENString',

        // This all works assuming that intersection types are only created for objects.
        // This may need to be rethought.
        'canSatisfyComparison', // null
        'isNativeType',
        'isGenerator', // null
        'isPossiblyFalse',
        'isPossiblyFalsey',
        'isPossiblyNumeric',
        'isPossiblyTrue',
        'isPossiblyTruthy',
        'isPrintableScalar',
        'isAlwaysFalse',
        'isAlwaysFalsey',
        'isAlwaysTrue',
        'isAlwaysTruthy',
        'isValidNumericOperand',
        'isGenericArray',
        'asNonFalseType',
        'asNonLiteralType',
        'asNonTrueType',
        'asNonTruthyType',
        'asArrayType',
        'getTypeAfterIncOrDec',
        'getNormalizationFlags',

        'hasArrayShapeOrLiteralTypeInstances',
        'hasArrayShapeTypeInstances',
        'isDefiniteNonObjectType',
        'withFlattenedTopLevelArrayShapeTypeInstances',
        'withFlattenedArrayShapeOrLiteralTypeInstances',

        // This is only checking the first layer of template types
        'getTemplateParameterTypeList',
        'getTemplateParameterTypeMap',
        'getTemplateTypeExtractorClosure', // TODO: Not sure how to implement

        'isGenerator', // this is a final class, why would it be in an intersection type
        'isThrowableInterface', // ???

        'asExpandedTypes',  // Override computeExpandedTypes
        'asExpandedTypesPreservingTemplate',  // Override computeExpandedTypesPreservingTemplate
    ];

    public function testMethods(): void
    {
        $failures = '';
        $method_set = [];
        foreach ((new ReflectionClass(IntersectionType::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }
            $method_name = $method->getName();
            $actual_class = $method->class;
            if (\in_array($method_name, self::SKIPPED_METHOD_NAMES, true)) {
                if (IntersectionType::class === $actual_class) {
                    $failures .= "no longer need to skip $method_name\n";
                }
                continue;
            }
            if (IntersectionType::class !== $actual_class) {
                $method_set[$method_name] = $method;
            }
        }
        \uksort($method_set, 'strcmp');
        foreach ($method_set as $method_name => $method) {
            $actual_class = $method->class;
            $failures .= "unexpected declaring class $actual_class for $method_name\n";
        }
        $this->assertSame('', \trim($failures));
    }
}
