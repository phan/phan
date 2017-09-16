<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\Context;

abstract class ScalarType extends NativeType
{
    public function isScalar() : bool
    {
        return true;
    }

    public function isSelfType() : bool
    {
        return false;
    }

    public function isStaticType() : bool
    {
        return false;
    }

    public function isIterable() : bool
    {
        return false;
    }

    public function isArrayLike() : bool
    {
        return false;
    }

    public function isGenericArray() : bool
    {
        return false;
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     *
     * @param Type $parent (@phan-unused-param)
     *
     * @return bool
     * True if this type represents a class which is a sub-type of
     * the class represented by the passed type.
     */
    public function isSubclassOf(CodeBase $code_base, Type $parent) : bool
    {
        return false;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        // Scalars may be configured to always cast to eachother
        if ($type->isScalar()) {
            if (Config::getValue('scalar_implicit_cast')) {
                return true;
            }
            $scalar_implicit_partial = Config::getValue('scalar_implicit_partial');
            if (\count($scalar_implicit_partial) > 0) {
                // check if $type->getName() is in the list of permitted types $this->getName() can cast to.
                if (\in_array($type->getName(), $scalar_implicit_partial[$this->getName()] ?? [], true)) {
                    return true;
                }
            }
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @override
     */
    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $unused_context,
        CodeBase $unused_code_base
    ) : bool {
        return $union_type->hasType($this) || $this->asUnionType()->canCastToUnionType($union_type);
    }

    /**
     * @override
     */
    public function asFQSENString() : string
    {
        return $this->name;
    }

    public function getIsAlwaysTruthy() : bool
    {
        // Most scalars (Except ResourceType) have a false value, e.g. 0/""/"0"/0.0/false.
        // (But ResourceType isn't a subclass of ScalarType in Phan's implementation)
        return false;
    }

    public function asNonTruthyType() : Type
    {
        // Subclasses of ScalarType all have false values within their types.
        return $this;
    }
}
