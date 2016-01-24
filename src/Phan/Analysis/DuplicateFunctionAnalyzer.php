<?php declare(strict_types=1);
namespace Phan\Analysis;

use \Phan\CodeBase;
use \Phan\Issue;
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

        if (!$code_base->hasMethod($original_fqsen)) {
            return;
        }

        $original_method =
            $code_base->getMethod($original_fqsen);

        $method_name = $method->getName();

        if ('internal' === $original_method->getContext()->getFile()) {
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
