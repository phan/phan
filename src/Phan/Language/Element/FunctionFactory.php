<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

class FunctionFactory {

    /**
     * @return Func[]
     * One or more (alternate) functions/methods begotten from
     * reflection info and internal method data
     */
    public static function functionListFromName(
        CodeBase $code_base,
        string $function_name
    ) : array {
        return self::functionListFromReflectionFunction(
            $code_base,
            new \ReflectionFunction($function_name)
        );
    }

    /**
     * @return Func[]
     * One or more (alternate) functions begotten from
     * reflection info and internal functions data
     */
    public static function functionListFromReflectionFunction(
        CodeBase $code_base,
        \ReflectionFunction $reflection_function
    ) : array {

        $context = new Context();

        $parts = explode('\\', $reflection_function->getName());
        $function_name = array_pop($parts);
        $namespace = '\\' . implode('\\', $parts);

        $fqsen = FullyQualifiedFunctionName::make(
            $namespace, $function_name
        );

        $function = new Func(
            $context,
            $fqsen->getName(),
            new UnionType(),
            0,
            $fqsen
        );

        $function->setNumberOfRequiredParameters(
            $reflection_function->getNumberOfRequiredParameters()
        );

        $function->setNumberOfOptionalParameters(
            $reflection_function->getNumberOfParameters()
            - $reflection_function->getNumberOfRequiredParameters()
        );
        $function->setIsDeprecated($reflection_function->isDeprecated());

        return self::functionListFromFunction($function, $code_base);
    }

    /**
     * @return Func[]
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     */
    public static function functionListFromSignature(
        CodeBase $code_base,
        FullyQualifiedFunctionName $fqsen,
        array $signature
    ) : array {

        $context = new Context();

        $return_type = UnionType::fromStringInContext(
            array_shift($signature),
            $context,
            Type::FROM_TYPE
        );

        $func = new Func(
            $context,
            $fqsen->getName(),
            $return_type,
            0,
            $fqsen
        );

        return self::functionListFromFunction($func, $code_base);
    }

    /**
     * @return Method[]
     */
    public static function methodListFromReflectionClassAndMethod(
        Context $context,
        CodeBase $code_base,
        \ReflectionClass $class,
        \ReflectionMethod $reflection_method
    ) : array {
        $method_fqsen = FullyQualifiedMethodName::fromStringInContext(
            $reflection_method->getName(),
            $context
        );

        $class_name = $class->getName();
        $reflection_method = new \ReflectionMethod(
            $class_name,
            $reflection_method->name
        );

        $method = new Method(
            $context,
            $reflection_method->name,
            new UnionType(),
            $reflection_method->getModifiers(),
            $method_fqsen
        );

        $method->setNumberOfRequiredParameters(
            $reflection_method->getNumberOfRequiredParameters()
        );

        $method->setNumberOfOptionalParameters(
            $reflection_method->getNumberOfParameters()
            - $reflection_method->getNumberOfRequiredParameters()
        );

        if ($method->getIsMagicCall() || $method->getIsMagicCallStatic()) {
            $method->setNumberOfOptionalParameters(999);
            $method->setNumberOfRequiredParameters(0);
        }
        $method->setIsDeprecated($reflection_method->isDeprecated());
        // https://github.com/etsy/phan/issues/888 - Reflection for that class's parameters causes php to throw/hang
        if ($class_name !== 'ServerResponse') {
            $method->setRealReturnType(UnionType::fromReflectionType($reflection_method->getReturnType()));
            $method->setRealParameterList(Parameter::listFromReflectionParameterList($reflection_method->getParameters()));
        }

        return self::functionListFromFunction($method, $code_base);
    }

    /**
     * @param FunctionInterface $function
     * Get a list of methods hydrated with type information
     * for the given partial method
     *
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return FunctionInterface[]
     * A list of typed functions/methods based on the given method
     */
    private static function functionListFromFunction(
        FunctionInterface $function,
        CodeBase $code_base
    ) : array {
        // See if we have any type information for this
        // internal function
        $map_list = UnionType::internalFunctionSignatureMapForFQSEN(
            $function->getFQSEN()
        );

        if (!$map_list) {
            return [$function];
        }

        $alternate_id = 0;
        return \array_map(function($map) use (
            $function,
            &$alternate_id
        ) : FunctionInterface {
            $alternate_function = clone($function);

            $alternate_function->setFQSEN(
                $alternate_function->getFQSEN()->withAlternateId(
                    $alternate_id++
                )
            );

            // Set the return type if one is defined
            if (!empty($map['return_type'])) {
                $alternate_function->setUnionType($map['return_type']);
            }

            // Load parameter types if defined
            foreach ($map['parameter_name_type_map'] ?? []
                as $parameter_name => $parameter_type
            ) {
                $flags = 0;
                $phan_flags = 0;
                $is_optional = false;

                // Check to see if its a pass-by-reference parameter
                if (($parameter_name[0] ?? '') === '&') {
                    $flags |= \ast\flags\PARAM_REF;
                    $parameter_name = \substr($parameter_name, 1);
                    if (\strncmp($parameter_name, 'rw_', 3) === 0) {
                        $phan_flags |= Flags::IS_READ_REFERENCE | Flags::IS_WRITE_REFERENCE;
                        $parameter_name = \substr($parameter_name, 3);
                    } else if (\strncmp($parameter_name, 'w_', 2) === 0) {
                        $phan_flags |= Flags::IS_WRITE_REFERENCE;
                        $parameter_name = \substr($parameter_name, 2);
                    }
                }

                // Check to see if its variadic
                if (\strpos($parameter_name, '...') !== false) {
                    $flags |= \ast\flags\PARAM_VARIADIC;
                    $parameter_name = \str_replace('...', '', $parameter_name);
                }

                // Check to see if its an optional parameter
                if (\strpos($parameter_name, '=') !== false) {
                    $is_optional = true;
                    $parameter_name = \str_replace('=', '', $parameter_name);
                }

                $parameter = new Parameter(
                    $function->getContext(),
                    $parameter_name,
                    $parameter_type,
                    $flags
                );
                $parameter->setPhanFlags(Flags::bitVectorWithState(
                    $parameter->getPhanFlags(),
                    $phan_flags,
                    true
                ));

                if ($is_optional) {
                    // TODO: could check isDefaultValueAvailable and getDefaultValue, for a better idea.
                    // I don't see any cases where this will be used for internal types, though.
                    $parameter->setDefaultValueType(
                        NullType::instance(false)->asUnionType()
                    );
                }

                // Add the parameter
                $alternate_function->appendParameter($parameter);
            }

            // TODO: Store the "real" number of required parameters,
            // if this is out of sync with the extension's ReflectionMethod->getParameterList()?
            // (e.g. third party extensions may add more required parameters?)
            $alternate_function->setNumberOfRequiredParameters(
                \array_reduce($alternate_function->getParameterList(),
                    function(int $carry, Parameter $parameter) : int {
                        return ($carry + (
                            $parameter->isOptional() ? 0 : 1
                        ));
                    }, 0
                )
            );

            $alternate_function->setNumberOfOptionalParameters(
                \count($alternate_function->getParameterList()) -
                $alternate_function->getNumberOfRequiredParameters()
            );

            if ($alternate_function instanceof Method) {
                if ($alternate_function->getIsMagicCall() || $alternate_function->getIsMagicCallStatic()) {
                    $alternate_function->setNumberOfOptionalParameters(999);
                    $alternate_function->setNumberOfRequiredParameters(0);
                }
            }

            return $alternate_function;
        }, $map_list);
    }
}
