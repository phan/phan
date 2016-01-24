<?php declare(strict_types=1);
namespace Phan\Analysis;

use \Phan\CodeBase;
use \Phan\Issue;
use \Phan\Language\Element\FunctionInterface;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;

class ParameterTypesAnalyzer
{

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeParameterTypes(
        CodeBase $code_base,
        FunctionInterface $method
    ) {

        // Look at each method parameter
        foreach ($method->getParameterList() as $parameter) {
            $union_type = $parameter->getUnionType();

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
                        Issue::UndeclaredTypeParameter,
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart(),
                        (string)$type_fqsen
                    );
                }
            }
        }
    }
}
