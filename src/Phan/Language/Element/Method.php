<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\Element\Parameter;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Type\CallableType;
use \Phan\Language\Type\NullType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;
use \ast\Node\Decl;

class Method extends ClassElement implements Addressable {
    use AddressableImplementation;
    use \Phan\Analyze\Analyzable;

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
     * @var bool
     * No idea what this is
     */
    private $is_dynamic = false;

    /**
     * @var bool
     * This should be set to true if the method signature and
     * comment do not define a return type for the method.
     */
    private $is_return_type_undefined = false;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param int $number_of_required_parameters
     *
     * @param int $number_of_optional_parameters
     *
     * @param bool $is_dynamic
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        int $number_of_required_parameters = 0,
        int $number_of_optional_parameters = 0,
        bool $is_dynamic = false
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags
        );

        $this->number_of_required_parameters =
            $number_of_required_parameters;

        $this->number_of_optional_parameters =
            $number_of_optional_parameters;

        $this->is_dynamic = $is_dynamic;
    }

    /**
     * @return Method
     * A default constructor for the given class
     */
    public static function defaultConstructorForClassInContext(
        Clazz $clazz,
        Context $context
    ) : Method {
        return new Method(
            $context,
            '__construct',
            $clazz->getUnionType(),
            0
        );
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
                    $parameter->setDefaultValue(
                        null,
                        NullType::instance()->asUnionType()
                    );
                }

                // Add the parameter
                $alternate_method->parameter_list[] = $parameter;
            }

            $alternate_method->setNumberOfRequiredParameters(
                array_reduce($alternate_method->parameter_list,
                    function(int $carry, Parameter $parameter) : int {
                        return ($carry + (
                            $parameter->isOptional() ? 0 : 1
                        ));
                    }, 0
                )
            );

            $alternate_method->setNumberOfOptionalParameters(
                count($alternate_method->parameter_list) -
                $alternate_method->getNumberOfRequiredParameters()
            );

            return $alternate_method;
        }, $map_list);
    }

    /**
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Node $node
     * An AST node representing a method
     *
     * @return Method
     * A Method representing the AST node in the
     * given context
     *
     *
     * @see \Phan\Deprecated\Pass1::node_func
     * Formerly 'function node_func'
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Decl $node
    ) : Method {

        // Parse the comment above the method to get
        // extra meta information about the method.
        $comment =
            Comment::fromStringInContext(
                $node->docComment ?? '',
                $context
            );

        // @var Parameter[]
        // The list of parameters specified on the
        // method
        $parameter_list =
            Parameter::listFromNode(
                $context,
                $code_base,
                $node->children['params']
            );

        // Add each parameter to the scope of the function
        foreach ($parameter_list as $parameter) {
            $context = $context->withScopeVariable(
                $parameter
            );
        }

        // Create the skeleton method object from what
        // we know so far
        $method = new Method(
            $context,
            (string)$node->name,
            new UnionType(),
            $node->flags ?? 0
        );

        // If the method is Analyzable, set the node so that
        // we can come back to it whenever we like and
        // rescan it
        $method->setNode($node);

        // Set the parameter list on the method
        $method->setParameterList($parameter_list);

        $method->setNumberOfRequiredParameters(array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            }, 0)
        );

        $method->setNumberOfOptionalParameters(array_reduce(
            $parameter_list, function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            }, 0)
        );


        // Check to see if the comment specifies that the
        // method is deprecated
        $method->setIsDeprecated($comment->isDeprecated());

        // Take a look at method return types
        if($node->children['returnType'] !== null) {
            // Get the type of the parameter
            $union_type = UnionType::fromNode(
                $context,
                $code_base,
                $node->children['returnType']
            );

            $method->getUnionType()->addUnionType($union_type);
        }

        if ($comment->hasReturnUnionType()) {

            // See if we have a return type specified in the comment
            $union_type = $comment->getReturnType();

            if ($union_type->hasSelfType()) {
                // We can't actually figure out 'static' at this
                // point, but fill it in regardless. It will be partially
                // correct
                if ($context->hasClassFQSEN()) {
                    // n.b.: We're leaving the reference to self, static
                    //       or $this in the type because I'm guessing
                    //       it doesn't really matter. Apologies if it
                    //       ends up being an issue.
                    $union_type->addUnionType(
                        $context->getClassFQSEN()->asUnionType()
                    );
                }
            }

            $method->getUnionType()->addUnionType($union_type);
        }

        // Add params to local scope for user functions
        if($context->getFile() != 'internal') {

            $parameter_offset = 0;
            foreach ($method->getParameterList() as $i => $parameter) {
                if ($parameter->getUnionType()->isEmpty()) {
                    // If there is no type specified in PHP, check
                    // for a docComment with @param declarations. We
                    // assume order in the docComment matches the
                    // parameter order in the code
                    if ($comment->hasParameterWithNameOrOffset(
                        $parameter->getName(),
                        $parameter_offset
                    )) {
                        $comment_type =
                            $comment->getParameterWithNameOrOffset(
                                $parameter->getName(),
                                $parameter_offset
                            )->getUnionType();

                        $parameter->getUnionType()->addUnionType(
                            $comment_type
                        );
                    }
                }

                // If there's a default value on the parameter, check to
                // see if the type of the default is cool with the
                // specified type.
                if ($parameter->hasDefaultValue()) {
                    $default_type = $parameter->getDefaultValueType();

                    if ($default_type->isEqualTo(
                        NullType::instance()->asUnionType()
                    )) {
                        $parameter->getUnionType()->addUnionType(
                            $default_type
                        );
                    }

                    if (!$default_type->isEqualTo(NullType::instance()->asUnionType())
                        && !$default_type->canCastToUnionType(
                            $parameter->getUnionType()
                    )) {
                        Log::err(
                            Log::ETYPE,
                            "Default value for {$parameter->getUnionType()} \${$parameter->getName()} can't be {$default_type}",
                            $context->getFile(),
                            $node->lineno ?? 0
                        );
                    }

                    // If we have no other type info about a parameter,
                    // just because it has a default value of null
                    // doesn't mean that is its type. Any type can default
                    // to null
                    if ((string)$default_type === 'null'
                        && !$parameter->getUnionType()->isEmpty()
                    ) {
                        $parameter->getUnionType()->addType(
                            NullType::instance()
                        );
                    }
                }

                ++$parameter_offset;
            }

        }

        return $method;
    }

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
     * @return null
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
     * @return null
     */
    public function setNumberOfRequiredParameters(int $number) {
        $this->number_of_required_parameters = $number;
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
     * @return null
     */
    public function setParameterList(array $parameter_list) {
        $this->parameter_list = $parameter_list;
    }

    /**
     * @return bool
     * True if this is a dynamically created method
     */
    public function isDynamic() : bool {
        return $this->is_dynamic;
    }

    /**
     * @return bool
     * True if this is a static method
     */
    public function isStatic() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\MODIFIER_STATIC
        );
    }

    /**
     * @return bool
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function isReturnTypeUndefined() : bool {
        return $this->is_return_type_undefined;
    }

    /**
     * @param bool $is_return_type_undefined
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function setIsReturnTypeUndefined(
        bool $is_return_type_undefined
    ) {
        $this->is_return_type_undefined =
            $is_return_type_undefined;
    }

    /**
     * Mark this method as dynamic
     *
     * @param bool $is_dynamic
     * True to set this method to be dynamic, else false
     *
     * @return null
     */
    public function setIsDynamic(bool $is_dynamic) {
        $this->is_dynamic = $is_dynamic;
    }

    /**
     * @return bool
     * True if this is a magic method
     */
    public function getIsMagic() : bool {
        return in_array($this->getName(), [
            '__get',
            '__set',
            '__construct',
            '__destruct',
            '__call',
            '__callStatic',
            '__get',
            '__set',
            '__isset',
            '__unset',
            '__sleep',
            '__wakeup',
            '__toString',
            '__invoke',
            '__set_state',
            '__clone',
            '__debugInfo'
        ]);
    }

    /**
     * @return FullyQualifiedFunctionName|FullyQualifiedMethodName
     */
    public function getFQSEN() : FQSEN {
        // Allow overrides
        if ($this->fqsen) {
            return $this->fqsen;
        }

        return FullyQualifiedMethodName::fromStringInContext(
            $this->getName(),
            $this->getContext()
        );
    }

    /**
     * @return Method[]|\Generator
     * The set of all alternates to this method
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();

        while ($code_base->hasMethod($fqsen)) {
            yield $code_base->getMethod($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string representation of this method signature
     */
    public function __toString() : string {
        $string = '';

        $string .= 'function ' . $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        $string .= ';';

        return $string;
    }
}
