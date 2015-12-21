<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

/**
 * A Fully-Qualified Constant Name
 */
class FullyQualifiedConstantName extends FullyQualifiedGlobalStructuralElement {
    use \Phan\Language\FQSEN\Alternatives;

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    protected static function getNamespaceMapType() : int {
        return T_CONST;
    }

}
