<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Memoize;

/**
 * A Fully-Qualified Class Name
 */
class FullyQualifiedClassName extends FullyQualifiedGlobalStructuralElement
{
    use Memoize;

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    protected static function getNamespaceMapType() : int
    {
        return T_CLASS;
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name) : string
    {
        return $name;
    }

    /**
     * @return FullyQualifiedClassName
     * A fully qualified class name from the given type
     */
    public static function fromType(Type $type) : FullyQualifiedClassName
    {
        return self::fromFullyQualifiedString(
            $type->asFQSENString()
        );
    }

    /**
     * @return Type
     * The type of this class
     */
    public function asType() : Type
    {
        return Type::fromFullyQualifiedString(
            (string)$this
        );
    }

    /**
     * @return UnionType
     * The union type of just this class type
     */
    public function asUnionType() : UnionType
    {
        return $this->asType()->asUnionType();
    }

    /**
     * @return FullyQualifiedClassName
     * The FQSEN for \stdClass.
     */
    public static function getStdClassFQSEN() : FullyQualifiedClassName
    {
        return self::memoizeStatic(__METHOD__, function() {
            return self::fromFullyQualifiedString("\stdClass");
        });
    }
}
