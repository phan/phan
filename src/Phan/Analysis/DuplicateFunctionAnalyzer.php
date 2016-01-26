<?php declare(strict_types=1);
namespace Phan\Analysis;

use \Phan\CodeBase;
use \Phan\Issue;
use \Phan\Language\Element\Func;
use \Phan\Language\Element\FunctionInterface;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;

class DuplicateFunctionAnalyzer
{

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeDuplicateFunction(
        CodeBase $code_base,
        FunctionInterface $method
    ) {
        $fqsen = $method->getFQSEN();

        if (!$fqsen->isAlternate()) {
            return;
        }

        $original_fqsen = $fqsen->getCanonicalFQSEN();

        if ($original_fqsen instanceof FullyQualifiedFunctionName) {
            if (!$code_base->hasFunctionWithFQSEN($original_fqsen)) {
                return;
            }

            $original_method = $code_base->getFunctionByFQSEN(
                $original_fqsen
            );
        } else {
            if (!$code_base->hasMethodWithFQSEN($original_fqsen)) {
                return;
            }

            $original_method = $code_base->getMethodByFQSEN(
                $original_fqsen
            );
        }

        $method_name = $method->getName();

        if ($original_method->isInternal()) {
            Issue::emit(
                Issue::RedefineFunctionInternal,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart(),
                $method_name,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart()
            );
        } else {
            Issue::emit(
                Issue::RedefineFunction,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart(),
                $method_name,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart(),
                $original_method->getFileRef()->getFile(),
                $original_method->getFileRef()->getLineNumberStart()
            );
        }
    }
}
