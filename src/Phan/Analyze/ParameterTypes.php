<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Log;

trait ParameterTypes {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeParameterTypes(
        CodeBase $code_base,
        Method $method
    ) {

        // Look at each method parameter
        foreach ($method->getParameterList() as $parameter) {
            $union_type = $parameter->getUnionType();

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getTypeList() as $type) {

                // If its a native type or a reference to
                // self, its OK
                if ($type->isNativeType() || $type->isSelfType()) {
                    continue;
                }


                // Otherwise, make sure the class exists
                $type_fqsen = $type->asFQSEN();
                if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                    Log::err(
                        Log::EUNDEF,
                        "parameter of undeclared type {$type_fqsen}",
                        $method->getContext()->getFile(),
                        $method->getContext()->getLineNumberStart()
                    );
                }
            }
        }
    }

}
