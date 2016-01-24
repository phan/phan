<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\Type\NullType;
use \Phan\Language\UnionType;

class FunctionFactory {

    /**
     * @return Func[]
     * One or more (alternate) methods begotten from
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
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     */
    public static function functionListFromReflectionFunction(
        CodeBase $code_base,
        \ReflectionFunction $reflection_function
    ) : array {

        $context = new Context();

        $parts = explode('\\', $reflection_function->getName());
        $method_name = array_pop($parts);
        $namespace = '\\' . implode('\\', $parts);

        $fqsen = FullyQualifiedFunctionName::make(
            $namespace, $method_name
        );

        $function = new Func(
            $context,
            $fqsen->getName(),
            new UnionType(),
            0
        );

        $function->setNumberOfRequiredParameters(
            $reflection_function->getNumberOfRequiredParameters()
        );

        $function->setNumberOfOptionalParameters(
            $reflection_function->getNumberOfParameters()
            - $reflection_function->getNumberOfRequiredParameters()
        );

        $function->setFQSEN($fqsen);

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
            $context
        );

        $func = new Func(
            $context,
            $fqsen->getName(),
            $return_type,
            0
        );

        $func->setFQSEN($fqsen);

        return self::functionListFromFunction($func, $code_base);
    }

    /**
     * @return Method[]
     */
    public static function methodListFromReflectionClassAndMethod(
        Context $context,
        CodeBase $code_base,
        \ReflectionClass $class,
        \ReflectionMethod $method
    ) : array {
        $reflection_method =
            new \ReflectionMethod($class->getName(), $method->name);

        $method = new Method(
            $context,
            $method->name,
            new UnionType(),
            $reflection_method->getModifiers()
        );

        $method->setNumberOfRequiredParameters(
            $reflection_method->getNumberOfRequiredParameters()
        );

        $method->setNumberOfOptionalParameters(
            $reflection_method->getNumberOfParameters()
            - $reflection_method->getNumberOfRequiredParameters()
        );

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
     * @return Method[]
     * A list of typed methods based on the given method
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
        return array_map(function($map) use (
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

            // Load properties if defined
            foreach ($map['property_name_type_map'] ?? []
                as $parameter_name => $parameter_type
            ) {
                $flags = 0;
                $is_optional = false;

                // Check to see if its a pass-by-reference parameter
                if (strpos($parameter_name, '&') === 0) {
                    $flags |= \ast\flags\PARAM_REF;
                    $parameter_name = substr($parameter_name, 1);
                }

                // Check to see if its variadic
                if (strpos($parameter_name, '...') !== false) {
                    $flags |= \ast\flags\PARAM_VARIADIC;
                    $parameter_name = str_replace('...', '', $parameter_name);
                }

                // Check to see if its an optional parameter
                if (strpos($parameter_name, '=') !== false) {
                    $is_optional = true;
                    $parameter_name = str_replace('=', '', $parameter_name);
                }

                $parameter = new Parameter(
                    $function->getContext(),
                    $parameter_name,
                    $parameter_type,
                    $flags
                );

                if ($is_optional) {
                    $parameter->setDefaultValueType(
                        NullType::instance()->asUnionType()
                    );
                }

                // Add the parameter
                $alternate_function->appendParameter($parameter);
            }

            $alternate_function->setNumberOfRequiredParameters(
                array_reduce($alternate_function->getParameterList(),
                    function(int $carry, Parameter $parameter) : int {
                        return ($carry + (
                            $parameter->isOptional() ? 0 : 1
                        ));
                    }, 0
                )
            );

            $alternate_function->setNumberOfOptionalParameters(
                count($alternate_function->getParameterList()) -
                $alternate_function->getNumberOfRequiredParameters()
            );

            return $alternate_function;
        }, $map_list);
    }
}
