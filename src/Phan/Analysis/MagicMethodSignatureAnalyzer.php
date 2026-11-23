<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\Method;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\IntersectionType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NeverType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;

/**
 * Checks the declarations of magic methods against the rules that PHP enforces when it compiles a class
 * (zend_check_magic_method_implementation() in php-src): the number of parameters, parameters passed by reference,
 * `static`, the visibility, and the declared types of the parameters and of the return value.
 *
 * Only the declared (real) types matter, and classes are not looked up, so this can run while parsing.
 */
class MagicMethodSignatureAnalyzer
{
    private const NO_RETURN_TYPE = 'none';

    /**
     * Maps the names of magic methods to
     * [the exact number of parameters (null for any), whether it must be static (false if it cannot be),
     *  whether it must be public, the types that parameters must allow if they declare a type,
     *  the return type if it declares one (self::NO_RETURN_TYPE if it cannot declare one, null for any)]
     */
    private const RULES = [
        '__construct'   => [null, false, false, [], self::NO_RETURN_TYPE],
        '__destruct'    => [0, false, false, [], self::NO_RETURN_TYPE],
        '__clone'       => [0, false, false, [], 'void'],
        '__get'         => [1, false, true, ['string'], null],
        '__set'         => [2, false, true, ['string'], 'void'],
        '__unset'       => [1, false, true, ['string'], 'void'],
        '__isset'       => [1, false, true, ['string'], 'bool'],
        '__call'        => [2, false, true, ['string', 'array'], null],
        '__callStatic'  => [2, true, true, ['string', 'array'], null],
        '__toString'    => [0, false, true, [], 'string'],
        '__debugInfo'   => [0, false, true, [], '?array'],
        '__serialize'   => [0, false, true, [], 'array'],
        '__unserialize' => [1, false, true, ['array'], 'void'],
        '__set_state'   => [1, true, true, ['array'], 'object'],
        '__invoke'      => [null, false, true, [], null],
        '__sleep'       => [0, false, true, [], 'array'],
        '__wakeup'      => [0, false, true, [], 'void'],
    ];

    /**
     * Emits issues if $method is a magic method with a signature that PHP rejects, warns about or deprecates.
     *
     * @param Method $method a method that was declared in the code being parsed (not from phpdoc or a stub)
     * @param Node $node the node of kind ast\AST_METHOD that declares it
     */
    public static function analyzeMagicMethodSignature(CodeBase $code_base, Method $method, Node $node): void
    {
        // Names are normalized in FullyQualifiedMethodName::make(), e.g. __CALLSTATIC to __callStatic
        $rule = self::RULES[$method->getName()] ?? null;
        if (!$rule) {
            return;
        }
        [$parameter_count, $must_be_static, $must_be_public, $parameter_types, $return_type] = $rule;

        $parameter_list = [];
        // Whether the parameter declares a type. The real type is empty for a type that could not be resolved: `parent` in a trait.
        $parameter_has_type = [];
        foreach ($method->getRealParameterList() as $i => $parameter) {
            // PHP does not count a variadic parameter
            if (!$parameter->isVariadic()) {
                $parameter_list[] = $parameter;
                $parameter_node = $node->children['params']->children[$i] ?? null;
                $parameter_has_type[] = $parameter_node instanceof Node && $parameter_node->children['type'] !== null;
            }
        }
        if ($parameter_count !== null) {
            if (\count($parameter_list) !== $parameter_count) {
                if ($parameter_count === 0) {
                    self::emitSignatureIssue($code_base, $method, 'it cannot take arguments');
                } elseif ($parameter_count === 1) {
                    self::emitSignatureIssue($code_base, $method, 'it must take exactly 1 argument');
                } else {
                    self::emitSignatureIssue($code_base, $method, "it must take exactly $parameter_count arguments");
                }
            } else {
                foreach ($parameter_list as $parameter) {
                    if ($parameter->isPassByReference()) {
                        self::emitSignatureIssue($code_base, $method, 'it cannot take arguments by reference');
                        break;
                    }
                }
                foreach ($parameter_types as $i => $expected_type) {
                    $parameter = $parameter_list[$i];
                    $real_type = $parameter->getNonVariadicUnionType()->getRealUnionType();
                    if ($parameter_has_type[$i] && !self::allowsType($real_type, $expected_type)) {
                        self::emitSignatureIssue(
                            $code_base,
                            $method,
                            'parameter #' . ($i + 1) . ' ($' . $parameter->getName() . ") must be of type $expected_type when declared"
                        );
                    }
                }
            }
        }
        if ($method->isStatic() !== $must_be_static) {
            self::emitSignatureIssue($code_base, $method, $must_be_static ? 'it must be static' : 'it cannot be static');
        }
        if ($must_be_public && !$method->isPublic()) {
            self::emitIssue($code_base, $method, Issue::MagicMethodNonPublic, $method->getRepresentationForIssue());
        }

        $real_return_type = $method->getRealReturnType();
        $has_return_type = $node->children['returnType'] !== null;
        if ($return_type === self::NO_RETURN_TYPE) {
            if ($has_return_type) {
                self::emitSignatureIssue($code_base, $method, 'it cannot declare a return type');
            }
        } elseif ($return_type !== null && $has_return_type) {
            // A declared type that could not be resolved is `parent` in a trait: the type of an object.
            $is_valid = $real_return_type->isEmpty() ? $return_type === 'object' : self::isReturnType($real_return_type, $return_type);
            if (!$is_valid) {
                self::emitSignatureIssue($code_base, $method, "the return type must be $return_type when declared");
            }
        }
        if (($return_type === self::NO_RETURN_TYPE || $return_type === 'void')
            && $method->hasNoDiscardAttribute()
            && Config::get_closest_target_php_version_id() >= 80500
        ) {
            self::emitSignatureIssue($code_base, $method, 'it cannot be #[\NoDiscard]');
        }
        if ($return_type === '?array' && $real_return_type->containsNullable() && self::isReturnType($real_return_type, $return_type)
            && Config::get_closest_target_php_version_id() >= 80600
        ) {
            self::emitIssue($code_base, $method, Issue::DeprecatedNullableDebugInfoReturnType, $method->getRepresentationForIssue());
        }
    }

    /**
     * @param UnionType $real_type the non-empty declared type of a parameter
     * @param string $expected_type 'string' or 'array'
     * @return bool whether the declared type allows $expected_type (it may allow other types as well)
     */
    private static function allowsType(UnionType $real_type, string $expected_type): bool
    {
        foreach ($real_type->getTypeSet() as $type) {
            if ($type instanceof MixedType) {
                return true;
            }
            if ($expected_type === 'string') {
                if ($type instanceof StringType) {
                    return true;
                }
            } elseif ($type instanceof ArrayType) {
                return true;
            } elseif ($type instanceof IterableType && Config::get_closest_minimum_target_php_version_id() >= 80200) {
                // iterable is Traversable|array since php 8.2. PHP 8.1 rejects it.
                return true;
            }
        }
        return false;
    }

    /**
     * @param UnionType $real_type the non-empty declared return type
     * @param string $expected_type 'void', 'bool', 'string', 'array', '?array' or 'object'
     * @return bool whether the declared type does not allow anything other than $expected_type
     */
    private static function isReturnType(UnionType $real_type, string $expected_type): bool
    {
        foreach ($real_type->getTypeSet() as $type) {
            if ($type instanceof NeverType) {
                // It is always legal to specify the never type
                return true;
            }
            if (!self::isPartOfReturnType($type, $expected_type)) {
                return false;
            }
        }
        return true;
    }

    private static function isPartOfReturnType(Type $type, string $expected_type): bool
    {
        // (ArrayType extends IterableType, and a VoidType is nullable)
        if ($expected_type === 'void') {
            return $type instanceof VoidType;
        }
        if ($expected_type === '?array') {
            return $type instanceof NullType || $type instanceof ArrayType;
        }
        if ($type->isNullable()) {
            return false;
        }
        switch ($expected_type) {
            case 'bool':
                return $type instanceof BoolType || $type instanceof TrueType || $type instanceof FalseType;
            case 'string':
                return $type instanceof StringType;
            case 'array':
                return $type instanceof ArrayType;
            default:
                // object: also any class, self, static and intersection types
                return $type instanceof ObjectType
                    || $type instanceof StaticOrSelfType
                    || $type instanceof IntersectionType
                    || $type->isObjectWithKnownFQSEN();
        }
    }

    private static function emitSignatureIssue(CodeBase $code_base, Method $method, string $details): void
    {
        self::emitIssue($code_base, $method, Issue::InvalidMagicMethodSignature, $method->getRepresentationForIssue(), $details);
    }

    private static function emitIssue(CodeBase $code_base, Method $method, string $issue_type, string ...$args): void
    {
        if (!Config::getValue('disable_suppression') && $method->checkHasSuppressIssueAndIncrementCount($issue_type)) {
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $method->getContext(),
            $issue_type,
            $method->getContext()->getLineNumberStart(),
            ...$args
        );
    }
}
