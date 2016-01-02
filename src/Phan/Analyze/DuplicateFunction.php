<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Issue;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Log;

trait DuplicateFunction {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeDuplicateFunction(
        CodeBase $code_base,
        Method $method
    ) {
        $fqsen = $method->getFQSEN();

        if (!$fqsen->isAlternate()) {
            return;
        }

        $original_fqsen = $fqsen->getCanonicalFQSEN();

        if (!$code_base->hasMethod($original_fqsen)) {
            return;
        }

        $original_method =
            $code_base->getMethod($original_fqsen);

        $method_name = $method->getName();

        if ('internal' === $original_method->getContext()->getFile()) {
            // If its in an conditional and the original is an
            // internal method, presume its all OK.
            if ($method->getContext()->getIsConditional()) {
                return;
            }

            Issue::emit(
                Issue::RedefineFunctionInternal,
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart(),
                $method_name,
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart()
            );
        } else {
            Issue::emit(
                Issue::RedefineFunction,
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart(),
                $method_name,
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart(),
                $original_method->getContext()->getFile(),
                $original_method->getContext()->getLineNumberStart()
            );
        }
    }

}
