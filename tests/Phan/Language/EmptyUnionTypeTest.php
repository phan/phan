<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\EmptyUnionType;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\UnionType;
use Phan\Tests\BaseTest;

use Closure;
use Generator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use TypeError;

/**
 * Checks that EmptyUnionType behaves the same way as an empty UnionType instance
 * @phan-file-suppress PhanThrowTypeAbsent it's a test
 */
final class EmptyUnionTypeTest extends BaseTest
{
    const SKIPPED_METHOD_NAMES = [
        'unserialize',  // throws
        '__construct',
        // UnionType implementation can't be optimized
        'withIsPossiblyUndefined',
        'getIsPossiblyUndefined',
    ];

    public function testMethods()
    {
        $this->assertTrue(class_exists(UnionType::class));  // Force the autoloader to load UnionType before attempting to load EmptyUnionType
        $failures = '';
        foreach ((new ReflectionClass(EmptyUnionType::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }
            $method_name = $method->getName();
            if (\in_array($method_name, self::SKIPPED_METHOD_NAMES, true)) {
                continue;
            }
            $failures .= $this->checkHasSameImplementationForEmpty($method);
            $actual_class = $method->getDeclaringClass()->getName();
            if (EmptyUnionType::class !== $actual_class) {
                $failures .= "unexpected declaring class $actual_class for $method_name\n";
            }
        }
        $this->assertSame('', $failures);
    }

    // Returns the test errors to show as a string, or the empty string on success
    public function checkHasSameImplementationForEmpty(ReflectionMethod $method) : string
    {
        $method_name = $method->getName();
        if (!method_exists(UnionType::class, $method_name)) {
            return '';
        }

        $empty_regular = new UnionType([]);

        $candidate_arg_lists = $this->generateArgLists($method);
        if (count($candidate_arg_lists) === 0) {
            throw new RuntimeException("Failed to generate 1 or more candidate arguments lists for $method_name");
        }
        $failures = '';
        foreach ($candidate_arg_lists as $arg_list) {
            $expected_result = $empty_regular->{$method_name}(...$arg_list);
            $actual_result = $empty_regular->{$method_name}(...$arg_list);
            if ($expected_result instanceof Generator && $actual_result instanceof Generator) {
                $expected_result = iterator_to_array($expected_result);
                $actual_result = iterator_to_array($actual_result);
            }
            if ($expected_result !== $actual_result) {
                $failures .= "Expected $method_name implementation to be the same for " . serialize($arg_list) . "\n";
            }
        }
        return $failures;
    }

    /**
     * Generate one or more argument lists to test a method
     * implementation in a subclass of UnionType
     *
     * @return array<int,array>
     */
    public function generateArgLists(ReflectionMethod $method) : array
    {
        $list_of_arg_list = [[]];

        foreach ($method->getParameters() as $param) {
            if ($param->isOptional()) {
                break;
            }
            $possible_new_args = $this->getPossibleArgValues($param);
            if (count($possible_new_args) === 0) {
                throw new RuntimeException("Failed to generate 1 or more candidate arguments for $param");
            }
            $new_list_of_arg_list = [];
            foreach ($possible_new_args as $arg) {
                foreach ($list_of_arg_list as $prev_args) {
                    $new_list_of_arg_list[] = array_merge($prev_args, [$arg]);
                }
            }
            $list_of_arg_list = $new_list_of_arg_list;
        }
        if (count($list_of_arg_list) === 0) {
            throw new RuntimeException("Failed to generate 1 or more candidate arguments lists for $param");
        }
        return $list_of_arg_list;
    }

    public function getPossibleArgValues(ReflectionParameter $param) : array
    {
        $type = $param->getType();
        $type_name = (string)$type;
        switch ($type_name) {
            case 'bool':
                return [false, true];
            case 'array':
                return [[]];
            case 'int':
                if ($param->getName() === 'key_type') {
                    return [GenericArrayType::KEY_INT, GenericArrayType::KEY_STRING, GenericArrayType::KEY_MIXED];
                }
                break;
            case CodeBase::class:
                return [new CodeBase([], [], [], [], [])];
            case Context::class:
                return [new Context()];
            case Type::class:
                return [
                    IntType::instance(false),
                    ArrayType::instance(false),
                    FalseType::instance(true),
                    ObjectType::instance(false),
                    MixedType::instance(false),
                    Type::fromFullyQualifiedString('\stdClass'),
                ];
            case UnionType::class:
                return [
                    IntType::instance(false)->asUnionType(),
                    UnionType::empty(),
                    new UnionType([FalseType::instance(false), ArrayType::instance(false)]),
                    ArrayType::instance(false)->asUnionType(),
                    FalseType::instance(true)->asUnionType(),
                    ObjectType::instance(false)->asUnionType(),
                    MixedType::instance(false)->asUnionType(),
                    Type::fromFullyQualifiedString('\stdClass')->asUnionType(),
                ];
            case Closure::class:
                return [
                    function (...$unused_args) : bool {
                        return false;
                    },
                    function (...$unused_args) : bool {
                        return true;
                    },
                ];
            case '':
                if ($param->getName() === 'field_key') {
                    return ['', 'key', 0, 2, false, 2.5];
                }
                break;
        }
        throw new TypeError("Unable to handle param {$type_name} \${$param->getName()}");
    }
}
