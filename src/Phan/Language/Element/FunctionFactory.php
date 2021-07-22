<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use ast;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ReflectionFunctionAbstract;

/**
 * This returns internal function declarations for a given function/method FQSEN,
 * using Reflection and/or Phan's internal function signature map.
 */
class FunctionFactory
{
    /**
     * @return list<Func>
     * One or more (alternate) functions begotten from
     * reflection info and internal functions data
     * @suppress PhanTypeMismatchReturn FunctionInterface->Method
     */
    public static function functionListFromReflectionFunction(
        FullyQualifiedFunctionName $fqsen,
        \ReflectionFunction $reflection_function
    ): array {

        $context = new Context();

        $namespaced_name = $fqsen->getNamespacedName();

        $function = new Func(
            $context,
            $namespaced_name,
            UnionType::empty(),
            0,
            $fqsen,
            null
        );

        $function->setNumberOfRequiredParameters(
            $reflection_function->getNumberOfRequiredParameters()
        );

        $function->setNumberOfOptionalParameters(
            $reflection_function->getNumberOfParameters()
            - $reflection_function->getNumberOfRequiredParameters()
        );
        $function->setIsDeprecated($reflection_function->isDeprecated());

        $real_return_type = self::getRealReturnTypeFromReflection($reflection_function);
        // @phan-suppress-next-line PhanUndeclaredMethod
        if (\PHP_VERSION_ID >= 80100 && $reflection_function->hasTentativeReturnType()) {
            $function->setHasTentativeReturnType();
        }
        if (Config::getValue('assume_real_types_for_internal_functions')) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $real_type_string = UnionType::getLatestRealFunctionSignatureMap(Config::get_closest_target_php_version_id())[$namespaced_name] ?? null;
            if (\is_string($real_type_string)) {
                // Override it with Phan's information, useful for list<string> overriding array
                // TODO: Validate that Phan's signatures are compatible (e.g. nullability)
                $real_return_type = UnionType::fromStringInContext($real_type_string, new Context(), Type::FROM_TYPE);
            }
        }
        $function->setRealReturnType($real_return_type);
        $function->setRealParameterList(Parameter::listFromReflectionParameterList($reflection_function->getParameters()));

        return self::functionListFromFunction($function);
    }

    /**
     * @param string[] $signature
     * @return list<Func>
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     * @suppress PhanTypeMismatchReturn FunctionInterface->Method
     */
    public static function functionListFromSignature(
        FullyQualifiedFunctionName $fqsen,
        array $signature
    ): array {

        // TODO: Look into adding helper method in UnionType caching this to speed up loading.
        $context = new Context();

        $return_type = UnionType::fromStringInContext(
            $signature[0],
            $context,
            Type::FROM_TYPE
        );
        unset($signature[0]);

        $func = new Func(
            $context,
            $fqsen->getNamespacedName(),
            $return_type,
            0,
            $fqsen,
            []  // will be filled in by functionListFromFunction
        );

        return self::functionListFromFunction($func);
    }

    /**
     * @return list<Method> a list of 1 or more method signatures from a ReflectionMethod
     * and Phan's alternate signatures for that method's FQSEN in FunctionSignatureMap.
     * @suppress PhanTypeMismatchReturn FunctionInterface->Method
     */
    public static function methodListFromReflectionClassAndMethod(
        Context $context,
        \ReflectionClass $class,
        \ReflectionMethod $reflection_method
    ): array {
        $class_name = $class->getName();
        $method_fqsen = FullyQualifiedMethodName::make(
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            FullyQualifiedClassName::fromFullyQualifiedString($class_name),
            $reflection_method->getName()
        );

        // Deliberately don't use getModifiers - flags we don't know about might cause unexpected effects,
        // and there's no guarantee MODIFIER_PUBLIC would continue to equal ReflectionMethod::IS_PUBLIC
        if ($reflection_method->isPublic()) {
            $modifiers = ast\flags\MODIFIER_PUBLIC;
        } elseif ($reflection_method->isProtected()) {
            $modifiers = ast\flags\MODIFIER_PROTECTED;
        } else {
            $modifiers = ast\flags\MODIFIER_PRIVATE;
        }
        if ($reflection_method->isStatic()) {
            $modifiers |= ast\flags\MODIFIER_STATIC;
        }
        if ($reflection_method->isFinal()) {
            $modifiers |= ast\flags\MODIFIER_FINAL;
        }
        if ($reflection_method->isAbstract()) {
            $modifiers |= ast\flags\MODIFIER_ABSTRACT;
        }

        $method = new Method(
            $context,
            $reflection_method->name,
            UnionType::empty(),
            $modifiers,
            $method_fqsen,
            null
        );
        // Knowing the defining class of the method is useful for warning about unused calls to inherited methods such as Exception->getCode()
        $defining_class_name = $reflection_method->class;
        if ($defining_class_name !== $class_name) {
            $method->setDefiningFQSEN(
                FullyQualifiedMethodName::make(
                    // @phan-suppress-next-line PhanThrowTypeAbsentForCall
                    FullyQualifiedClassName::fromFullyQualifiedString($defining_class_name),
                    $reflection_method->getName()
                )
            );
        }

        $method->setNumberOfRequiredParameters(
            $reflection_method->getNumberOfRequiredParameters()
        );

        $method->setNumberOfOptionalParameters(
            $reflection_method->getNumberOfParameters()
            - $reflection_method->getNumberOfRequiredParameters()
        );

        if ($method->isMagicCall() || $method->isMagicCallStatic()) {
            $method->setNumberOfOptionalParameters(FunctionInterface::INFINITE_PARAMETERS);
            $method->setNumberOfRequiredParameters(0);
        }
        $method->setIsDeprecated($reflection_method->isDeprecated());
        // https://github.com/phan/phan/issues/888 - Reflection for that class's parameters causes php to throw/hang
        if ($class_name !== 'ServerResponse') {
            $method->setRealReturnType(self::getRealReturnTypeFromReflection($reflection_method));
            // @phan-suppress-next-line PhanUndeclaredMethod
            if (\PHP_VERSION_ID >= 80100 && $reflection_method->hasTentativeReturnType()) {
                $method->setHasTentativeReturnType();
            }
            $method->setRealParameterList(Parameter::listFromReflectionParameterList($reflection_method->getParameters()));
        }

        return self::functionListFromFunction($method);
    }

    /**
     * Get the return type from reflection (or the tentative return type)
     * @suppress PhanUndeclaredMethod
     */
    public static function getRealReturnTypeFromReflection(ReflectionFunctionAbstract $function): UnionType
    {
        if (\PHP_VERSION_ID >= 80100 && $function->hasTentativeReturnType() && Config::getValue('use_tentative_return_type')) {
            return UnionType::fromReflectionType($function->getTentativeReturnType());
        }
        return UnionType::fromReflectionType($function->getReturnType());
    }

    /**
     * @param FunctionInterface $function
     * Get a list of methods hydrated with type information
     * for the given partial method
     *
     * @return list<FunctionInterface>
     * A list of typed functions/methods based on the given method
     */
    public static function functionListFromFunction(
        FunctionInterface $function
    ): array {
        // See if we have any type information for this
        // internal function
        $map_list = UnionType::internalFunctionSignatureMapForFQSEN(
            $function->getFQSEN()
        );

        if (!$map_list) {
            if (!$function->getParameterList()) {
                $function->setParameterList($function->getRealParameterList());
            }
            $function->inheritRealParameterDefaults();
            return [$function];
        }

        $alternate_id = 0;
        /**
         * @param array<string,mixed> $map
         * @suppress PhanPossiblyFalseTypeArgumentInternal, PhanPossiblyFalseTypeArgument
         */
        return \array_map(static function (array $map) use (
            $function,
            &$alternate_id
        ): FunctionInterface {
            $alternate_function = clone($function);

            $alternate_function->setFQSEN(
                $alternate_function->getFQSEN()->withAlternateId(
                    $alternate_id++
                )
            );

            // Set the return type if one is defined
            $return_type = $map['return_type'] ?? null;
            if ($return_type instanceof UnionType) {
                $real_return_type = $function->getRealReturnType();
                if (!$real_return_type->isEmpty()) {
                    $return_type = UnionType::of($return_type->getTypeSet(), $real_return_type->getTypeSet());
                }
                $alternate_function->setUnionType($return_type);
            }
            $alternate_function->clearParameterList();

            // Load parameter types if defined
            foreach ($map['parameter_name_type_map'] ?? [] as $parameter_name => $parameter_type) {
                $flags = 0;
                $phan_flags = 0;
                $is_optional = false;

                // Check to see if it's a pass-by-reference parameter
                if (($parameter_name[0] ?? '') === '&') {
                    $flags |= \ast\flags\PARAM_REF;
                    $parameter_name = \substr($parameter_name, 1);
                    if (\strncmp($parameter_name, 'rw_', 3) === 0) {
                        $phan_flags |= Flags::IS_READ_REFERENCE | Flags::IS_WRITE_REFERENCE;
                        $parameter_name = \substr($parameter_name, 3);
                    } elseif (\strncmp($parameter_name, 'w_', 2) === 0) {
                        $phan_flags |= Flags::IS_WRITE_REFERENCE;
                        $parameter_name = \substr($parameter_name, 2);
                    } elseif (\strncmp($parameter_name, 'r_', 2) === 0) {
                        $phan_flags |= Flags::IS_READ_REFERENCE;
                        $parameter_name = \substr($parameter_name, 2);
                    }
                }

                // Check to see if it's variadic
                if (\strpos($parameter_name, '...') !== false) {
                    $flags |= \ast\flags\PARAM_VARIADIC;
                    $parameter_name = \str_replace('...', '', $parameter_name);
                }

                // Check to see if it's an optional parameter
                if (\strpos($parameter_name, '=') !== false) {
                    $is_optional = true;
                    $parameter_name = \str_replace('=', '', $parameter_name);
                }

                $parameter = Parameter::create(
                    $function->getContext(),
                    $parameter_name,
                    $parameter_type,
                    $flags
                );
                $parameter->enablePhanFlagBits($phan_flags);
                if ($is_optional) {
                    if (!$parameter->hasDefaultValue()) {
                        // Placeholder value. PHP 8.0+ is better at actually providing real parameter defaults.
                        $parameter->setDefaultValueType(NullType::instance(false)->asPHPDocUnionType());
                    }
                }

                // Add the parameter
                $alternate_function->appendParameter($parameter);
            }

            // TODO: Store the "real" number of required parameters,
            // if this is out of sync with the extension's ReflectionMethod->getParameterList()?
            // (e.g. third party extensions may add more required parameters?)
            $alternate_function->setNumberOfRequiredParameters(
                \array_reduce(
                    $alternate_function->getParameterList(),
                    static function (int $carry, Parameter $parameter): int {
                        return ($carry + (
                            $parameter->isOptional() ? 0 : 1
                        ));
                    },
                    0
                )
            );

            $alternate_function->setNumberOfOptionalParameters(
                \count($alternate_function->getParameterList()) -
                $alternate_function->getNumberOfRequiredParameters()
            );

            if ($alternate_function instanceof Method) {
                if ($alternate_function->isMagicCall() || $alternate_function->isMagicCallStatic()) {
                    $alternate_function->setNumberOfOptionalParameters(999);
                    $alternate_function->setNumberOfRequiredParameters(0);
                }
            }
            $alternate_function->inheritRealParameterDefaults();

            return $alternate_function;
        }, $map_list);
    }
}
