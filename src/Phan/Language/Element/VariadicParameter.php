<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\UnionType;
use Phan\Language\Type\GenericArrayType;

class VariadicParameter extends Parameter {
    // __construct inherited from Parameter

    /**
     * @return static - non-variadic clone which can be modified.
     * @override
     */
    public function cloneAsNonVariadic()
    {
        $result = clone($this);
        if (!$result->isCloneOfVariadic()) {
            $result->convertToNonVariadic();
            $result->setPhanFlags(Flags::bitVectorWithState(
                $result->getPhanFlags(),
                Flags::IS_CLONE_OF_VARIADIC,
                true
            ));
        }
        return $result;
    }

    /**
     * @return bool - True when this is a non-variadic clone of a variadic parameter.
     * (We avoid bugs by adding new types to a variadic parameter if this is cloned.)
     * However, error messages still need to convert variadic parameters to a string.
     * @override
     */
    public function isCloneOfVariadic() : bool
    {
        return Flags::bitVectorHasState($this->getPhanFlags(), Flags::IS_CLONE_OF_VARIADIC);
    }

    /**
     * @return bool
     * True if this parameter is variadic, i.e. can
     * take an unlimited list of parameters and express
     * them as an array.
     * @override
     */
    public function isVariadic() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if this is an optional parameter (true because this is variadic)
     * @override
     */
    public function isOptional() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if this is a required parameter (false because this is variadic)
     * @override
     */
    public function isRequired() : bool
    {
        return false;
    }

    /**
     * Returns the Parameter in the form expected by a caller.
     *
     * If this parameter is variadic (e.g. `DateTime ...$args`), then this
     * would return a parameter with the type of the elements (e.g. `DateTime`)
     *
     * If this parameter is not variadic, returns $this.
     *
     * @return static
     * @override
     */
    public function asNonVariadic()
    {
        // TODO: Is it possible to cache this while maintaining
        //       correctness? PostOrderAnalysisVisitor clones the
        //       value to avoid it being reused.
        //
        // Also, figure out if the cloning still working correctly
        // after this PR for fixing variadic args. Create a single
        // Parameter instance for analyzing callers of the
        // corresponding method/function.
        // e.g. $this->getUnionType() is of type T[]
        //      $this->non_variadic->getUnionType() is of type T
        return Parameter::create(
            $this->getFileRef(),
            $this->getName(),
            $this->getNonVariadicUnionType(),
            Flags::bitVectorWithState($this->getFlags(), \ast\flags\PARAM_VARIADIC, false)
        );
    }

    /**
     * If this parameter is variadic (e.g. `DateTime ...$args`),
     * then this returns the corresponding array type(s) of $args.
     * (e.g. `DateTime[]`)
     *
     * NOTE: For analyzing the code within a function,
     * code should pass $param->cloneAsNonVariadic() instead.
     * Modifying/analyzing the clone should work without any bugs.
     *
     * TODO(Issue #376) : We will probably want to be able to modify
     * the underlying variable, e.g. by creating
     * `class UnionTypeGenericArrayView extends UnionType`.
     * Otherwise, type inference of `...$args` based on the function
     * source will be less effective without phpdoc types.
     *
     * @override
     */
    public function getUnionType() : UnionType
    {
        if (!$this->isCloneOfVariadic()) {
            // TODO: Figure out why asNonEmptyGenericArrayTypes() causes test failures
            return parent::getUnionType()->asGenericArrayTypes(GenericArrayType::KEY_INT);
        }
        return parent::getUnionType();
    }
}
