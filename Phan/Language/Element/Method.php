<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Parameter;
use \Phan\Language\FQSEN;
use \Phan\Language\Type\NullType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

class Method extends TypedStructuralElement {

    /**
     * @var int
     */
    private $number_of_required_parameters = 0;

    /**
     * @var int
     */
    private $number_of_optional_parameters = 0;

    /**
     * @var
     */
    private $parameter_list = [];

    /**
     * @var ?
     */
    private $ret = null;

    /**
     * @var bool
     */
    private $is_dynamic = false;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
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
     */
    public function __construct(
        Context $context,
        Comment $comment,
        string $name,
        UnionType $type,
        int $flags,
        int $number_of_required_parameters = 0,
        int $number_of_optional_parameters = 0
    ) {
        parent::__construct(
            $context,
            $comment,
            $name,
            $type,
            $flags
        );

        $this->number_of_required_parameters =
            $number_of_required_parameters;

        $this->number_of_optional_parameters =
            $number_of_optional_parameters;
    }

    /**
     *
     */
    public static function fromFunctionName(
        CodeBase $code_base,
        string $function_name
    ) : Method {

        $reflection_function =
            new \ReflectionFunction($function_name);

        return self::fromReflectionFunction(
            $code_base,
            $reflection_function
        );
    }

    /**
     *
     */
    public static function fromReflectionFunction(
        CodeBase $code_base,
        \ReflectionFunction $reflection_function
    ) : Method {

        $number_of_required_parameters =
            $reflection_function->getNumberOfRequiredParameters();

        $number_of_optional_parameters =
            $reflection_function->getNumberOfParameters()
            - $number_of_required_parameters;

        $context = new Context($code_base);

        $method = new Method(
            $context,
            Comment::none(),
            $reflection_function->getName(),
            new UnionType(),
            0,
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        // See if we have any type information for this
        // builtin function
        $map = UnionType::builtinFunctionPropertyNameTypeMap(
            $method->getFQSEN(), $context->getCodeBase()
        );

        // Set the return type if one is defined
        if (!empty($map['return_type'])) {
            $method->setUnionType($map['return_type']);
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
                $context,
                Comment::none(),
                $parameter_name,
                $parameter_type,
                $flags
            );

            if ($is_optional) {
                $parameter->setDefaultValue(null);
                $parameter->setDefaultValueUnionType(
                    NullType::instance()->asUnionType()
                );
            }

            // Add the parameter
            $method->parameter_list[] = $parameter;
        }

        return $method;
    }


    /**
     * @return map[string,Method];
     */
    public static function mapFromReflectionClassAndMethod(
        Context $context,
        \ReflectionClass $class,
        \ReflectionMethod $method,
        array $parents
    ) : array {
        $reflection_method =
            new \ReflectionMethod($class->getName(), $method->name);

        $number_of_required_parameters =
            $reflection_method->getNumberOfRequiredParameters();

        $number_of_optional_parameters =
            $reflection_method->getNumberOfParameters()
            - $number_of_required_parameters;

        $canonical_method_name =
            strtolower($method->name);

        $method = new Method(
            $context,
            Comment::none(),
            $method->name,
            new UnionType(),
            $reflection_method->getModifiers(),
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $fqsen = $method->getFQSEN();

        $name_method_info_map = [
            strtolower($method->getName()) => $method
        ];

        // Populate multiple-dispatch alternate method
        foreach ($fqsen->alternateFQSENInfiniteList() as $alt_fqsen) {
            if (!UnionType::builtinExists($alt_fqsen)) {
                break;
            }

            $alt_method = clone($method);
            $alt_method->withName(
                $method->name . ' ' . $alt_fqsen->getAlternateId()
            );

            $name_method_info_map = array_merge($name_method_info_map, [
                $alt_fqsen->__toString() => $alt_method,
            ]);
        }

        foreach($method->getParameterList() as $param) {
            $alt = 1;
            $flags = 0;
            if($param->isPassedByReference()) {
                $flags |= \ast\flags\PARAM_REF;
            }

            if($param->isVariadic()) {
                $flags |= \ast\flags\PARAM_VARIADIC;
            }

            $name_method_info_map[strtolower($method->name)]
                ->parameter_list[] = new Parameter(
                    $context,
                    Comment::none(),
                    $param->name,
                    new UnionType(),
                    // TODO: what was this below?
                    // new UnionType([(empty($arginfo) ? '' : (next($arginfo) ?: ''))]),
                    $flags
                );
        }

        return $name_method_info_map;
    }

    /**
     * @param Context $context
     * The context in which the node appears
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
        Node $node
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
            Parameter::listFromNode($context, $node->children['params']);

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
            $comment,
            $node->name,
            new UnionType(),
            $node->flags ?? 0
        );

        // Set the parameter list on the method
        $method->parameter_list = $parameter_list;

        $method->setNumberOfRequiredParameters(array_reduce(
            $parameter_list, function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            }, 0));

        $method->setNumberOfOptionalParameters(array_reduce(
            $parameter_list, function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            }, 0));


        // Check to see if the comment specifies that the
        // method is deprecated
        $method->setIsDeprecated($comment->isDeprecated());

        // Take a look at method return types
        if($node->children['returnType'] !== null) {
            $union_type = UnionType::fromSimpleNode(
                $context,
                $node->children['returnType']
            );

            $method->getUnionType()->addUnionType($union_type);
        } else if ($comment->hasReturnUnionType()) {

            // See if we have a return type specified in the comment
            $union_type = $comment->getReturnType();

            if ($union_type->hasSelfType()) {
                // We can't actually figure out 'static' at this
                // point, but fill it in regardless. It will be partially
                // correct
                if ($context->hasClassFQSEN()) {
                    $union_type =
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
            foreach ($method->parameter_list as $i => $parameter) {
                if ($parameter->getUnionType()->isEmpty()) {
                    // If there is no type specified in PHP, check
                    // for a docComment with @param declarations. We
                    // assume order in the docComment matches the
                    // parameter order in the code
                    if ($comment->hasParameterAtOffset($parameter_offset)) {
                        $comment_type =
                            $comment->getParameterAtOffset(
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
                if ($parameter->hasDefaultValueUnionType()) {
                    $default_type = $parameter->getDefaultValueType();

                    if (!$default_type->canCastToUnionType(
                        $parameter->getUnionType()
                    )) {
                        Log::err(
                            Log::ETYPE,
                            "Default value for {$parameter->getUnionType()} \${$parameter->getName()} can't be {$default_type}",
                            $context->getFile(),
                            $node->lineno
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
    public function getParameterList() : array {
        return $this->parameter_list;
    }

    public function isDynamic() : bool {
        return $this->is_dynamic;
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
     * @return FQSEN
     */
    public function getFQSEN() : FQSEN {
        // Allow overrides
        if ($this->fqsen) {
            return $this->fqsen;
        }

        // Otherwise, construct it
        return parent::getFQSEN()->withMethodName(
            $this->getContext(),
            $this->getName()
        );
    }

    /**
     * @return Method[]
     * The set of all alternates to this method
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();
        while ($code_base->hasMethodWithFQSEN($fqsen)) {
            yield $code_base->getMethodByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string representation of this method signature
     */
    public function __toString() : string {
        $string = '';

        $string .= (string)$this->getComment() . "\n";

        $string .= 'function ' . $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        $string .= ';';

        return $string;
    }
}
