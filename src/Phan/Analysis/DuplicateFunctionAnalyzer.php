<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;

/**
 * Checks to see if the given method is a duplicate of another method
 */
class DuplicateFunctionAnalyzer
{

    /**
     * Check to see if the given FunctionInterface is a duplicate
     */
    public static function analyzeDuplicateFunction(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
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
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            if (!$code_base->hasMethodWithFQSEN($original_fqsen)) {
                return;
            }

            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            $original_method = $code_base->getMethodByFQSEN($original_fqsen);
        }

        $method_name = $method->getName();

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
            // If there are 3 functions with the same namespace and name,
            // warn *once* about the first functions being a duplicate.
            // NOTE: This won't work very well in language server mode.
            if ($fqsen->getAlternateId() === 1) {
                Issue::maybeEmit(
                    $code_base,
                    $original_method->getContext(),
                    Issue::RedefineFunction,
                    $original_method->getFileRef()->getLineNumberStart(),
                    $original_method->getName(),
                    $original_method->getFileRef()->getFile(),
                    $original_method->getFileRef()->getLineNumberStart(),
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            }
        }
    }
}
