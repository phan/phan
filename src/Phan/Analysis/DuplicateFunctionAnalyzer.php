<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\Analysis\FunctionAnalyzer;
use Phan\Analysis\MethodAnalyzer;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;

class DuplicateFunctionAnalyzer implements FunctionAnalyzer, MethodAnalyzer
{

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return void
     */
    private function analyzeDuplicateFunction(
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

        if (!$method->hasSuppressIssue(Issue::RedefineFunction)) {
            if ($original_method->isPHPInternal()) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::RedefineFunctionInternal,
                    $method->getFileRef()->getLineNumberStart(),
                    $method_name,
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::RedefineFunction,
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

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $this->analyzeDuplicateFunction($code_base, $function);
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        $this->analyzeDuplicateFunction($code_base, $method);
    }
}
