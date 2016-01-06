<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Issue;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Log;

trait PropertyTypes {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzePropertyTypes(
        CodeBase $code_base,
        Clazz $clazz
    ) {
        foreach ($clazz->getPropertyList($code_base) as $property) {
            $union_type = $property->getUnionType();

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getTypeSet() as $type) {

                // If its a native type or a reference to
                // self, its OK
                if ($type->isNativeType() || $type->isSelfType()) {
                    continue;
                }

                // Otherwise, make sure the class exists
                $type_fqsen = $type->asFQSEN();
                if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                    Issue::emit(
                        Issue::UndeclaredTypeProperty,
                        $property->getContext()->getFile(),
                        $property->getContext()->getLineNumberStart(),
                        (string)$type_fqsen
                    );
                }
            }
        }
    }
}
