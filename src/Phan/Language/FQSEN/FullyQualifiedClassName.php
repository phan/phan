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
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType() : int
    {
        return \ast\flags\USE_NORMAL;
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     * TODO: Separate the function used to render names in phan errors
     *       from the ones used for generating array keys.
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
        return self::memoizeStatic(__METHOD__, function () {
            return self::fromFullyQualifiedString("\stdClass");
        });
    }
}
