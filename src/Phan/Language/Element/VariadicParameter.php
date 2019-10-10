<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\UnionType;

/**
 * Contains Phan's representation of a variadic parameter of a method declaration, and methods to access/modify/use the variadic parameters.
 */
class VariadicParameter extends Parameter
{
    // __construct inherited from Parameter

    /** @var ?bool */
    private $has_empty_non_variadic_type;

    /**
     * @return static - non-variadic clone which can be modified.
     * @override
     */
    public function cloneAsNonVariadic()
    {
        $result = clone($this);
        if (!$result->isCloneOfVariadic()) {
            $result->convertToNonVariadic();
            $result->enablePhanFlagBits(Flags::IS_CLONE_OF_VARIADIC);
            $result->has_empty_non_variadic_type = $this->hasEmptyNonVariadicType();
        }
        return $result;
    }

    private function convertToNonVariadic() : void
    {
        // Avoid a redundant clone of toGenericArray()
        $this->type = $this->getUnionType();
    }

    /**
     * @return bool - True when this is a non-variadic clone of a variadic parameter.
     * (We avoid bugs by adding new types to a variadic parameter if this is cloned.)
     * However, error messages still need to convert variadic parameters to a string.
     * @override
     */
    public function isCloneOfVariadic() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_CLONE_OF_VARIADIC);
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
        return new Parameter(
            // @phan-suppress-next-line PhanTypeMismatchArgument Here it's fine to pass a FileRef
            $this->getFileRef(),
            $this->getName(),
            $this->type,
            Flags::bitVectorWithState($this->getFlags(), \ast\flags\PARAM_VARIADIC, false)
        );
    }

    /**
     * If this Parameter is variadic, calling `getUnionType`
     * will return an array type such as `DateTime[]`. This
     * method will return the element type (such as `DateTime`)
     * for variadic parameters.
     */
    public function getNonVariadicUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * If this parameter is variadic (e.g. `DateTime ...$args`),
     * then this returns the corresponding array type(s) of $args.
     * (e.g. `list<DateTime>`)
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
            return parent::getUnionType()->asNonEmptyListTypes();
        }
        return $this->type;
    }

    public function hasEmptyNonVariadicType() : bool
    {
        return $this->has_empty_non_variadic_type ?? parent::getUnionType()->isEmpty();
    }
}
