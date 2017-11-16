<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

class Parameter
{

    /**
     * @var string
     * The name of the parameter
     */
    private $name;

    /**
     * @var UnionType
     * The type of the parameter
     */
    private $type;

    /**
     * @var bool
     * Whether or not the parameter is variadic (in the comment)
     */
    private $is_variadic;

    /**
     * @var bool
     * Whether or not the parameter is optional (Note: only applies to the comment for (at)method.)
     */
    private $has_default_value;

    /**
     * @var bool
     * True if a given parameter is an output-only parameter and ignores the passed in type.
     */
    private $is_output_reference;

    /**
     * @param string $name
     * The name of the parameter
     *
     * @param UnionType $type
     * The type of the parameter
     */
    public function __construct(
        string $name,
        UnionType $type,
        bool $is_variadic = false,
        bool $has_default_value = false,
        bool $is_output_reference = false
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->is_variadic = $is_variadic;
        $this->has_default_value = $has_default_value;
        $this->is_output_reference = $is_output_reference;
    }

    /**
     *
     */
    public function asVariable(
        Context $context,
        int $flags = 0
    ) : Variable {
        return new Variable(
            $context,
            $this->getName(),
            $this->getUnionType(),
            $flags
        );
    }

    /**
     *
     */
    public function asRealParameter(
        Context $context
    ) : \Phan\Language\Element\Parameter {
        $flags = 0;
        if ($this->isVariadic()) {
            $flags |= \ast\flags\PARAM_VARIADIC;
        }
        $union_type = $this->getUnionType();
        $param = new \Phan\Language\Element\Parameter(
            $context,
            $this->getName(),
            $union_type,
            $flags
        );
        if ($this->has_default_value) {
            $param->setDefaultValueType(clone($union_type));
            // TODO: could setDefaultValue in a future PR. Would have to run \ast\parse_code on the default value, catch ParseError if necessary.
            // If given '= "Default"', then extract the default from '<?php ("Default");'
            // Then get the type from UnionTypeVisitor, for defaults such as SomeClass::CONST.
        }
        return $param;
    }

    /**
     * @return string
     * The name of the parameter
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * The type of the parameter
     */
    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @return bool
     * Whether or not the parameter is variadic
     */
    public function isVariadic() : bool
    {
        return $this->is_variadic;
    }

    /**
     * @return bool
     * Whether or not the parameter is an output reference
     */
    public function isOutputReference() : bool
    {
        return $this->is_output_reference;
    }

    /**
     * @return bool
     * Whether or not the parameter is required
     */
    public function isRequired() : bool
    {
        return !$this->isOptional();
    }

    /**
     * @return bool
     * Whether or not the parameter is optional
     */
    public function isOptional() : bool
    {
        return $this->has_default_value || $this->is_variadic;
    }

    public function __toString() : string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }
        if ($this->is_variadic) {
            $string .= '...';
        }

        $string .= '$' . $this->name;

        if ($this->has_default_value) {
            $string .= ' = default';
        }

        return $string;
    }
}
