<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\UnionType;

/**
 * A Fully-Qualified Class Name
 */
class FullyQualifiedClassName extends FullyQualifiedGlobalStructuralElement {

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    protected static function getNamespaceMapType() : int {
        return T_CLASS;
    }

    /**
     * @return FullyQualifiedClassName
     * A fully qualified class name from the given type
     */
    public static function fromType(Type $type) : FullyQualifiedClassName {
        return self::fromFullyQualifiedString(
            (string)$type
        );
    }

    /**
     * @return Type
     * The type of this class
     */
    public function asType() : Type {
        return Type::fromFullyQualifiedString(
            (string)$this
        );
    }

    /**
     * @return UnionType
     * The union type of just this class type
     */
    public function asUnionType() : UnionType {
        return $this->asType()->asUnionType();
    }

    /**
     * @return FullyQualifiedMethodName
     * A fully-qualified method name
     */
    public function withMethodName(
        string $method_name,
        int $method_alternate_id = 0
    ) : FullyQualifiedMethodName {
        return FullyQualifiedMethodName::make(
            $this,
            $method_name,
            $method_alternate_id
        );
    }

    /**
     * @return FullyQualifiedConstantName
     * A fully-qualified constant name
     */
    public function withConstantName(
        string $constant_name
    ) : FullyQualifiedClassConstantName {
        return FullyQualifiedClassConstantName::make(
            $this,
            $constant_name
        );
    }

    /**
     * @return FullyQualifiedPropertyName
     * A fully-qualified property name
     */
    public function withPropertyName(
        string $property_name
    ) : FullyQualifiedPropertyName {
        return FullyQualifiedPropertyName::make(
            $this,
            $property_name
        );
    }
}
