<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\FQSEN2;

/**
 * A Fully-Qualified Constant Name
 */
class FullyQualifiedConstantName extends FullyQualifiedGlobalStructuralElement {

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    protected static function getNamespaceMapType() : int {
        return T_CONST;
    }

}
