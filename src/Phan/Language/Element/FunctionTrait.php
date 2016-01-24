<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\Type\NullType;
use \Phan\Language\UnionType;

trait FunctionTrait {

    /**
     * @return int
     */
    abstract public function getFlags() : int;

    /**
     * @param int $flags
     *
     * @return void
     */
    abstract public function setFlags(int $flags);


    /**
     * @var int
     * The number of required parameters for the method
     */
    private $number_of_required_parameters = 0;

    /**
     * @var int
     * The number of optional parameters for the method
     */
    private $number_of_optional_parameters = 0;

    /**
     * @var Parameter[]
     * The list of parameters for this method
     */
    private $parameter_list = [];

    /**
     * @return int
     * The number of optional parameters on this method
     */
    public function getNumberOfOptionalParameters() : int {
        return $this->number_of_optional_parameters;
    }

    /**
     * The number of optional parameters
     *
     * @return void
     */
    public function setNumberOfOptionalParameters(int $number) {
        $this->number_of_optional_parameters = $number;
    }

    /**
     * @return int
     * The maximum number of parameters to this method
     */
    public function getNumberOfParameters() : int {
        return (
            $this->getNumberOfRequiredParameters()
            + $this->getNumberOfOptionalParameters()
        );
    }

    /**
     * @return int
     * The number of required parameters on this method
     */
    public function getNumberOfRequiredParameters() : int {
        return $this->number_of_required_parameters;
    }

    /**
     *
     * The number of required parameters
     *
     * @return void
     */
    public function setNumberOfRequiredParameters(int $number) {
        $this->number_of_required_parameters = $number;
    }

    /**
     * @return bool
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function isReturnTypeUndefined() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            Flags::IS_RETURN_TYPE_UNDEFINED
        );
    }

    /**
     * @param bool $is_return_type_undefined
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     *
     * @return void
     */
    public function setIsReturnTypeUndefined(
        bool $is_return_type_undefined
    ) {
        $this->setFlags(Flags::bitVectorWithState(
            $this->getFlags(),
            Flags::IS_RETURN_TYPE_UNDEFINED,
            $is_return_type_undefined
        ));
    }

    /**
     * @return bool
     * True if this method returns a value
     */
    public function getHasReturn() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            Flags::HAS_RETURN
        );
    }

    /**
     * @param bool $has_return
     * Set to true to mark this method as having a
     * return value
     *
     * @return void
     */
    public function setHasReturn(bool $has_return)
    {
        $this->setFlags(Flags::bitVectorWithState(
            $this->getFlags(),
            Flags::HAS_RETURN,
            $has_return
        ));
    }

    /**
     * @return Parameter[]
     * A list of parameters on the method
     */
    public function getParameterList() {
        return $this->parameter_list;
    }

    /**
     * @param Parameter[] $parameter_list
     * A list of parameters to set on this method
     *
     * @return void
     */
    public function setParameterList(array $parameter_list) {
        $this->parameter_list = $parameter_list;
    }

    /**
     * @param Parameter $parameter
     * A parameter to append to the parameter list
     */
    public function appendParameter(Parameter $parameter) {
        $this->parameter_list[] = $parameter;
    }

    /**
     * @return Method[]
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     */
    public static function methodListFromFunctionName(
        CodeBase $code_base,
        string $function_name
    ) : array {
        $reflection_function =
            new \ReflectionFunction($function_name);

        $method_list = self::methodListFromReflectionFunction(
            $code_base,
            $reflection_function
        );

        return $method_list;
    }

    /**
     * @return Method[]
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     */
    public static function methodListFromReflectionFunction(
        CodeBase $code_base,
        \ReflectionFunction $reflection_function
    ) : array {

        $number_of_required_parameters =
            $reflection_function->getNumberOfRequiredParameters();

        $number_of_optional_parameters =
            $reflection_function->getNumberOfParameters()
            - $number_of_required_parameters;

        $context = new Context();

        $parts = explode('\\', $reflection_function->getName());
        $method_name = array_pop($parts);
        $namespace = '\\' . implode('\\', $parts);

        $fqsen = FullyQualifiedFunctionName::make(
            $namespace, $method_name
        );

        $method = new Method(
            $context,
            $fqsen->getName(),
            new UnionType(),
            0,
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $method->setFQSEN($fqsen);

        return self::methodListFromMethod($method, $code_base);
    }

    /**
     * @return Method[]
     * One or more (alternate) methods begotten from
     * reflection info and internal method data
     */
    public static function methodListFromSignature(
        CodeBase $code_base,
        FullyQualifiedFunctionName $fqsen,
        array $signature
    ) : array {

        $context = new Context();

        $return_type =
            UnionType::fromStringInContext(
                array_shift($signature),
                $context
            );

        $method = new Method(
            $context,
            $fqsen->getName(),
            $return_type,
            0
        );

        $method->setFQSEN($fqsen);

        return self::methodListFromMethod($method, $code_base);
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

        $number_of_required_parameters =
            $reflection_method->getNumberOfRequiredParameters();

        $number_of_optional_parameters =
            $reflection_method->getNumberOfParameters()
            - $number_of_required_parameters;

        $method = new Method(
            $context,
            $method->name,
            new UnionType(),
            $reflection_method->getModifiers(),
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        return self::methodListFromMethod($method, $code_base);
    }

    /**
     * @param Method $method
     * Get a list of methods hydrated with type information
     * for the given partial method
     *
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Method[]
     * A list of typed methods based on the given method
     */
    private static function methodListFromMethod(
        Method $method,
        CodeBase $code_base
    ) : array {
        // See if we have any type information for this
        // internal function
        $map_list = UnionType::internalFunctionSignatureMapForFQSEN(
            $method->getFQSEN()
        );

        if (!$map_list) {
            return [$method];
        }

        $alternate_id = 0;
        return array_map(function($map) use (
            $method,
            &$alternate_id
        ) : Method {
            $alternate_method = clone($method);

            $alternate_method->setFQSEN(
                $alternate_method->getFQSEN()->withAlternateId(
                    $alternate_id++
                )
            );

            // Set the return type if one is defined
            if (!empty($map['return_type'])) {
                $alternate_method->setUnionType($map['return_type']);
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
                    $method->getContext(),
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
                $alternate_method->appendParameter($parameter);
            }

            $alternate_method->setNumberOfRequiredParameters(
                array_reduce($alternate_method->getParameterList(),
                    function(int $carry, Parameter $parameter) : int {
                        return ($carry + (
                            $parameter->isOptional() ? 0 : 1
                        ));
                    }, 0
                )
            );

            $alternate_method->setNumberOfOptionalParameters(
                count($alternate_method->getParameterList()) -
                $alternate_method->getNumberOfRequiredParameters()
            );

            return $alternate_method;
        }, $map_list);
    }


}
